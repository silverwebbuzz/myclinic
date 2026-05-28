<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\MessagingSettings;
use App\Support\Plan;
use PDO;

/**
 * MessagingPolicy — the cost-control gate. Decides, for a given send:
 *   1. Which channel actually fires (messaging_rules: whatsapp/sms/push/off),
 *      per audience + event + the clinic's plan tier (trial vs paid).
 *   2. Whether per-event frequency caps (per day/week/month) are exceeded.
 *   3. Whether the clinic has quota left (base + add-on). If WhatsApp quota is
 *      gone, downgrade to SMS; if SMS is also gone, stop + flag a one-time
 *      "quota finished" alert.
 *   4. Quiet hours.
 *
 * resolve() returns the channel to send on, or null to skip. The processor
 * calls record() after a successful send to increment quota + usage log.
 *
 * Platform/marketing-origin messages use clinic_id = 0 → bypass quota
 * (those are the platform's own funnel, not a clinic's allowance).
 */
final class MessagingPolicy
{
    /**
     * @return array{channel: ?string, reason: string, downgraded: bool}
     *   channel null = skip the send.
     */
    public static function resolve(int $clinicId, string $audience, string $eventKey, string $requestedChannel): array
    {
        // Master switch.
        if (!MessagingSettings::enabled()) {
            return ['channel' => null, 'reason' => 'messaging disabled', 'downgraded' => false];
        }

        // Quiet hours (OTP never routes through here, so safe to block all).
        if (self::inQuietHours()) {
            return ['channel' => null, 'reason' => 'quiet hours', 'downgraded' => false];
        }

        // Platform/marketing origin: no clinic quota; honour the rule channel only.
        if ($clinicId === 0) {
            $ch = self::ruleChannel(0, $audience, $eventKey, $requestedChannel);
            return ['channel' => $ch, 'reason' => $ch ? 'platform send' : 'rule off', 'downgraded' => false];
        }

        $tier = Plan::hasAddon($clinicId, 'patient_connect') || self::isPaid($clinicId) ? 'paid' : 'trial';
        $rule = self::rule($audience, $eventKey, $tier);

        if ($rule === null || ($rule['channel'] ?? 'off') === 'off' || !$rule['is_active']) {
            return ['channel' => null, 'reason' => "rule off ({$tier})", 'downgraded' => false];
        }

        // Per-event frequency caps.
        if (self::frequencyExceeded($clinicId, $eventKey, $rule)) {
            return ['channel' => null, 'reason' => 'frequency cap', 'downgraded' => false];
        }

        $channel = $rule['channel'];

        // 'push' is delivered by the app layer (free) — let it through untouched.
        if ($channel === 'push') {
            return ['channel' => 'push', 'reason' => 'push', 'downgraded' => false];
        }

        // Quota check + downgrade chain.
        $quota = self::quota($clinicId);
        $downgraded = false;

        if ($channel === 'whatsapp') {
            if (self::hasQuota($quota, 'whatsapp')) {
                return ['channel' => 'whatsapp', 'reason' => 'ok', 'downgraded' => false];
            }
            // WhatsApp gone → downgrade to SMS.
            $channel = 'sms';
            $downgraded = true;
        }

        if ($channel === 'sms') {
            if (self::hasQuota($quota, 'sms')) {
                return ['channel' => 'sms', 'reason' => $downgraded ? 'downgraded to sms' : 'ok', 'downgraded' => $downgraded];
            }
            // Both exhausted → stop, flag the one-time quota-finished alert.
            self::flagQuotaFinished($clinicId);
            return ['channel' => null, 'reason' => 'quota exhausted', 'downgraded' => $downgraded];
        }

        return ['channel' => null, 'reason' => 'no channel', 'downgraded' => false];
    }

    /** Increment quota + write the usage log after a successful send. */
    public static function record(int $clinicId, ?int $notificationId, string $audience, string $eventKey, string $channel): void
    {
        if ($clinicId === 0 || $channel === 'push') {
            // Platform sends + push don't consume clinic quota; still log push for analytics if desired.
            return;
        }
        $period = date('Y-m');
        $col = $channel === 'whatsapp' ? 'whatsapp_used' : 'sms_used';

        $pdo = Database::connection();
        $pdo->prepare(
            "UPDATE messaging_quota SET {$col} = {$col} + 1 WHERE clinic_id = :c AND period_ym = :p"
        )->execute([':c' => $clinicId, ':p' => $period]);

        $pdo->prepare(
            'INSERT INTO messaging_usage_log
                (clinic_id, notification_id, audience, event_key, channel, period_ym)
             VALUES (:c, :n, :a, :e, :ch, :p)'
        )->execute([
            ':c' => $clinicId, ':n' => $notificationId, ':a' => $audience,
            ':e' => $eventKey, ':ch' => $channel, ':p' => $period,
        ]);
    }

    // ---------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------

    private static function rule(string $audience, string $eventKey, string $tier): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM messaging_rules
              WHERE audience = :a AND event_key = :e AND plan_tier = :t LIMIT 1'
        );
        $stmt->execute([':a' => $audience, ':e' => $eventKey, ':t' => $tier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function ruleChannel(int $clinicId, string $audience, string $eventKey, string $fallback): ?string
    {
        // Platform origin uses the 'paid' rule row as its baseline.
        $rule = self::rule($audience, $eventKey, 'paid');
        if ($rule === null) {
            return $fallback;
        }
        return ($rule['channel'] === 'off') ? null : $rule['channel'];
    }

    private static function frequencyExceeded(int $clinicId, string $eventKey, array $rule): bool
    {
        $checks = [
            ['cap' => $rule['per_day_cap']   ?? null, 'sql' => 'sent_at >= CURDATE()'],
            ['cap' => $rule['per_week_cap']  ?? null, 'sql' => 'sent_at >= NOW() - INTERVAL 7 DAY'],
            ['cap' => $rule['per_month_cap'] ?? null, 'sql' => "period_ym = DATE_FORMAT(NOW(),'%Y-%m')"],
        ];
        $pdo = Database::connection();
        foreach ($checks as $c) {
            if ($c['cap'] === null) {
                continue;
            }
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM messaging_usage_log
                  WHERE clinic_id = :c AND event_key = :e AND {$c['sql']}"
            );
            $stmt->execute([':c' => $clinicId, ':e' => $eventKey]);
            if ((int) $stmt->fetchColumn() >= (int) $c['cap']) {
                return true;
            }
        }
        return false;
    }

    private static function quota(int $clinicId): array
    {
        $period = date('Y-m');
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM messaging_quota WHERE clinic_id = :c LIMIT 1');
        $stmt->execute([':c' => $clinicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Lazy-create / roll over the period from platform defaults.
        if (!$row || $row['period_ym'] !== $period) {
            $waBase = (int) MessagingSettings::get('quota_whatsapp_base', '300');
            $smsBase = (int) MessagingSettings::get('quota_sms_base', '300');
            $pdo->prepare(
                'INSERT INTO messaging_quota
                    (clinic_id, period_ym, whatsapp_limit, sms_limit, whatsapp_addon, sms_addon, whatsapp_used, sms_used, quota_warned_at)
                 VALUES (:c, :p, :wl, :sl, 0, 0, 0, 0, NULL)
                 ON DUPLICATE KEY UPDATE
                    period_ym = VALUES(period_ym),
                    whatsapp_used = 0, sms_used = 0,
                    whatsapp_addon = 0, sms_addon = 0, quota_warned_at = NULL'
            )->execute([':c' => $clinicId, ':p' => $period, ':wl' => $waBase, ':sl' => $smsBase]);

            $stmt->execute([':c' => $clinicId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return $row ?: [];
    }

    private static function hasQuota(array $quota, string $channel): bool
    {
        if ($channel === 'whatsapp') {
            $limit = (int) ($quota['whatsapp_limit'] ?? 0) + (int) ($quota['whatsapp_addon'] ?? 0);
            return (int) ($quota['whatsapp_used'] ?? 0) < $limit;
        }
        $limit = (int) ($quota['sms_limit'] ?? 0) + (int) ($quota['sms_addon'] ?? 0);
        return (int) ($quota['sms_used'] ?? 0) < $limit;
    }

    private static function flagQuotaFinished(int $clinicId): void
    {
        $pdo = Database::connection();
        // Only fire the alert once per period.
        $stmt = $pdo->prepare('SELECT quota_warned_at FROM messaging_quota WHERE clinic_id = :c LIMIT 1');
        $stmt->execute([':c' => $clinicId]);
        if ($stmt->fetchColumn()) {
            return; // already warned this period
        }
        $pdo->prepare('UPDATE messaging_quota SET quota_warned_at = NOW() WHERE clinic_id = :c')
            ->execute([':c' => $clinicId]);

        // Queue a single SMS to the clinic owner. Best-effort.
        try {
            $owner = $pdo->prepare(
                "SELECT phone FROM users WHERE clinic_id = :c AND is_owner = 1 AND phone IS NOT NULL LIMIT 1"
            );
            $owner->execute([':c' => $clinicId]);
            $phone = $owner->fetchColumn();
            if ($phone) {
                \App\Core\QueryBuilder::table('notifications')->insert([
                    'clinic_id' => 0,  // platform alert — bypasses quota
                    'channel' => 'sms',
                    'template' => 'quota_warning',
                    'to_number' => $phone,
                    'payload' => json_encode(['clinic_id' => $clinicId]),
                    'status' => 'queued',
                    'scheduled_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private static function isPaid(int $clinicId): bool
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT plan_expires_at FROM tenants WHERE id = :c LIMIT 1'
            );
            $stmt->execute([':c' => $clinicId]);
            $exp = $stmt->fetchColumn();
            return $exp !== false && $exp !== null && $exp >= date('Y-m-d');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function inQuietHours(): bool
    {
        $start = (int) MessagingSettings::get('messaging_quiet_start', '21');
        $end = (int) MessagingSettings::get('messaging_quiet_end', '7');
        $h = (int) date('G');
        // Window wraps midnight (e.g. 21→7).
        return $start > $end ? ($h >= $start || $h < $end) : ($h >= $start && $h < $end);
    }
}
