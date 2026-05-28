<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Support\MessagingSettings;
use PDO;

/**
 * WhatsAppWebhookService — processes Meta WhatsApp Cloud API webhook events.
 *
 * Two kinds of events arrive at /webhooks/whatsapp:
 *   1. statuses[] — delivery receipts (sent/delivered/read/failed) keyed by
 *      the wamid we stored when sending. Drives:
 *        - notifications.delivery_status update
 *        - the WhatsApp-capable 3-state cache (yes/no) on patient_identities
 *          and directory_doctors
 *        - SMS fallback enqueue on hard failure
 *   2. messages[] — inbound messages (button taps / patient-initiated chats).
 *      Confirm-button taps mark a lead confirmed; any inbound opens a 24h
 *      free service window (informational here).
 */
final class WhatsAppWebhookService
{
    /** Meta signature check (X-Hub-Signature-256: sha256=...). */
    public static function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = MessagingSettings::waAppSecret();
        if ($secret === null) {
            // Not configured (dev) → accept so local testing works.
            return true;
        }
        if ($signatureHeader === null || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, substr($signatureHeader, 7));
    }

    /** @param array<string,mixed> $event */
    public static function handle(array $event): void
    {
        $entries = $event['entry'] ?? [];
        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                foreach ($value['statuses'] ?? [] as $status) {
                    self::handleStatus($status);
                }
                foreach ($value['messages'] ?? [] as $message) {
                    self::handleInbound($message);
                }
            }
        }
    }

    /** @param array<string,mixed> $status */
    private static function handleStatus(array $status): void
    {
        $wamid = $status['id'] ?? null;
        $state = $status['status'] ?? null;   // sent | delivered | read | failed
        if (!$wamid || !$state) {
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE provider_message_id = :w LIMIT 1');
        $stmt->execute([':w' => $wamid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $to = (string) ($row['to_number'] ?? '');

        // Update delivery status (+ delivered_at on delivered/read).
        $update = ['delivery_status' => $state];
        if (in_array($state, ['delivered', 'read'], true)) {
            $update['delivered_at'] = date('Y-m-d H:i:s');
            self::cacheWhatsApp($to, 'yes');
        }
        QueryBuilder::table('notifications')->where('id', '=', (int) $row['id'])->update($update);

        if ($state === 'failed') {
            $err = $status['errors'][0] ?? [];
            $code = (int) ($err['code'] ?? 0);
            // 131026 / 131047 etc = not a WhatsApp user / undeliverable.
            $notWhatsApp = in_array($code, [131026, 131047, 131052, 131053], true);
            if ($notWhatsApp) {
                self::cacheWhatsApp($to, 'no');
            }
            // Webhook-driven SMS fallback (only once; SMS rows never re-fall-back).
            if (empty($row['fallback_of']) && ($row['channel'] ?? '') === 'whatsapp') {
                self::enqueueSmsFallback($row);
            }
        }
    }

    /** @param array<string,mixed> $message */
    private static function handleInbound(array $message): void
    {
        $from = $message['from'] ?? '';
        if ($from !== '') {
            // Any inbound proves the number is on WhatsApp + opens a service window.
            self::cacheWhatsApp($from, 'yes');
        }

        // Button reply (Confirm). Meta delivers interactive button payloads.
        $btn = $message['button']['payload']
            ?? ($message['interactive']['button_reply']['id'] ?? null);
        if ($btn && str_starts_with((string) $btn, 'confirm:')) {
            $token = substr((string) $btn, strlen('confirm:'));
            if ($token !== '') {
                LeadFlowService::confirm($token);
            }
        }
    }

    /** Update the 3-state WhatsApp cache on identity + directory_doctor by phone tail. */
    private static function cacheWhatsApp(string $number, string $state): void
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';
        if ($digits === '' || !in_array($state, ['yes', 'no'], true)) {
            return;
        }
        $tail = substr($digits, -10);
        try {
            $pdo = Database::connection();
            foreach (['patient_identities', 'directory_doctors'] as $table) {
                $pdo->prepare(
                    "UPDATE $table
                        SET whatsapp_status = :s, whatsapp_checked_at = NOW()
                      WHERE RIGHT(REPLACE(REPLACE(phone,'+',''),' ',''), 10) = :t"
                )->execute([':s' => $state, ':t' => $tail]);
            }
        } catch (\Throwable $e) {
            // columns missing pre-migration — ignore
        }
    }

    /** @param array<string,mixed> $waRow */
    private static function enqueueSmsFallback(array $waRow): void
    {
        $payload = json_decode((string) ($waRow['payload'] ?? '{}'), true) ?: [];
        $template = (string) ($waRow['template'] ?? '');
        $to = (string) ($waRow['to_number'] ?? '');

        $newId = QueryBuilder::table('notifications')->insert([
            'clinic_id' => (int) $waRow['clinic_id'],
            'patient_id' => $waRow['patient_id'] ?? null,
            'channel' => 'sms',
            'template' => $template,
            'to_number' => $to,
            'payload' => json_encode($payload),
            'status' => 'queued',
            'fallback_of' => (int) $waRow['id'],
            'scheduled_at' => date('Y-m-d H:i:s'),
        ]);

        $body = WaTemplateService::renderPlain($template, $payload);
        $result = SmsService::send($to, $body);
        QueryBuilder::table('notifications')
            ->where('id', '=', (int) $newId)
            ->update($result['ok']
                ? ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'), 'delivery_status' => 'sent', 'attempts' => 1]
                : ['status' => 'failed', 'error_log' => $result['message'], 'attempts' => 1]);
    }
}
