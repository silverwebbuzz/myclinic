<?php

declare(strict_types=1);

namespace App\Services\Wellness;

use App\Core\Database;
use PDO;

/**
 * /admin/wellness/creators — applications, per-type permissions, Verified
 * Doctor / Organisation badge, suspend. Every decision is logged in
 * wh_creator_log and (when the creator gave an email) emailed to them.
 */
final class CreatorAdminService
{
    public const TABS = [
        'applications' => 'Permission requests',
        'verification' => 'Badge requests',
        'all'          => 'All creators',
        'suspended'    => 'Suspended',
    ];

    /** Pending permission applications + badge requests (sidebar badge). */
    public static function pendingCount(): int
    {
        try {
            $db = Database::connection();
            $a = (int) $db->query("SELECT COUNT(DISTINCT creator_id) FROM wh_creator_permissions WHERE status = 'applied'")->fetchColumn();
            $b = (int) $db->query("SELECT COUNT(*) FROM wh_creators WHERE verify_status = 'pending'")->fetchColumn();

            return $a + $b;
        } catch (\Throwable) {
            return 0;   // patch not run yet
        }
    }

    /** @return array<string,int> */
    public static function tabCounts(): array
    {
        try {
            $db = Database::connection();

            return [
                'applications' => (int) $db->query("SELECT COUNT(DISTINCT creator_id) FROM wh_creator_permissions WHERE status = 'applied'")->fetchColumn(),
                'verification' => (int) $db->query("SELECT COUNT(*) FROM wh_creators WHERE verify_status = 'pending'")->fetchColumn(),
                'all' => (int) $db->query("SELECT COUNT(*) FROM wh_creators WHERE creator_type <> 'platform'")->fetchColumn(),
                'suspended' => (int) $db->query("SELECT COUNT(*) FROM wh_creators WHERE status = 'suspended'")->fetchColumn(),
            ];
        } catch (\Throwable) {
            return array_fill_keys(array_keys(self::TABS), 0);
        }
    }

    /** @return list<array<string,mixed>> */
    public static function list(string $tab, string $q): array
    {
        $where = ["w.creator_type <> 'platform'"];
        $bind = [];
        if ($tab === 'applications') {
            $where[] = "EXISTS (SELECT 1 FROM wh_creator_permissions p WHERE p.creator_id = w.id AND p.status = 'applied')";
        } elseif ($tab === 'verification') {
            $where[] = "w.verify_status = 'pending'";
        } elseif ($tab === 'suspended') {
            $where[] = "w.status = 'suspended'";
        }
        $q = trim($q);
        if ($q !== '') {
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            if (strlen($digits) >= 6) {
                $where[] = 'pi.phone LIKE :qphone';
                $bind['qphone'] = '%' . substr($digits, -10) . '%';
            } else {
                $where[] = '(w.display_name LIKE :qname OR w.org_name LIKE :qorg OR w.email LIKE :qmail OR w.city LIKE :qcity)';
                $like = '%' . $q . '%';
                $bind += ['qname' => $like, 'qorg' => $like, 'qmail' => $like, 'qcity' => $like];
            }
        }
        $order = $tab === 'verification' ? 'w.verify_requested_at ASC' : ($tab === 'applications'
            ? "(SELECT MIN(p2.applied_at) FROM wh_creator_permissions p2 WHERE p2.creator_id = w.id AND p2.status = 'applied') ASC"
            : 'w.created_at DESC');

        $st = Database::connection()->prepare(
            "SELECT w.*, pi.phone AS identity_phone, pi.name AS identity_name,
                    (SELECT GROUP_CONCAT(CONCAT(p.content_type, ':', p.status) ORDER BY p.content_type)
                       FROM wh_creator_permissions p WHERE p.creator_id = w.id) AS perms
             FROM wh_creators w
             LEFT JOIN patient_identities pi ON pi.id = w.identity_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY $order
             LIMIT 200"
        );
        $st->execute($bind);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed>|null creator + identity + permissions + documents + log */
    public static function detail(int $id): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT w.*, pi.phone AS identity_phone, pi.name AS identity_name, pi.email AS identity_email, pi.created_at AS identity_created_at
             FROM wh_creators w LEFT JOIN patient_identities pi ON pi.id = w.identity_id WHERE w.id = :id LIMIT 1'
        );
        $st->execute(['id' => $id]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) {
            return null;
        }
        $log = Database::connection()->prepare('SELECT * FROM wh_creator_log WHERE creator_id = :c ORDER BY id DESC LIMIT 50');
        $log->execute(['c' => $id]);

        return $c + [
            'permissions' => CreatorService::permissions($id),
            'documents' => CreatorService::documents($id),
            'log' => $log->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'tip_counts' => CreatorService::tipCounts($c),
        ];
    }

    /** @return array<string,mixed>|null */
    public static function document(int $docId): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM wh_creator_documents WHERE id = :id LIMIT 1');
        $st->execute(['id' => $docId]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * approve | reject | suspend | grant (approve without an application).
     *
     * @return array{ok:bool,error?:string}
     */
    public static function decidePermission(int $creatorId, string $type, string $decision, string $note, ?int $adminId): array
    {
        $c = CreatorService::find($creatorId);
        if ($c === null || !isset(CreatorService::CONTENT[$type])) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $note = CreatorService::clean($note, 600);
        $current = CreatorService::permissions($creatorId)[$type] ?? null;

        $status = match ($decision) {
            'approve', 'grant' => 'approved',
            'reject' => 'rejected',
            'suspend' => 'suspended',
            default => null,
        };
        if ($status === null) {
            return ['ok' => false, 'error' => 'bad_decision'];
        }
        if ($decision === 'reject' && $note === null) {
            return ['ok' => false, 'error' => 'note_required'];
        }
        if ($decision === 'suspend' && ($current['status'] ?? '') !== 'approved') {
            return ['ok' => false, 'error' => 'not_approved'];
        }

        Database::connection()->prepare(
            "INSERT INTO wh_creator_permissions (creator_id, content_type, status, review_note, applied_at, reviewed_at, reviewed_by)
             VALUES (:c, :t, :s, :n, NOW(), NOW(), :a)
             ON DUPLICATE KEY UPDATE status = VALUES(status), review_note = VALUES(review_note),
                                     reviewed_at = NOW(), reviewed_by = VALUES(reviewed_by)"
        )->execute(['c' => $creatorId, 't' => $type, 's' => $status, 'n' => $note, 'a' => $adminId]);

        $label = CreatorService::CONTENT[$type][0];
        CreatorService::log($creatorId, 'permission_' . $status, $label . ($note ? ' — ' . $note : ''), $adminId);
        CreatorService::syncLegacy($creatorId);

        if (in_array($status, ['approved', 'rejected'], true)) {
            CreatorService::notifyCreator($c, 'wellness_permission_decision', [
                'decision' => $status,
                'type_label' => $label,
                'note' => (string) $note,
                'ready_now' => !in_array($type, CreatorService::UPCOMING_TYPES, true),
            ]);
        }

        return ['ok' => true];
    }

    /**
     * verify | reject | revoke the Verified Doctor / Organisation badge.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function decideVerification(int $creatorId, string $decision, string $note, string $register, ?int $adminId): array
    {
        $c = CreatorService::find($creatorId);
        if ($c === null || CreatorService::badgeKind($c) === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $note = CreatorService::clean($note, 600);
        $register = CreatorService::clean($register, 120);
        $db = Database::connection();

        if ($decision === 'verify') {
            if ($register === null) {
                return ['ok' => false, 'error' => 'register_required'];
            }
            $db->prepare(
                "UPDATE wh_creators SET verify_status = 'verified', verified_at = NOW(), verified_by = :a,
                        verify_register = :r, verify_note = :n WHERE id = :id"
            )->execute(['a' => $adminId, 'r' => $register, 'n' => $note, 'id' => $creatorId]);
            $db->prepare("UPDATE wh_creator_documents SET status = 'accepted' WHERE creator_id = :c AND purpose = 'verification' AND status = 'pending'")
               ->execute(['c' => $creatorId]);
            $status = 'verified';
        } elseif ($decision === 'reject' || $decision === 'revoke') {
            if ($note === null) {
                return ['ok' => false, 'error' => 'note_required'];
            }
            if ($decision === 'revoke' && $c['verify_status'] !== 'verified') {
                return ['ok' => false, 'error' => 'not_verified'];
            }
            $status = $decision === 'reject' ? 'rejected' : 'revoked';
            // Without the badge the "Dr." prefix has to go (the profile rule).
            $name = (string) $c['display_name'];
            $stripped = trim((string) preg_replace('/^\s*dr\b\.?\s*/iu', '', $name));
            $db->prepare(
                'UPDATE wh_creators SET verify_status = :s, verify_note = :n, display_name = :dn WHERE id = :id'
            )->execute(['s' => $status, 'n' => $note, 'dn' => $stripped !== '' ? $stripped : $name, 'id' => $creatorId]);
            if ($decision === 'reject') {
                $db->prepare("UPDATE wh_creator_documents SET status = 'rejected' WHERE creator_id = :c AND purpose = 'verification' AND status = 'pending'")
                   ->execute(['c' => $creatorId]);
            }
            CreatorService::syncLegacy($creatorId);
        } else {
            return ['ok' => false, 'error' => 'bad_decision'];
        }

        $label = CreatorService::badgeKind($c) === 'doctor' ? 'Verified Doctor' : 'Verified Organisation';
        CreatorService::log($creatorId, 'verify_' . $status, $label . ($register ? ' · register: ' . $register : '') . ($note ? ' — ' . $note : ''), $adminId);
        CreatorService::notifyCreator($c, 'wellness_verification_decision', [
            'decision' => $status, 'badge' => $label, 'note' => (string) $note,
        ]);

        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function setStatus(int $creatorId, string $status, string $reason, ?int $adminId): array
    {
        $c = CreatorService::find($creatorId);
        if ($c === null || !in_array($status, ['active', 'suspended'], true) || $c['creator_type'] === 'platform') {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $reason = CreatorService::clean($reason, 255);
        if ($status === 'suspended' && $reason === null) {
            return ['ok' => false, 'error' => 'note_required'];
        }
        Database::connection()->prepare('UPDATE wh_creators SET status = :s, status_reason = :r WHERE id = :id')
            ->execute(['s' => $status, 'r' => $status === 'suspended' ? $reason : null, 'id' => $creatorId]);
        CreatorService::log($creatorId, $status === 'suspended' ? 'suspended' : 'reactivated', $reason, $adminId);
        CreatorService::syncLegacy($creatorId);

        return ['ok' => true];
    }

    public static function saveNote(int $creatorId, string $note, bool $trusted, ?int $adminId): void
    {
        Database::connection()->prepare('UPDATE wh_creators SET admin_note = :n, is_trusted = :t WHERE id = :id')
            ->execute(['n' => CreatorService::clean($note, 255), 't' => $trusted ? 1 : 0, 'id' => $creatorId]);
        CreatorService::log($creatorId, 'admin_note', $trusted ? 'Marked trusted' : null, $adminId);
    }
}
