<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Manages the doctor claim review queue (claim + new_listing requests).
 * Approval creates a clinic (tenant), a doctor user, and links the
 * directory listing if it was a claim.
 */
final class DoctorClaimService
{
    /** @return list<array<string, mixed>> */
    public static function pending(): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT * FROM doctor_claim_requests
             WHERE status IN ("pending", "phone_verified")
             ORDER BY created_at DESC
             LIMIT 200'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach the directory listing snippet for claims.
        $directoryIds = [];
        foreach ($rows as $r) {
            if (!empty($r['directory_doctor_id'])) {
                $directoryIds[] = (int) $r['directory_doctor_id'];
            }
        }
        $listings = [];
        if ($directoryIds) {
            $placeholders = implode(',', array_fill(0, count($directoryIds), '?'));
            $sel = self::pdo()->prepare(
                "SELECT * FROM directory_doctors WHERE id IN ($placeholders)"
            );
            $sel->execute($directoryIds);
            foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $d) {
                $listings[(int) $d['id']] = $d;
            }
        }
        foreach ($rows as &$r) {
            $r['_listing'] = !empty($r['directory_doctor_id'])
                ? ($listings[(int) $r['directory_doctor_id']] ?? null)
                : null;
        }
        return $rows;
    }

    public static function pendingCount(): int
    {
        $stmt = self::pdo()->prepare(
            'SELECT COUNT(*) FROM doctor_claim_requests
             WHERE status IN ("pending", "phone_verified")'
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::pdo()->prepare(
            'SELECT * FROM doctor_claim_requests WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        if (!empty($row['directory_doctor_id'])) {
            $sel = self::pdo()->prepare(
                'SELECT * FROM directory_doctors WHERE id = :id LIMIT 1'
            );
            $sel->execute(['id' => (int) $row['directory_doctor_id']]);
            $row['_listing'] = $sel->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        return $row;
    }

    /**
     * Approve a request. Creates clinic + doctor user, links the directory
     * listing if it was a claim. Returns the created user_id or null on failure.
     */
    public static function approve(int $requestId, int $reviewerId, ?string $notes = null): ?int
    {
        $req = self::find($requestId);
        if ($req === null) return null;
        if (in_array($req['status'], ['approved', 'rejected'], true)) return null;

        $db = self::pdo();
        $db->beginTransaction();
        try {
            // 1) Create the tenant (clinic).
            $clinicSlug = self::makeUniqueSlug((string) $req['clinic_name'], 'tenants', 'slug');
            $addrLine   = trim(($req['city'] ?? '') . ($req['state'] ? ', ' . $req['state'] : ''));
            $tenantStmt = $db->prepare(
                'INSERT INTO tenants (name, slug, country_code, address, phone, email, is_active, created_at)
                 VALUES (:n, :s, "IN", :addr, :phone, :email, 1, NOW())'
            );
            $tenantStmt->execute([
                'n'     => $req['clinic_name'],
                's'     => $clinicSlug,
                'addr'  => $addrLine ?: null,
                'phone' => $req['phone'],
                'email' => $req['email'],
            ]);
            $tenantId = (int) $db->lastInsertId();

            // 2) Create the doctor user. Email is required + unique, but we
            // don't have one — use a placeholder. Password_hash is NOT NULL
            // but we set an unusable bcrypt hash since auth is via OTP.
            $placeholderEmail = $req['email'] ?: ('doctor+' . $tenantId . '@eclinicpro.placeholder');
            $unusableHash     = '!disabled:' . bin2hex(random_bytes(8));   // never matches a valid bcrypt
            $userStmt = $db->prepare(
                'INSERT INTO users
                    (clinic_id, name, email, phone, password_hash, role, is_owner,
                     specialization, qualification, is_active, created_at)
                 VALUES
                    (:cid, :name, :email, :phone, :pwd, "doctor", 1,
                     :spec, :qual, 1, NOW())'
            );
            $userStmt->execute([
                'cid'   => $tenantId,
                'name'  => $req['full_name'],
                'email' => $placeholderEmail,
                'phone' => $req['phone'],
                'pwd'   => $unusableHash,
                'spec'  => $req['specialty'],
                'qual'  => trim(($req['reg_council'] ? $req['reg_council'] . ' ' : '') . ($req['reg_number'] ?: '')) ?: null,
            ]);
            $userId = (int) $db->lastInsertId();

            // 3) If this was a claim, flip the directory listing.
            if (!empty($req['directory_doctor_id'])) {
                $db->prepare(
                    'UPDATE directory_doctors
                     SET is_claimed = 1, claimed_tenant_id = :tid
                     WHERE id = :did'
                )->execute([
                    'tid' => $tenantId,
                    'did' => (int) $req['directory_doctor_id'],
                ]);
            }

            // 4) Mark the request approved.
            $db->prepare(
                'UPDATE doctor_claim_requests
                 SET status = "approved", reviewed_by = :rb, reviewed_at = NOW(),
                     reviewer_notes = :n, created_tenant_id = :tid, created_user_id = :uid
                 WHERE id = :id'
            )->execute([
                'rb'  => $reviewerId,
                'n'   => $notes,
                'tid' => $tenantId,
                'uid' => $userId,
                'id'  => $requestId,
            ]);

            $db->commit();
            return $userId;
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[DoctorClaimService::approve] ' . $e->getMessage());
            return null;
        }
    }

    public static function reject(int $requestId, int $reviewerId, ?string $notes = null): bool
    {
        return self::setStatus($requestId, 'rejected', $reviewerId, $notes);
    }

    public static function markDuplicate(int $requestId, int $reviewerId, ?string $notes = null): bool
    {
        return self::setStatus($requestId, 'duplicate', $reviewerId, $notes);
    }

    private static function setStatus(int $requestId, string $status, int $reviewerId, ?string $notes): bool
    {
        $req = self::find($requestId);
        if ($req === null) return false;
        if (in_array($req['status'], ['approved', 'rejected'], true)) return false;

        $stmt = self::pdo()->prepare(
            'UPDATE doctor_claim_requests
             SET status = :s, reviewed_by = :rb, reviewed_at = NOW(), reviewer_notes = :n
             WHERE id = :id'
        );
        return $stmt->execute([
            's'  => $status,
            'rb' => $reviewerId,
            'n'  => $notes,
            'id' => $requestId,
        ]);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private static function makeUniqueSlug(string $base, string $table, string $col): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim(strtolower($base))) ?: 'clinic';
        $slug = trim($slug, '-');
        if ($slug === '') $slug = 'clinic';
        $slug = substr($slug, 0, 60);

        $check = self::pdo()->prepare("SELECT 1 FROM $table WHERE $col = :v LIMIT 1");
        $candidate = $slug;
        $i = 1;
        while (true) {
            $check->execute(['v' => $candidate]);
            if (!$check->fetchColumn()) return $candidate;
            $i++;
            $candidate = $slug . '-' . $i;
            if ($i > 999) return $slug . '-' . substr((string) time(), -6);
        }
    }

    private static function pdo(): PDO
    {
        return \App\Core\Database::connection();
    }
}
