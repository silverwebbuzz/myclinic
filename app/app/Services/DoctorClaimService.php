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
     * Approve a request. Creates clinic + doctor user (or reuses existing),
     * links the directory listing if it was a claim.
     *
     * On success, `notifications` holds one human-readable line per channel
     * (email / SMS / WhatsApp) describing whether it fired and why not — so
     * the admin sees, e.g. "WhatsApp: skipped (not configured yet)".
     *
     * @return array{ok: true, user_id: int, notifications: list<string>}|array{ok: false, error: string, step?: string}
     */
    public static function approve(int $requestId, int $reviewerId, ?string $notes = null): array
    {
        $req = self::find($requestId);
        if ($req === null) {
            return ['ok' => false, 'error' => 'Claim request #' . $requestId . ' was not found.', 'step' => 'load'];
        }
        if (in_array($req['status'], ['approved', 'rejected'], true)) {
            return [
                'ok'    => false,
                'error' => 'This request is already marked as "' . $req['status'] . '". Refresh the page.',
                'step'  => 'status',
            ];
        }

        $db = self::pdo();
        $schemaError = self::preflightApprovalSchema($db);
        if ($schemaError !== null) {
            return ['ok' => false, 'error' => $schemaError, 'step' => 'schema'];
        }

        $db->beginTransaction();
        try {
            // Reuse an existing portal account when the doctor already signed up
            // (e.g. /register trial, then "get listed" from the dashboard). Only
            // marketing-site applicants with no account get a fresh tenant + user.
            $existing = self::findExistingDoctorAccount($db, $req);
            if ($existing !== null) {
                $tenantId = $existing['tenant_id'];
                $userId   = $existing['user_id'];
            } else {
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
                $userEmail = self::resolveDoctorUserEmail($db, $req, $tenantId);
                $unusableHash = '!disabled:' . bin2hex(random_bytes(8));   // never matches a valid bcrypt
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
                    'email' => $userEmail,
                    'phone' => DoctorOtpService::normalizePhone((string) ($req['phone'] ?? '')) ?: $req['phone'],
                    'pwd'   => $unusableHash,
                    'spec'  => $req['specialty'],
                    'qual'  => trim(($req['reg_council'] ? $req['reg_council'] . ' ' : '') . ($req['reg_number'] ?: '')) ?: null,
                ]);
                $userId = (int) $db->lastInsertId();
            }

            // 3) Directory listing.
            //    - For 'claim' requests: link to the existing row.
            //    - For 'new_listing' requests: create a new row so the
            //      clinic appears on /find-a-doctor immediately.
            $directoryId = null;
            $tenantDir = $db->prepare(
                'SELECT directory_doctor_id FROM tenants WHERE id = :id LIMIT 1'
            );
            $tenantDir->execute(['id' => $tenantId]);
            $existingDirId = $tenantDir->fetchColumn();
            if (!empty($existingDirId)) {
                $directoryId = (int) $existingDirId;
            } elseif (!empty($req['directory_doctor_id'])) {
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

            // Portal bootstrap: trial plan, modules, specialty config, skip
            // onboarding wizard — claim approval already collected clinic info.
            self::bootstrapApprovedTenant($tenantId, $req);

            // Notify the doctor that they're approved across every available
            // channel — best-effort, AFTER the commit so a notification problem
            // never rolls back the account. Each channel is independently
            // guarded; the returned lines tell the admin what fired and what
            // was skipped (e.g. SMS/WhatsApp not configured yet).
            $notifications = self::notifyApproved($req, $tenantId);

            return ['ok' => true, 'user_id' => $userId, 'notifications' => $notifications];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $human = self::humanizeApprovalError($e);
            error_log('[DoctorClaimService::approve] request=' . $requestId . ' ' . $e->getMessage());
            return ['ok' => false, 'error' => $human, 'step' => 'database'];
        }
    }

    /**
     * Ready an approved doctor's clinic for day-one use. Without this, the
     * tenant stays at onboarding_step=1 and every login lands on clinic setup.
     *
     * @param array<string, mixed> $req
     */
    private static function bootstrapApprovedTenant(int $tenantId, array $req): void
    {
        try {
            $spec = self::resolveSpecialtyDbValue((string) ($req['specialty'] ?? ''));

            \App\Core\QueryBuilder::table('tenants')->where('id', '=', $tenantId)->update([
                'specialty' => $spec,
            ]);

            $tenant = \App\Core\QueryBuilder::table('tenants')->where('id', '=', $tenantId)->first();
            if ($tenant === null) {
                return;
            }

            $config = \App\Core\QueryBuilder::table('specialty_configs')
                ->where('clinic_id', '=', $tenantId)
                ->first();
            if ($config === null) {
                $slug = (string) ($tenant['slug'] ?? 'clinic');
                \App\Core\QueryBuilder::table('specialty_configs')->insert([
                    'clinic_id'     => $tenantId,
                    'uhid_prefix'   => strtoupper(substr(preg_replace('/[^a-z]/', '', strtolower($slug)), 0, 6) ?: 'MC'),
                    'working_hours' => json_encode(OnboardingService::defaultWorkingHours()),
                ]);
            }

            PlanService::applyPlanToTenant($tenantId, 'standard', true);
            OnboardingService::complete($tenantId);
        } catch (\Throwable $e) {
            error_log('[DoctorClaimService::bootstrapApprovedTenant] tenant=' . $tenantId . ' ' . $e->getMessage());
        }
    }

    /**
     * Approval notification to the doctor, fanned out across every channel we
     * have a destination for: email, SMS, and WhatsApp. Login is passwordless
     * (phone OTP), so every channel points them to the doctor login page to
     * sign in with their verified number — no password is generated or sent.
     *
     * Design per requirement:
     *   - SMS/WhatsApp may not be live yet. When their provider isn't
     *     configured we DON'T pretend to send — we skip with a clear reason.
     *   - Every channel is independently guarded; a failure in one never
     *     aborts the others and never throws out of here (called post-commit).
     *   - Returns one human line per channel for the admin to see.
     *
     * @param array<string, mixed> $req the claim request row
     * @return list<string>
     */
    private static function notifyApproved(array $req, int $tenantId): array
    {
        $base      = rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/');
        $loginUrl  = $base . '/doctor/login';
        $email     = trim((string) ($req['email'] ?? ''));
        $phone     = trim((string) ($req['phone'] ?? ''));
        $name      = (string) ($req['full_name'] ?? 'Doctor');
        $clinic    = (string) ($req['clinic_name'] ?? '');

        // Shared payload for SMS/WhatsApp template rendering.
        $payload = [
            'doctor_name' => $name,
            'clinic_name' => $clinic,
            'phone'       => $phone,
            'login_url'   => $loginUrl,
        ];

        // Plain text used for SMS and as the WhatsApp body. Self-contained so
        // it reads sensibly even with no wa_templates row registered.
        $plain = 'Hello ' . $name . ', your eClinicPro clinic panel is now active. '
            . 'Sign in at ' . $loginUrl . ' with your phone ' . $phone
            . ' — no password needed, we send a one-time SMS code.';

        $lines = [];
        $lines[] = self::notifyByEmail($email, $tenantId, $payload);
        $lines[] = self::notifyBySms($phone, $plain);
        $lines[] = self::notifyByWhatsApp($phone, $payload, $plain);

        return $lines;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function notifyByEmail(string $email, int $tenantId, array $payload): string
    {
        if ($email === '') {
            return 'Email: skipped (no email address on file for this request).';
        }
        try {
            MailService::send($email, 'doctor_approved', $payload, $tenantId);
            return 'Email: sent to ' . $email . '.';
        } catch (\Throwable $e) {
            error_log('[DoctorClaimService::notifyByEmail] ' . $e->getMessage());
            return 'Email: NOT sent — ' . $e->getMessage();
        }
    }

    private static function notifyBySms(string $phone, string $plain): string
    {
        if ($phone === '') {
            return 'SMS: skipped (no phone number on file).';
        }
        if (!\App\Support\MessagingSettings::smsConfigured()) {
            return 'SMS: not sent — SMS provider is not configured/started yet.';
        }
        try {
            $r = SmsService::send($phone, $plain);
            return $r['ok']
                ? 'SMS: sent to ' . $phone . '.'
                : 'SMS: NOT sent — ' . ($r['message'] ?? 'unknown error');
        } catch (\Throwable $e) {
            error_log('[DoctorClaimService::notifyBySms] ' . $e->getMessage());
            return 'SMS: NOT sent — ' . $e->getMessage();
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function notifyByWhatsApp(string $phone, array $payload, string $plain): string
    {
        if ($phone === '') {
            return 'WhatsApp: skipped (no phone number on file).';
        }
        if (!\App\Support\MessagingSettings::whatsappConfigured()) {
            return 'WhatsApp: not sent — WhatsApp is not configured/started yet.';
        }
        try {
            // WhatsAppService renders via WaTemplateService; pass the plain body
            // through the payload so a missing wa_templates row still has text.
            $r = WhatsAppService::send($phone, 'doctor_approved', $payload + ['body' => $plain]);
            return ($r['ok'] ?? false)
                ? 'WhatsApp: sent to ' . $phone . '.'
                : 'WhatsApp: NOT sent — ' . ($r['message'] ?? 'unknown error');
        } catch (\Throwable $e) {
            error_log('[DoctorClaimService::notifyByWhatsApp] ' . $e->getMessage());
            return 'WhatsApp: NOT sent — ' . $e->getMessage();
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

        // Carry over the rest of the submitted contact + about info so the
        // public profile page isn't half-empty after approval:
        //   - intl_phone: canonical (+CC) form of the verified phone.
        //   - address:    no dedicated address field is collected today, so
        //                 fall back to "City, State" (same as the tenant row).
        //   - bio:        the doctor's free-text "message" from the request —
        //                 shown as the "About" section on the front-end profile.
        $intlPhone = DoctorOtpService::normalizePhone((string) ($req['phone'] ?? '')) ?: null;
        $address   = trim(((string) ($req['city'] ?? '')) . ($req['state'] ? ', ' . $req['state'] : '')) ?: null;
        $bio       = trim((string) ($req['message'] ?? '')) ?: null;

        $stmt = $db->prepare(
            'INSERT INTO directory_doctors
                (place_id, source, is_claimed, claimed_tenant_id,
                 name, doctor_name, specialty, country, state, city,
                 address, phone, intl_phone, bio,
                 status, is_active, fetched_at, refreshed_at)
             VALUES
                (:pid, "self", 1, :tid,
                 :name, :doctor_name, :spec, "IN", :state, :city,
                 :address, :phone, :intl_phone, :bio,
                 "OPERATIONAL", 1, NOW(), NOW())'
        );
        $stmt->execute([
            'pid'         => $placeId,
            'tid'         => $tenantId,
            'name'        => $req['clinic_name'] ?? $req['full_name'],
            'doctor_name' => $req['full_name'],
            'spec'        => $specDb,
            'state'       => $req['state'] ?: null,
            'city'        => $req['city'] ?: '',
            'address'     => $address,
            'phone'       => $req['phone'],
            'intl_phone'  => $intlPhone,
            'bio'         => $bio,
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
            'ayurveda','homeopathy','siddha','unani','naturopathy','acupuncturist',
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

    /**
     * If the doctor already has a portal account (same verified phone or email),
     * return their tenant + user ids so approval only adds the directory row.
     *
     * @return array{tenant_id: int, user_id: int}|null
     */
    private static function findExistingDoctorAccount(PDO $db, array $req): ?array
    {
        foreach (self::phoneLookupVariants((string) ($req['phone'] ?? '')) as $variant) {
            $stmt = $db->prepare(
                'SELECT id, clinic_id, role, phone FROM users
                 WHERE phone = :p AND is_active = 1
                 ORDER BY is_owner DESC, id ASC
                 LIMIT 1'
            );
            $stmt->execute(['p' => $variant]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return self::finalizeExistingAccount($db, $row, $req);
            }
        }

        $email = trim((string) ($req['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $db->prepare(
                'SELECT id, clinic_id, role, phone FROM users
                 WHERE email = :e AND is_active = 1
                 ORDER BY is_owner DESC, id ASC
                 LIMIT 1'
            );
            $stmt->execute(['e' => $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return self::finalizeExistingAccount($db, $row, $req);
            }

            // Owner may have registered with clinic email on the tenant row only.
            $tenant = $db->prepare(
                'SELECT id FROM tenants WHERE email = :e AND is_active = 1 LIMIT 1'
            );
            $tenant->execute(['e' => $email]);
            $tenantId = $tenant->fetchColumn();
            if ($tenantId) {
                $owner = $db->prepare(
                    'SELECT id, clinic_id, role, phone FROM users
                     WHERE clinic_id = :cid AND is_owner = 1 AND is_active = 1
                     LIMIT 1'
                );
                $owner->execute(['cid' => (int) $tenantId]);
                $row = $owner->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return self::finalizeExistingAccount($db, $row, $req);
                }
            }
        }

        return null;
    }

    /**
     * Ensure an existing portal user can log in as a doctor after approval.
     *
     * @param array<string, mixed> $row
     * @return array{tenant_id: int, user_id: int}
     */
    private static function finalizeExistingAccount(PDO $db, array $row, array $req): array
    {
        $userId   = (int) $row['id'];
        $tenantId = (int) $row['clinic_id'];
        $phone    = DoctorOtpService::normalizePhone((string) ($req['phone'] ?? '')) ?: ($row['phone'] ?? null);

        $needsUpdate = ($row['role'] ?? '') !== 'doctor'
            || ($phone !== null && ($row['phone'] ?? '') !== $phone);
        if ($needsUpdate) {
            $db->prepare(
                'UPDATE users
                 SET role = "doctor", phone = COALESCE(:phone, phone), is_owner = 1
                 WHERE id = :id'
            )->execute([
                'phone' => $phone,
                'id'    => $userId,
            ]);
        }

        return ['tenant_id' => $tenantId, 'user_id' => $userId];
    }

    /** @return list<string> */
    private static function phoneLookupVariants(string $raw): array
    {
        $normalized = DoctorOtpService::normalizePhone($raw);
        $digits     = preg_replace('/\D/', '', $normalized) ?? '';
        $variants   = array_filter([$raw, $normalized, $digits]);
        if (strlen($digits) === 10) {
            $variants[] = '+91' . $digits;
            $variants[] = '91' . $digits;
            $variants[] = '0' . $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $variants[] = '+' . $digits;
        }

        return array_values(array_unique($variants));
    }

    private static function resolveDoctorUserEmail(PDO $db, array $req, int $tenantId): string
    {
        $email = trim((string) ($req['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'doctor+' . $tenantId . '@eclinicpro.placeholder';
        }

        $stmt = $db->prepare('SELECT 1 FROM users WHERE email = :e LIMIT 1');
        $stmt->execute(['e' => $email]);
        if ($stmt->fetchColumn()) {
            return 'doctor+' . $tenantId . '@eclinicpro.placeholder';
        }

        return $email;
    }

    private static function preflightApprovalSchema(PDO $db): ?string
    {
        $missing = [];
        foreach (['is_directory_listed', 'directory_doctor_id'] as $col) {
            $stmt = $db->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = "tenants"
                   AND COLUMN_NAME = :col
                 LIMIT 1'
            );
            $stmt->execute(['col' => $col]);
            if (!$stmt->fetchColumn()) {
                $missing[] = 'tenants.' . $col;
            }
        }
        if ($missing !== []) {
            return 'Database migration not applied. Missing: ' . implode(', ', $missing)
                . '. Run app/database/migrations/028_tenant_directory_listing.sql in phpMyAdmin.';
        }

        try {
            $db->query('SELECT 1 FROM directory_doctors LIMIT 1');
        } catch (\Throwable) {
            return 'Table directory_doctors does not exist on this database. Run fetch_doctor/migration.sql first.';
        }

        return null;
    }

    private static function humanizeApprovalError(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'uq_email')) {
            return 'Duplicate email: ' . $msg
                . ' — this doctor may already have a portal account with that email.';
        }
        if (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'uq_place')) {
            return 'Duplicate directory listing (place_id already exists): ' . $msg;
        }
        if (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'uq_slug')) {
            return 'Clinic slug collision: ' . $msg;
        }
        if (str_contains($msg, 'Unknown column') && str_contains($msg, 'is_directory_listed')) {
            return 'Missing column tenants.is_directory_listed — run migration 028_tenant_directory_listing.sql';
        }
        if (str_contains($msg, 'directory_doctors') && str_contains($msg, "doesn't exist")) {
            return 'Table directory_doctors is missing — run fetch_doctor/migration.sql';
        }
        if (str_contains($msg, 'Data truncated') || str_contains($msg, 'Incorrect enum value')) {
            if (str_contains($msg, 'source')) {
                return 'directory_doctors.source must allow value "self". Update the ENUM on directory_doctors.source.';
            }
        }
        if (str_contains($msg, 'Data too long')) {
            return 'A field value is too long for the database column: ' . $msg;
        }

        return $msg;
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
