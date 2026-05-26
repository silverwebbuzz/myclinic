<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * CRUD for the global directory_sms_settings row + per-doctor quota overrides.
 * Single-row config table; read/write through this service so callers don't
 * touch the DB directly.
 */
final class LeadSettingsService
{
    public static function get(): array
    {
        $db = Database::connection();
        $row = $db->query('SELECT * FROM directory_sms_settings WHERE id = 1')
                  ->fetch(PDO::FETCH_ASSOC);
        return $row ?: self::defaults();
    }

    public static function save(array $input): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE directory_sms_settings SET
                enabled            = :enabled,
                default_per_day    = :per_day,
                default_per_week   = :per_week,
                default_per_month  = :per_month,
                provider_template_id = :tpl_id,
                template_body      = :tpl_body,
                quiet_hours_start  = :qh_start,
                quiet_hours_end    = :qh_end,
                lead_landing_base  = :base
             WHERE id = 1'
        );
        $stmt->execute([
            'enabled'  => !empty($input['enabled']) ? 1 : 0,
            'per_day'  => max(0, (int) ($input['default_per_day'] ?? 2)),
            'per_week' => max(0, (int) ($input['default_per_week'] ?? 5)),
            'per_month'=> max(0, (int) ($input['default_per_month'] ?? 20)),
            'tpl_id'   => trim((string) ($input['provider_template_id'] ?? '')) ?: null,
            'tpl_body' => trim((string) ($input['template_body'] ?? '')) ?: self::defaults()['template_body'],
            'qh_start' => self::normTime((string) ($input['quiet_hours_start'] ?? '21:00')),
            'qh_end'   => self::normTime((string) ($input['quiet_hours_end']   ?? '08:00')),
            'base'     => trim((string) ($input['lead_landing_base'] ?? 'https://eclinicpro.com/L/')),
        ]);
    }

    /** Set or clear a per-doctor quota row. Pass null to fields you don't override. */
    public static function saveDoctorQuota(int $doctorId, ?int $perDay, ?int $perWeek, ?int $perMonth, bool $paused): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO directory_sms_quotas
                (directory_doctor_id, per_day, per_week, per_month, is_paused, paused_at, pause_reason)
             VALUES
                (:id, :pd, :pw, :pm, :paused, :paused_at, :reason)
             ON DUPLICATE KEY UPDATE
                per_day     = VALUES(per_day),
                per_week    = VALUES(per_week),
                per_month   = VALUES(per_month),
                is_paused   = VALUES(is_paused),
                paused_at   = VALUES(paused_at),
                pause_reason = VALUES(pause_reason)'
        );
        $stmt->execute([
            'id'        => $doctorId,
            'pd'        => $perDay,
            'pw'        => $perWeek,
            'pm'        => $perMonth,
            'paused'    => $paused ? 1 : 0,
            'paused_at' => $paused ? date('Y-m-d H:i:s') : null,
            'reason'    => $paused ? 'admin_set' : null,
        ]);
    }

    /** List doctors that have a custom quota row (overrides + paused). */
    public static function listOverrides(int $limit = 50): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT dsq.*, dd.name AS clinic_name, dd.doctor_name, dd.city, dd.phone
             FROM directory_sms_quotas dsq
             JOIN directory_doctors dd ON dd.id = dsq.directory_doctor_id
             ORDER BY dsq.is_paused DESC, dsq.updated_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function defaults(): array
    {
        return [
            'enabled' => 1,
            'default_per_day' => 2, 'default_per_week' => 5, 'default_per_month' => 20,
            'provider_template_id' => null,
            'template_body' => 'eClinicPro: {patient_name} wants to book you {date} at {time}. View: {url} Reply STOP to opt out.',
            'quiet_hours_start' => '21:00:00',
            'quiet_hours_end' => '08:00:00',
            'lead_landing_base' => 'https://eclinicpro.com/L/',
        ];
    }

    private static function normTime(string $t): string
    {
        // Accept "21:00" or "21:00:00" — store as HH:MM:SS.
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($t), $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
        }
        return '00:00:00';
    }
}
