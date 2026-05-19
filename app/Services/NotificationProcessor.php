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

        try {
            if ($channel === 'whatsapp') {
                if (!self::hasModule($clinicId, 'whatsapp')) {
                    self::markFailed($id, 'whatsapp module inactive');

                    return false;
                }
                $result = WhatsAppService::send((string) ($row['to_number'] ?? ''), $template, $payload);
                if (!$result['ok']) {
                    self::markFailed($id, $result['message']);

                    return false;
                }
            } elseif ($channel === 'sms') {
                if (!self::hasModule($clinicId, 'sms_email')) {
                    self::markFailed($id, 'sms module inactive');

                    return false;
                }
                $body = NotificationTemplateService::render($template, $payload);
                $result = TwilioSmsService::send((string) ($row['to_number'] ?? ''), $body);
                if (!$result['ok']) {
                    self::markFailed($id, $result['message']);

                    return false;
                }
            } elseif ($channel === 'email') {
                MailService::send(
                    (string) ($row['to_email'] ?? ''),
                    $template,
                    $payload,
                    $clinicId,
                );
            }

            QueryBuilder::table('notifications')
                ->where('id', '=', $id)
                ->update([
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s'),
                    'attempts' => (int) ($row['attempts'] ?? 0) + 1,
                ]);

            return true;
        } catch (\Throwable $e) {
            self::markFailed($id, $e->getMessage());

            return false;
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

    private static function hasModule(int $clinicId, string $moduleId): bool
    {
        $row = QueryBuilder::table('clinic_modules')
            ->forClinic($clinicId)
            ->where('module_id', '=', $moduleId)
            ->where('is_active', '=', 1)
            ->first();

        return $row !== null;
    }
}
