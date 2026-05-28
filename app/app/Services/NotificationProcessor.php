<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
final class NotificationProcessor
{
    public static function processQueue(int $limit = 50): int
    {
        if (!Database::ping()) {
            return 0;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT * FROM notifications
             WHERE status = 'queued' AND scheduled_at <= NOW()
             ORDER BY scheduled_at ASC
             LIMIT ?",
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        $processed = 0;
        foreach ($rows as $row) {
            if (self::processOne($row)) {
                $processed++;
            }
        }

        return $processed;
    }

    /** @param array<string, mixed> $row */
    private static function processOne(array $row): bool
    {
        $id = (int) $row['id'];
        $clinicId = (int) $row['clinic_id'];
        $payload = json_decode((string) ($row['payload'] ?? '{}'), true) ?: [];
        $channel = $row['channel'] ?? 'whatsapp';
        $template = $row['template'] ?? '';

        $to = (string) ($row['to_number'] ?? '');
        $audience = str_starts_with($template, 'doctor_') || $template === 'quota_warning' ? 'doctor' : 'patient';

        try {
            if ($channel === 'whatsapp') {
                // MessagingPolicy decides the actual channel: applies rules
                // (trial vs paid, on/off per event), per-event frequency caps,
                // quota (with WhatsApp→SMS downgrade), and quiet hours.
                $decision = MessagingPolicy::resolve($clinicId, $audience, $template, 'whatsapp');
                $channel = $decision['channel'];

                if ($channel === null) {
                    self::markSkipped($id, $decision['reason']);
                    return false;
                }

                if ($channel === 'push') {
                    // App push handled elsewhere; mark sent so queue advances.
                    MessagingPolicy::record($clinicId, $id, $audience, $template, 'push');
                    self::markSent($id, $row, null, 'push');
                    return true;
                }

                if ($channel === 'sms') {
                    // Policy downgraded WhatsApp→SMS (quota/cache). Send as SMS.
                    return self::sendSmsFallback($id, $row, $to, $template, $payload, $decision['reason'], $audience);
                }

                // 3-state cache: known NOT on WhatsApp → straight to SMS.
                if (self::knownNoWhatsApp($to)) {
                    return self::sendSmsFallback($id, $row, $to, $template, $payload, 'cached: not on WhatsApp', $audience);
                }

                $result = WhatsAppService::send($to, $template, $payload);
                if (!$result['ok']) {
                    return self::sendSmsFallback($id, $row, $to, $template, $payload, $result['message'], $audience);
                }

                MessagingPolicy::record($clinicId, $id, $audience, $template, 'whatsapp');
                QueryBuilder::table('notifications')
                    ->where('id', '=', $id)
                    ->update([
                        'status' => 'sent',
                        'sent_at' => date('Y-m-d H:i:s'),
                        'provider_message_id' => $result['wamid'] ?? null,
                        'delivery_status' => 'sent',
                        'attempts' => (int) ($row['attempts'] ?? 0) + 1,
                    ]);
                return true;
            }

            if ($channel === 'sms') {
                // Direct SMS rows (incl. fallback_of rows) still respect quota,
                // EXCEPT platform-origin (clinic_id 0) which bypasses.
                if ($clinicId > 0 && empty($row['fallback_of'])) {
                    $decision = MessagingPolicy::resolve($clinicId, $audience, $template, 'sms');
                    if ($decision['channel'] === null) {
                        self::markSkipped($id, $decision['reason']);
                        return false;
                    }
                }
                $body = WaTemplateService::renderPlain($template, $payload);
                $result = SmsService::send($to, $body);
                if (!$result['ok']) {
                    self::markFailed($id, $result['message']);
                    return false;
                }
                MessagingPolicy::record($clinicId, $id, $audience, $template, 'sms');
                QueryBuilder::table('notifications')
                    ->where('id', '=', $id)
                    ->update([
                        'status' => 'sent',
                        'sent_at' => date('Y-m-d H:i:s'),
                        'provider_message_id' => $result['provider_id'] ?? null,
                        'delivery_status' => 'sent',
                        'attempts' => (int) ($row['attempts'] ?? 0) + 1,
                    ]);
                return true;
            }

            if ($channel === 'email') {
                MailService::send((string) ($row['to_email'] ?? ''), $template, $payload, $clinicId);
                QueryBuilder::table('notifications')
                    ->where('id', '=', $id)
                    ->update([
                        'status' => 'sent',
                        'sent_at' => date('Y-m-d H:i:s'),
                        'attempts' => (int) ($row['attempts'] ?? 0) + 1,
                    ]);
                return true;
            }

            self::markFailed($id, 'unknown channel: ' . $channel);
            return false;
        } catch (\Throwable $e) {
            self::markFailed($id, $e->getMessage());

            return false;
        }
    }

    /**
     * Enqueue an SMS fallback for a failed WhatsApp row (and mark the WA row
     * failed). The SMS row is linked via fallback_of and never re-falls-back.
     * @param array<string,mixed> $row @param array<string,mixed> $payload
     */
    private static function sendSmsFallback(int $waId, array $row, string $to, string $template, array $payload, string $reason, string $audience = 'patient'): bool
    {
        $clinicId = (int) $row['clinic_id'];

        // Mark the original WhatsApp row failed with the reason.
        QueryBuilder::table('notifications')
            ->where('id', '=', $waId)
            ->update([
                'status' => 'failed',
                'delivery_status' => 'failed',
                'error_log' => $reason,
                'attempts' => (int) ($row['attempts'] ?? 0) + 1,
            ]);

        // Insert a fresh SMS row referencing the failed WA row.
        $newId = QueryBuilder::table('notifications')->insert([
            'clinic_id' => $clinicId,
            'patient_id' => $row['patient_id'] ?? null,
            'patient_identity_id' => $row['patient_identity_id'] ?? null,
            'channel' => 'sms',
            'template' => $template,
            'to_number' => $to,
            'payload' => json_encode($payload),
            'status' => 'queued',
            'fallback_of' => $waId,
            'scheduled_at' => date('Y-m-d H:i:s'),
        ]);

        // Send it now (best-effort; if it fails the queue retry catches it).
        $body = WaTemplateService::renderPlain($template, $payload);
        $result = SmsService::send($to, $body);
        if ($result['ok']) {
            MessagingPolicy::record($clinicId, (int) $newId, $audience, $template, 'sms');
        }
        QueryBuilder::table('notifications')
            ->where('id', '=', (int) $newId)
            ->update($result['ok']
                ? ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'), 'delivery_status' => 'sent', 'attempts' => 1]
                : ['status' => 'failed', 'error_log' => $result['message'], 'attempts' => 1]);

        return $result['ok'];
    }

    /** Policy decided not to send (rule off / cap / quota / quiet hours). */
    private static function markSkipped(int $id, string $reason): void
    {
        QueryBuilder::table('notifications')
            ->where('id', '=', $id)
            ->update([
                'status' => 'failed',          // 'failed' enum reused; error_log says skipped
                'delivery_status' => 'skipped',
                'error_log' => 'skipped: ' . $reason,
                'attempts' => 1,
            ]);
    }

    /** Mark a row sent on a non-SMS/WA channel (e.g. push). */
    private static function markSent(int $id, array $row, ?string $providerId, string $deliveryStatus): void
    {
        QueryBuilder::table('notifications')
            ->where('id', '=', $id)
            ->update([
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s'),
                'provider_message_id' => $providerId,
                'delivery_status' => $deliveryStatus,
                'attempts' => (int) ($row['attempts'] ?? 0) + 1,
            ]);
    }

    /** Is this number cached as NOT a WhatsApp user, checked within 90 days? */
    private static function knownNoWhatsApp(string $toNumber): bool
    {
        $digits = preg_replace('/\D/', '', $toNumber) ?? '';
        if ($digits === '') {
            return false;
        }
        try {
            $pdo = Database::connection();
            // last-10-digit match (handles +91 prefix variance).
            $stmt = $pdo->prepare(
                "SELECT 1 FROM patient_identities
                  WHERE whatsapp_status = 'no'
                    AND whatsapp_checked_at >= NOW() - INTERVAL 90 DAY
                    AND RIGHT(REPLACE(REPLACE(phone,'+',''),' ',''), 10) = RIGHT(:p, 10)
                  LIMIT 1"
            );
            $stmt->execute([':p' => $digits]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false; // columns missing pre-migration → don't skip WhatsApp
        }
    }

    private static function markFailed(int $id, string $error): void
    {
        QueryBuilder::table('notifications')
            ->where('id', '=', $id)
            ->update([
                'status' => 'failed',
                'error_log' => $error,
                'attempts' => 1,
            ]);
    }

    public static function queueDailyReminders(): int
    {
        if (!Database::ping()) {
            return 0;
        }

        $count = 0;
        $pdo = Database::connection();

        $sql = "SELECT a.*, p.name AS patient_name, p.phone, t.name AS clinic_name
                FROM appointments a
                INNER JOIN patients p ON p.id = a.patient_id
                INNER JOIN tenants t ON t.id = a.clinic_id
                WHERE a.status NOT IN ('cancelled', 'completed', 'no_show')
                AND (
                    (a.scheduled_at BETWEEN DATE_ADD(NOW(), INTERVAL 23 HOUR) AND DATE_ADD(NOW(), INTERVAL 25 HOUR))
                    OR (a.scheduled_at BETWEEN DATE_ADD(NOW(), INTERVAL 55 MINUTE) AND DATE_ADD(NOW(), INTERVAL 65 MINUTE))
                )";

        $appointments = $pdo->query($sql)->fetchAll() ?: [];
        foreach ($appointments as $appt) {
            $hours = (strtotime($appt['scheduled_at']) - time()) / 3600;
            $template = $hours > 2 ? 'appointment_reminder' : 'appointment_reminder';
            NotificationService::queueWhatsApp(
                (int) $appt['clinic_id'],
                (int) $appt['patient_id'],
                (string) $appt['phone'],
                $template,
                [
                    'patient_name' => $appt['patient_name'],
                    'clinic_name' => $appt['clinic_name'],
                    'scheduled_at' => $appt['scheduled_at'],
                    'hours_before' => $hours > 2 ? 24 : 1,
                ],
                date('Y-m-d H:i:s'),
            );
            $count++;
        }

        $followSql = "SELECT v.*, p.name AS patient_name, p.phone, t.name AS clinic_name
                      FROM visits v
                      INNER JOIN patients p ON p.id = v.patient_id
                      INNER JOIN tenants t ON t.id = v.clinic_id
                      WHERE v.follow_up_date = CURDATE() AND v.status = 'completed'";
        $followups = $pdo->query($followSql)->fetchAll() ?: [];
        foreach ($followups as $v) {
            NotificationService::queueWhatsApp(
                (int) $v['clinic_id'],
                (int) $v['patient_id'],
                (string) $v['phone'],
                'follow_up_reminder',
                [
                    'patient_name' => $v['patient_name'],
                    'clinic_name' => $v['clinic_name'],
                ],
                date('Y-m-d') . ' 07:00:00',
            );
            $count++;
        }

        return $count;
    }
}
