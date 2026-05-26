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

            // 3) Directory listing.
            //    - For 'claim' requests: link to the existing row.
            //    - For 'new_listing' requests: create a new row so the
            //      clinic appears on /find-a-doctor immediately.
            $directoryId = null;
            if (!empty($req['directory_doctor_id'])) {
                $directoryId = (int) $req['directory_doctor_id'];
                $db->prepare(
                    'UPDATE directory_doctors
                     SET is_claimed = 1, claimed_tenant_id = :tid
                     WHERE id = :did'
                )->execute([
                    'tid' => $tenantId,
                    'did' => $directoryId,
                ]);
            } elseif ($req['type'] === 'new_listing') {
                $directoryId = self::createDirectoryRow($db, $req, $tenantId);
            }

            // 4) Flip the tenant to "publicly listed". Trial signups that
            // come through this approval flow get directory visibility;
            // raw /register tenants stay is_directory_listed=0 until
            // they apply through this same queue.
            $db->prepare(
                'UPDATE tenants
                 SET is_directory_listed = 1, directory_doctor_id = :did
                 WHERE id = :tid'
            )->execute(['did' => $directoryId, 'tid' => $tenantId]);

            // 5) Mark the request approved.
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

    /**
     * Submit a listing request from inside the portal (logged-in tenant).
     * Skips phone OTP — the tenant is already authenticated. Skips
     * directory_doctor_id — we don't know if they have a Google listing,
     * so it's treated as a 'new_listing' the admin will manually merge
     * if a Google match shows up later.
     *
     * Returns the new request id, or null on failure.
     */
    public static function submitFromPortal(int $tenantId, array $tenant, array $input): ?int
    {
        // Prevent duplicate active requests from the same tenant — if they
        // already have one pending, just return that id instead of creating
        // another row.
        $db = self::pdo();
        $existing = $db->prepare(
            'SELECT id FROM doctor_claim_requests
             WHERE phone = :p AND status IN ("pending", "phone_verified")
             ORDER BY id DESC LIMIT 1'
        );
        $existing->execute(['p' => (string) ($tenant['phone'] ?? '')]);
        if ($id = $existing->fetchColumn()) {
            return (int) $id;
        }

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $ip = $ip ? substr(explode(',', (string) $ip)[0], 0, 45) : null;
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        $stmt = $db->prepare(
            'INSERT INTO doctor_claim_requests
                (type, full_name, phone, phone_verified_at, email,
                 clinic_name, city, state, specialty,
                 reg_number, reg_council, message,
                 status, source, ip, user_agent)
             VALUES
                ("new_listing", :name, :phone, NOW(), :email,
                 :clinic, :city, :state, :spec,
                 :reg, :council, :msg,
                 "phone_verified", :src, :ip, :ua)'
        );
        $stmt->execute([
            'name'    => trim((string) ($input['full_name']   ?? $tenant['name'] ?? 'Doctor')),
            'phone'   => (string) ($tenant['phone'] ?? ''),
            'email'   => $tenant['email'] ?? null,
            'clinic'  => trim((string) ($input['clinic_name'] ?? $tenant['name'] ?? '')),
            'city'    => trim((string) ($input['city']        ?? '')),
            'state'   => trim((string) ($input['state']       ?? '')) ?: null,
            'spec'    => trim((string) ($input['specialty']   ?? $tenant['specialty'] ?? 'gp')),
            'reg'     => trim((string) ($input['reg_number']  ?? '')) ?: null,
            'council' => trim((string) ($input['reg_council'] ?? '')) ?: null,
            'msg'     => trim((string) ($input['message']     ?? '')) ?: null,
            'src'     => 'portal_dashboard',
            'ip'      => $ip,
            'ua'      => $ua ?: null,
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Most recent request submitted by this tenant (any status). Used by
     * the get-listed page to show "you've already submitted, status: X"
     * instead of letting them submit twice.
     */
    public static function latestForTenantPhone(string $phone): ?array
    {
        if ($phone === '') return null;
        $stmt = self::pdo()->prepare(
            'SELECT * FROM doctor_claim_requests
             WHERE phone = :p
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['p' => $phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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

    /**
     * Create a brand-new directory_doctors row from a 'new_listing'
     * claim request. The clinic isn't on Google Maps, but the doctor
     * submitted enough info to make a public listing.
     *
     * place_id is the unique key. We synthesize one with a 'self_' prefix
     * so it never collides with a real Google place_id, and so a future
     * Google fetch can still merge if it discovers the same clinic.
     *
     * @return int|null  newly-created directory_doctors.id
     */
    private static function createDirectoryRow(PDO $db, array $req, int $tenantId): ?int
    {
        $placeId = 'self_' . substr(md5($tenantId . '|' . $req['phone'] . '|' . microtime(true)), 0, 24);

        // Map the submitted specialty (URL slug or DB key) to the canonical
        // DB value. Falls back to 'gp' if we can't recognize it.
        $specDb = self::resolveSpecialtyDbValue((string) ($req['specialty'] ?? ''));

        $stmt = $db->prepare(
            'INSERT INTO directory_doctors
                (place_id, source, is_claimed, claimed_tenant_id,
                 name, doctor_name, specialty, country, state, city,
                 phone, status, is_active, fetched_at, refreshed_at)
             VALUES
                (:pid, "self", 1, :tid,
                 :name, :doctor_name, :spec, "IN", :state, :city,
                 :phone, "OPERATIONAL", 1, NOW(), NOW())'
        );
        $stmt->execute([
            'pid'         => $placeId,
            'tid'         => $tenantId,
            'name'        => $req['clinic_name'] ?? $req['full_name'],
            'doctor_name' => $req['full_name'],
            'spec'        => $specDb,
            'state'       => $req['state'] ?: null,
            'city'        => $req['city'] ?: '',
            'phone'       => $req['phone'],
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Convert whatever the user picked in the wizard to the canonical DB
     * specialty value. The wizard sends DB keys directly today, but be
     * defensive in case a URL slug ('cardiologist') sneaks in.
     */
    private static function resolveSpecialtyDbValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') return 'gp';

        // Quick whitelist of all DB values currently in use.
        static $dbValues = [
            'gp','family_medicine','eye','derma','cosmetology','trichology',
            'cardio','psychiatrist','gastro','hepatology','ent','gyno',
            'fertility','neuro','urologist','andrology','sexology','peds',
            'ortho','sports_medicine','rheumatology','pain_management',
            'oncology','hematology','pulmonology','allergy','nephrology',
            'diabetology','endocrinology','neurosurgery','spine','gi_surgery',
            'general_surgery','plastic_surgery','bariatric','vascular',
            'radiology','critical_care','dental','prosthodontist',
            'orthodontist','pediatric_dentist','endodontist','implantologist',
            'ayurveda','homeo','siddha','unani','naturopathy','acupuncturist',
            'physio','psychologist','audiologist','speech','dietitian',
        ];
        if (in_array($value, $dbValues, true)) return $value;

        // Try URL-slug → DB mapping (e.g. 'cardiologist' → 'cardio').
        // We can't include the marketing-site seo_slugs.php here, so do a
        // tiny inline map for the most common ones.
        $slugToDb = [
            'cardiologist'      => 'cardio',
            'ophthalmologist'   => 'eye',
            'dermatologist'     => 'derma',
            'pediatrician'      => 'peds',
            'orthopedic'        => 'ortho',
            'gynecologist'      => 'gyno',
            'neurologist'       => 'neuro',
            'oncologist'        => 'oncology',
            'pulmonologist'     => 'pulmonology',
            'nephrologist'     => 'nephrology',
            'neurosurgeon'      => 'neurosurgery',
            'gastroenterologist'=> 'gastro',
            'ent-specialist'    => 'ent',
            'general-physician' => 'gp',
            'plastic-surgeon'   => 'plastic_surgery',
            'sexologist'        => 'sexology',
            'diabetologist'     => 'diabetology',
            'endocrinologist'   => 'endocrinology',
            'dentist'           => 'dental',
            'homeopathy'        => 'homeo',
        ];
        return $slugToDb[$value] ?? 'gp';
    }

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
