<?php

declare(strict_types=1);

namespace App\Services\OccHealth;

use App\Core\Database;
use App\Services\Corporate\CorpMailer;
use App\Support\Locale\LocalDate;

/**
 * Super admin › Workforce Health › Clinics (design §4, C1 + C10): switch a
 * clinic on for corporate work, and verify doctors' registrations.
 */
final class ProviderAdminService
{
    /**
     * @param list<string>|null $countries null = all (super admin)
     * @return list<array<string, mixed>>
     */
    public static function clinics(string $q, bool $enabledOnly, ?array $countries): array
    {
        $where = ['t.is_active = 1'];
        $params = [];
        if ($enabledOnly) {
            $where[] = 't.corporate_enabled = 1';
        }
        if ($countries !== null) {
            if ($countries === []) {
                return [];
            }
            $in = [];
            foreach ($countries as $i => $cc) {
                $in[] = ':cc' . $i;
                $params['cc' . $i] = $cc;
            }
            $where[] = 't.country_code IN (' . implode(',', $in) . ')';
        }
        $q = trim($q);
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($q, 0, 80)) . '%';
            $where[] = '(t.name LIKE :q1 OR t.address LIKE :q2 OR t.email LIKE :q3 OR t.phone LIKE :q4)';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        } elseif (!$enabledOnly) {
            $where[] = 't.corporate_enabled = 1';   // no search: show the enabled ones only
        }
        $st = Database::connection()->prepare(
            "SELECT t.id, t.name, t.address, t.phone, t.email, t.country_code, t.corporate_enabled, t.created_at,
                    (SELECT COUNT(*) FROM corp_provider_links l WHERE l.clinic_id = t.id AND l.status = 'active') AS companies,
                    (SELECT COUNT(*) FROM corp_provider_links l2 WHERE l2.clinic_id = t.id AND l2.status = 'requested') AS waiting,
                    (SELECT COUNT(*) FROM occ_doctor_credentials c WHERE c.clinic_id = t.id AND c.status = 'verified') AS verified_doctors
               FROM tenants t /* cross-company: admin */
              WHERE " . implode(' AND ', $where) . '
              ORDER BY t.corporate_enabled DESC, t.name LIMIT 100'
        );
        $st->execute($params);

        return $st->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function clinic(int $clinicId): ?array
    {
        $st = Database::connection()->prepare('SELECT id, name, email, country_code, corporate_enabled FROM tenants WHERE id = :id LIMIT 1');
        $st->execute(['id' => $clinicId]);

        return $st->fetch() ?: null;
    }

    /** @return array{ok: bool, error?: string} */
    public static function setEnabled(int $clinicId, bool $on): array
    {
        $clinic = self::clinic($clinicId);
        if ($clinic === null) {
            return ['ok' => false, 'error' => 'Clinic not found.'];
        }
        Database::connection()->prepare('UPDATE tenants SET corporate_enabled = :on WHERE id = :id')->execute(['on' => $on ? 1 : 0, 'id' => $clinicId]);
        if ($on && (int) $clinic['corporate_enabled'] === 0) {
            CorpMailer::send((string) ($clinic['email'] ?? ''), 'Your clinic can now take corporate health checks', 'Corporate work is switched on',
                [$clinic['name'] . ' can now be added by companies on eClinicPro Workforce Health for employee health checks.',
                    'Next: open Settings › Corporate work and add the medical registration of each doctor who will sign fitness certificates. We verify it once.'],
                CorpMailer::appUrl() . '/settings/corporate', 'Open corporate work');
        }
        // Switching off keeps existing links (and their history); companies can't add the clinic again until it is back on.

        return ['ok' => true];
    }

    /**
     * @param list<string>|null $countries
     * @return list<array<string, mixed>>
     */
    public static function credentials(string $status, ?array $countries): array
    {
        $params = ['st' => in_array($status, ['pending', 'verified', 'rejected'], true) ? $status : 'pending'];
        $countrySql = '';
        if ($countries !== null) {
            if ($countries === []) {
                return [];
            }
            $in = [];
            foreach ($countries as $i => $cc) {
                $in[] = ':cc' . $i;
                $params['cc' . $i] = $cc;
            }
            $countrySql = ' AND c.country_code IN (' . implode(',', $in) . ')';
        }
        $st = Database::connection()->prepare(
            'SELECT c.*, u.name AS doctor_name, u.qualification, t.name AS clinic_name
               FROM occ_doctor_credentials c JOIN users u ON u.id = c.user_id AND u.clinic_id = c.clinic_id JOIN tenants t ON t.id = c.clinic_id
              WHERE c.status = :st' . $countrySql . '
              ORDER BY c.updated_at ' . ($params['st'] === 'pending' ? 'ASC' : 'DESC') . ' LIMIT 200'
        );
        $st->execute($params);

        return $st->fetchAll();
    }

    public static function pendingCount(): int
    {
        try {
            return (int) Database::connection()->query("SELECT COUNT(*) FROM occ_doctor_credentials WHERE status = 'pending'")->fetchColumn();
        } catch (\Throwable) {
            return 0;   // 1C patch not run yet
        }
    }

    /** @return array<string, mixed>|null */
    public static function credential(int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM occ_doctor_credentials WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);

        return $st->fetch() ?: null;
    }

    /** @return array{ok: bool, error?: string} */
    public static function review(int $id, int $adminId, bool $approve, string $note): array
    {
        $c = self::credential($id);
        if ($c === null) {
            return ['ok' => false, 'error' => 'Not found.'];
        }
        $note = mb_substr(trim($note), 0, 255);
        if (!$approve && $note === '') {
            return ['ok' => false, 'error' => 'Write why it is rejected (the clinic sees this).'];
        }
        $st = Database::connection()->prepare(
            'UPDATE occ_doctor_credentials SET status = :st, review_note = :n, verified_by = :by, verified_at = :at, updated_at = :up WHERE id = :id AND updated_at = :seen'
        );
        $now = LocalDate::nowUtc();
        $st->execute(['st' => $approve ? 'verified' : 'rejected', 'n' => $note ?: null, 'by' => $adminId, 'at' => $now, 'up' => $now, 'id' => $id, 'seen' => $c['updated_at']]);
        if ($st->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'The clinic changed it just now. Please look again.'];
        }

        return ['ok' => true];
    }
}
