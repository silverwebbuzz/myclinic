<?php

declare(strict_types=1);

namespace App\Services\Wellness;

use App\Core\Database;
use App\Services\MailService;
use PDO;

/**
 * Wellness Hub — Health Creators (Phase 1). Plan: document/wellness-hub-plan.md
 *
 * A creator is always a patient identity underneath (same OTP login, same
 * ecp_pid cookie). Permissions are granted PER content type by the admin.
 *
 * Bridge to Health Tips: recipes / exercises / quick tips are still written in
 * the existing editor (eclinicpro.com/my-health-tips), which reads
 * health_tip_contributors. syncLegacy() keeps that row in step with the
 * creator (profile, status, terms), so the editor, public pages and the
 * Flutter app keep working unchanged until Phase 3 moves them.
 *
 * Every creator-side mutator takes the creator row from RequestContext (set by
 * CreatorAuthMiddleware) — never an id from the URL or form.
 */
final class CreatorService
{
    public const TERMS_VERSION = '2026-11-27';

    public const TYPES = [
        'individual' => 'Individual (coach, dietitian, yoga teacher, creator)',
        'doctor'     => 'Doctor',
        'clinic'     => 'Clinic / Hospital',
        'ngo'        => 'NGO / Trust',
        'government' => 'Government body',
        'company'    => 'Company / Event organiser',
    ];

    public const TYPE_SHORT = [
        'individual' => 'Individual', 'doctor' => 'Doctor', 'clinic' => 'Clinic / Hospital',
        'ngo' => 'NGO / Trust', 'government' => 'Government', 'company' => 'Company', 'platform' => 'eClinicPro',
    ];

    /** content_type => [label, what we usually ask for as proof] */
    public const CONTENT = [
        'recipe'    => ['Recipes', 'Your profile and a social link or sample of your work.'],
        'exercise'  => ['Exercises', 'A fitness / yoga certification, or your social profile.'],
        'tip'       => ['Quick tips', 'Your profile and qualification.'],
        'video'     => ['Videos (YouTube / Instagram reels)', 'The Instagram or YouTube channel you own.'],
        'free_camp' => ['Free health camps', 'Doctor registration, or hospital / NGO registration certificate.'],
        'event'     => ['Events (expos, workshops, conferences)', 'Company GST or organisation registration.'],
    ];

    /** Types the existing Health Tips editor handles. */
    public const TIP_TYPES = ['recipe', 'exercise', 'tip', 'video'];

    /** Not built yet — shown as "Opens soon" in the Studio. */
    public const UPCOMING_TYPES = [];

    public const ORG_TYPES = ['clinic', 'ngo', 'government', 'company'];

    public const COUNCILS = [
        'NMC (National Medical Commission)',
        'State Medical Council',
        'Dental Council of India / State Dental Council',
        'AYUSH — Ayurveda / Siddha / Unani (NCISM / State board)',
        'AYUSH — Homoeopathy (NCH / State board)',
        'Other',
    ];

    public const DOC_TYPES = [
        'registration_certificate' => 'Registration certificate',
        'degree_certificate'       => 'Degree certificate',
        'photo_id'                 => 'Government photo ID',
        'gst_certificate'          => 'GST certificate',
        'certification'            => 'Certification (fitness / yoga / nutrition)',
        'other'                    => 'Other supporting document',
    ];

    private const DOC_MIMES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const DOC_MAX = 5 * 1024 * 1024;
    private const PHOTO_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const PHOTO_MAX = 3 * 1024 * 1024;

    // -----------------------------------------------------------------
    // Lookups
    // -----------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public static function forIdentity(int $identityId): ?array
    {
        if ($identityId <= 0) {
            return null;
        }
        try {
            $st = Database::connection()->prepare('SELECT * FROM wh_creators WHERE identity_id = :i LIMIT 1');
            $st->execute(['i' => $identityId]);

            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            return null;   // patch not run yet
        }
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM wh_creators WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<string, array<string,mixed>> keyed by content_type */
    public static function permissions(int $creatorId): array
    {
        $st = Database::connection()->prepare('SELECT * FROM wh_creator_permissions WHERE creator_id = :c');
        $st->execute(['c' => $creatorId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['content_type']] = $row;
        }

        return $out;
    }

    /** @return list<string> approved content types */
    public static function approvedTypes(int $creatorId): array
    {
        return array_keys(array_filter(self::permissions($creatorId), static fn ($p) => $p['status'] === 'approved'));
    }

    /** @return list<array<string,mixed>> */
    public static function documents(int $creatorId): array
    {
        $st = Database::connection()->prepare('SELECT * FROM wh_creator_documents WHERE creator_id = :c ORDER BY id DESC');
        $st->execute(['c' => $creatorId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** 'doctor' | 'organisation' | null — which badge this creator type can apply for. */
    public static function badgeKind(array $creator): ?string
    {
        $type = (string) ($creator['creator_type'] ?? '');
        if ($type === 'doctor') {
            return 'doctor';
        }

        return in_array($type, self::ORG_TYPES, true) ? 'organisation' : null;
    }

    public static function badgeLabel(array $creator): ?string
    {
        if (($creator['verify_status'] ?? '') !== 'verified') {
            return null;
        }

        return self::badgeKind($creator) === 'doctor' ? 'Verified Doctor' : 'Verified Organisation';
    }

    /** Health Tips posts by status, for the dashboard. @return array<string,int> */
    public static function tipCounts(array $creator): array
    {
        $cid = (int) ($creator['legacy_contributor_id'] ?? 0);
        if ($cid <= 0) {
            return [];
        }
        try {
            $st = Database::connection()->prepare(
                'SELECT status, COUNT(*) n FROM health_tips WHERE contributor_id = :c AND is_deleted = 0 GROUP BY status'
            );
            $st->execute(['c' => $cid]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[(string) $r['status']] = (int) $r['n'];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> the creator's Health Tips posts */
    public static function tips(array $creator): array
    {
        $cid = (int) ($creator['legacy_contributor_id'] ?? 0);
        if ($cid <= 0) {
            return [];
        }
        try {
            $st = Database::connection()->prepare(
                'SELECT id, type, title, slug, status, review_note, submitted_at, published_at, view_count, updated_at
                 FROM health_tips WHERE contributor_id = :c AND is_deleted = 0 ORDER BY updated_at DESC'
            );
            $st->execute(['c' => $cid]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    // -----------------------------------------------------------------
    // Join (creator signup)
    // -----------------------------------------------------------------

    /**
     * @param array<string,mixed> $identity patient_identities row
     * @param array<string,mixed> $in       form input
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function join(array $identity, array $in): array
    {
        $identityId = (int) $identity['id'];
        if (self::forIdentity($identityId) !== null) {
            return ['ok' => false, 'error' => 'You are already a Health Creator.'];
        }

        $type = (string) ($in['creator_type'] ?? '');
        if (!isset(self::TYPES[$type])) {
            return ['ok' => false, 'error' => 'Please choose who you are.'];
        }
        $fields = self::cleanProfile($in, ['creator_type' => $type, 'verify_status' => 'none']);
        if (isset($fields['error'])) {
            return ['ok' => false, 'error' => $fields['error']];
        }
        if (($fields['email'] ?? null) === null) {
            return ['ok' => false, 'error' => 'Please add your email — we send approval updates there.'];
        }
        $want = array_values(array_intersect(array_keys(self::CONTENT), (array) ($in['content_types'] ?? [])));
        if (!$want) {
            return ['ok' => false, 'error' => 'Choose at least one thing you want to post.'];
        }
        if (empty($in['accept_terms'])) {
            return ['ok' => false, 'error' => 'Please read and accept the Health Creator terms.'];
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO wh_creators (identity_id, slug, display_name, creator_type, headline, bio, email, city, state,
                                          org_name, org_reg_no, gstin, instagram_handle, youtube_url, website_url,
                                          terms_accepted_at, terms_version)
                 VALUES (:iid, :slug, :name, :type, :headline, :bio, :email, :city, :state,
                         :org, :orgreg, :gstin, :insta, :yt, :web, NOW(), :tv)'
            )->execute([
                'iid' => $identityId,
                'slug' => self::uniqueSlug((string) $fields['display_name']),
                'name' => $fields['display_name'],
                'type' => $type,
                'headline' => $fields['headline'],
                'bio' => $fields['bio'],
                'email' => $fields['email'],
                'city' => $fields['city'],
                'state' => $fields['state'],
                'org' => $fields['org_name'],
                'orgreg' => $fields['org_reg_no'],
                'gstin' => $fields['gstin'],
                'insta' => $fields['instagram_handle'],
                'yt' => $fields['youtube_url'],
                'web' => $fields['website_url'],
                'tv' => self::TERMS_VERSION,
            ]);
            $creatorId = (int) $db->lastInsertId();

            $note = self::clean((string) ($in['applicant_note'] ?? ''), 600);
            $ins = $db->prepare(
                "INSERT INTO wh_creator_permissions (creator_id, content_type, status, applicant_note, applied_at)
                 VALUES (:c, :t, 'applied', :n, NOW())"
            );
            foreach ($want as $t) {
                $ins->execute(['c' => $creatorId, 't' => $t, 'n' => $note]);
            }

            // Fill the patient's own email if they had none (they can change it in My Profile).
            if (trim((string) ($identity['email'] ?? '')) === '') {
                $db->prepare('UPDATE patient_identities SET email = :e WHERE id = :id AND (email IS NULL OR email = "")')
                   ->execute(['e' => $fields['email'], 'id' => $identityId]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[wellness join] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not save your application. Please try again.'];
        }

        self::log($creatorId, 'joined', 'Applied for: ' . implode(', ', $want), null);
        self::notifyAdmin(
            'New Health Creator application',
            [
                $fields['display_name'] . ' (' . self::TYPE_SHORT[$type] . ') wants to post: '
                    . implode(', ', array_map(static fn ($t) => self::CONTENT[$t][0], $want)) . '.',
                'Phone: ' . ($identity['phone'] ?? '') . ' · Email: ' . $fields['email'],
            ],
            '/admin/wellness/creators/' . $creatorId,
        );

        return ['ok' => true, 'id' => $creatorId];
    }

    // -----------------------------------------------------------------
    // Profile
    // -----------------------------------------------------------------

    /**
     * @param array<string,mixed>      $creator
     * @param array<string,mixed>      $in
     * @param array<string,mixed>|null $avatar $_FILES entry
     * @return array{ok:bool,error?:string}
     */
    public static function saveProfile(array $creator, array $in, ?array $avatar): array
    {
        $type = (string) ($in['creator_type'] ?? $creator['creator_type']);
        if (!isset(self::TYPES[$type])) {
            $type = (string) $creator['creator_type'];
        }
        // A badge belongs to the type it was checked for.
        if ($type !== $creator['creator_type'] && in_array($creator['verify_status'], ['pending', 'verified'], true)) {
            return ['ok' => false, 'error' => 'You can’t change your creator type while your badge is ' . ($creator['verify_status'] === 'verified' ? 'verified' : 'being checked') . '. Contact us if it is wrong.'];
        }
        $fields = self::cleanProfile($in, ['creator_type' => $type, 'verify_status' => $creator['verify_status']]);
        if (isset($fields['error'])) {
            return ['ok' => false, 'error' => $fields['error']];
        }
        if (($fields['email'] ?? null) === null) {
            return ['ok' => false, 'error' => 'Please add your email — we send approval updates there.'];
        }

        $avatarPath = null;
        if ($avatar && ($avatar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $saved = self::storePhoto((int) $creator['id'], $avatar);
            if (!$saved['ok']) {
                return $saved;
            }
            $avatarPath = $saved['path'];
        }

        $sql = 'UPDATE wh_creators SET display_name = :name, creator_type = :type, headline = :headline, bio = :bio,
                    email = :email, city = :city, state = :state, org_name = :org, org_reg_no = :orgreg, gstin = :gstin,
                    instagram_handle = :insta, youtube_url = :yt, website_url = :web';
        $bind = [
            'name' => $fields['display_name'], 'type' => $type, 'headline' => $fields['headline'], 'bio' => $fields['bio'],
            'email' => $fields['email'], 'city' => $fields['city'], 'state' => $fields['state'],
            'org' => $fields['org_name'], 'orgreg' => $fields['org_reg_no'], 'gstin' => $fields['gstin'],
            'insta' => $fields['instagram_handle'], 'yt' => $fields['youtube_url'], 'web' => $fields['website_url'],
            'id' => (int) $creator['id'],
        ];
        if ($avatarPath !== null) {
            $sql .= ', avatar_path = :avatar';
            $bind['avatar'] = $avatarPath;
        }
        if ($type !== $creator['creator_type'] && in_array($creator['verify_status'], ['rejected', 'revoked'], true)) {
            $sql .= ", verify_status = 'none'";   // new type → fresh badge application
        }
        Database::connection()->prepare($sql . ' WHERE id = :id')->execute($bind);

        if ($avatarPath !== null && !empty($creator['avatar_path']) && $creator['avatar_path'] !== $avatarPath) {
            self::unlinkPhotoIfUnused((string) $creator['avatar_path']);
        }
        self::syncLegacy((int) $creator['id']);

        return ['ok' => true];
    }

    /**
     * Validates the shared profile fields. Returns the clean values, or ['error' => …].
     *
     * @param array<string,mixed> $in
     * @param array{creator_type:string,verify_status:string} $ctx
     * @return array<string,mixed>
     */
    private static function cleanProfile(array $in, array $ctx): array
    {
        $name = self::clean((string) ($in['display_name'] ?? ''), 120);
        if ($name === null || mb_strlen($name) < 2) {
            return ['error' => 'Please enter the name readers will see.'];
        }
        // "Dr." only for doctors whose registration we have verified.
        if (preg_match('/^\s*dr\b\.?/iu', $name) && !($ctx['creator_type'] === 'doctor' && $ctx['verify_status'] === 'verified')) {
            return ['error' => 'You can use “Dr.” in your name once your Verified Doctor badge is approved. Please remove it for now.'];
        }

        $email = trim((string) ($in['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Please enter a valid email address.'];
        }

        $insta = trim((string) ($in['instagram_handle'] ?? ''));
        $insta = rtrim(ltrim(preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $insta) ?? $insta, '@'), '/');
        if ($insta !== '' && !preg_match('/^[A-Za-z0-9._]{1,30}$/', $insta)) {
            return ['error' => 'Instagram handle can only have letters, numbers, dots and underscores.'];
        }
        $youtube = trim((string) ($in['youtube_url'] ?? ''));
        if ($youtube !== '' && !preg_match('#^https://(www\.|m\.)?(youtube\.com|youtu\.be)/#i', $youtube)) {
            return ['error' => 'YouTube link must start with https://youtube.com/ or https://youtu.be/'];
        }
        $website = trim((string) ($in['website_url'] ?? ''));
        if ($website !== '' && (!filter_var($website, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $website))) {
            return ['error' => 'Website must be a full link starting with https://'];
        }
        $gstin = strtoupper(preg_replace('/\s+/', '', (string) ($in['gstin'] ?? '')) ?? '');
        if ($gstin !== '' && !preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/', $gstin)) {
            return ['error' => 'GSTIN should be 15 characters, like 24ABCDE1234F1Z5.'];
        }

        $isOrg = in_array($ctx['creator_type'], self::ORG_TYPES, true);
        $org = $isOrg ? self::clean((string) ($in['org_name'] ?? ''), 160) : null;
        if ($isOrg && $org === null) {
            return ['error' => 'Please enter the organisation’s name.'];
        }

        return [
            'display_name' => $name,
            'headline' => self::clean((string) ($in['headline'] ?? ''), 120),
            'bio' => self::clean((string) ($in['bio'] ?? ''), 600),
            'email' => $email !== '' ? mb_substr(strtolower($email), 0, 160) : null,
            'city' => self::clean((string) ($in['city'] ?? ''), 80),
            'state' => self::clean((string) ($in['state'] ?? ''), 80),
            'org_name' => $org,
            'org_reg_no' => $isOrg ? self::clean((string) ($in['org_reg_no'] ?? ''), 80) : null,
            'gstin' => $gstin !== '' ? $gstin : null,
            'instagram_handle' => $insta !== '' ? $insta : null,
            'youtube_url' => $youtube !== '' ? mb_substr($youtube, 0, 255) : null,
            'website_url' => $website !== '' ? mb_substr($website, 0, 255) : null,
        ];
    }

    // -----------------------------------------------------------------
    // Permissions (creator applies)
    // -----------------------------------------------------------------

    /**
     * Apply (or re-apply after a rejection) for one content type, with optional proof files.
     *
     * @param array<string,mixed> $files $_FILES['proof'] (multi)
     * @return array{ok:bool,error?:string}
     */
    public static function applyPermission(array $creator, string $type, string $note, array $files, string $docType = 'other'): array
    {
        if (!isset(self::CONTENT[$type])) {
            return ['ok' => false, 'error' => 'Choose what you want to post.'];
        }
        $existing = self::permissions((int) $creator['id'])[$type] ?? null;
        if ($existing && in_array($existing['status'], ['applied', 'approved'], true)) {
            return ['ok' => false, 'error' => $existing['status'] === 'approved' ? 'You are already approved for this.' : 'This application is already waiting for review.'];
        }
        if ($existing && $existing['status'] === 'suspended') {
            return ['ok' => false, 'error' => 'This permission was paused by our team. Please contact us.'];
        }

        $uploads = self::normalizeFiles($files);
        if (count($uploads) > 3) {
            return ['ok' => false, 'error' => 'Upload up to 3 files.'];
        }
        foreach ($uploads as $f) {
            $saved = self::storeDocument((int) $creator['id'], 'permission', $type, $docType, $f);
            if (!$saved['ok']) {
                return $saved;
            }
        }

        $clean = self::clean($note, 600);
        Database::connection()->prepare(
            "INSERT INTO wh_creator_permissions (creator_id, content_type, status, applicant_note, applied_at)
             VALUES (:c, :t, 'applied', :n, NOW())
             ON DUPLICATE KEY UPDATE status = 'applied', applicant_note = VALUES(applicant_note), applied_at = NOW(),
                                     review_note = NULL, reviewed_at = NULL, reviewed_by = NULL"
        )->execute(['c' => (int) $creator['id'], 't' => $type, 'n' => $clean]);

        self::log((int) $creator['id'], 'permission_applied', self::CONTENT[$type][0], null);
        self::notifyAdmin(
            'Health Creator wants to post ' . strtolower(self::CONTENT[$type][0]),
            [$creator['display_name'] . ' applied for: ' . self::CONTENT[$type][0] . '.' . ($uploads ? ' ' . count($uploads) . ' proof file(s) attached.' : ' No proof file attached.')],
            '/admin/wellness/creators/' . (int) $creator['id'],
        );

        return ['ok' => true];
    }

    /**
     * Add proof to a permission that is already waiting for review.
     *
     * @param array<string,mixed> $file single $_FILES entry
     * @return array{ok:bool,error?:string}
     */
    public static function addPermissionProof(array $creator, string $type, string $docType, array $file): array
    {
        $perm = self::permissions((int) $creator['id'])[$type] ?? null;
        if (!$perm || $perm['status'] !== 'applied') {
            return ['ok' => false, 'error' => 'You can add proof while an application is waiting for review.'];
        }

        return self::storeDocument((int) $creator['id'], 'permission', $type, $docType, $file);
    }

    // -----------------------------------------------------------------
    // Verified Doctor / Organisation badge (creator applies)
    // -----------------------------------------------------------------

    /**
     * @param array<string,mixed> $in
     * @param array<string,mixed> $files $_FILES['doc'] keyed by doc_type
     * @return array{ok:bool,error?:string}
     */
    public static function requestVerification(array $creator, array $in, array $files): array
    {
        $kind = self::badgeKind($creator);
        if ($kind === null) {
            return ['ok' => false, 'error' => 'Badges are for doctors and organisations. Change your creator type in Profile if you are one.'];
        }
        if (in_array($creator['verify_status'], ['pending', 'verified'], true)) {
            return ['ok' => false, 'error' => $creator['verify_status'] === 'verified' ? 'You are already verified.' : 'Your documents are already being checked.'];
        }

        $set = [];
        if ($kind === 'doctor') {
            $council = trim((string) ($in['med_council'] ?? ''));
            if (!in_array($council, self::COUNCILS, true)) {
                return ['ok' => false, 'error' => 'Choose the council you are registered with.'];
            }
            $reg = self::clean((string) ($in['med_reg_no'] ?? ''), 60);
            $qual = self::clean((string) ($in['med_qualification'] ?? ''), 120);
            if ($reg === null || $qual === null) {
                return ['ok' => false, 'error' => 'Registration number and qualification are required.'];
            }
            $set = ['med_council' => $council, 'med_reg_no' => $reg, 'med_qualification' => $qual,
                    'specialty' => self::clean((string) ($in['specialty'] ?? ''), 120)];
            $required = ['registration_certificate', 'degree_certificate', 'photo_id'];
        } else {
            $reg = self::clean((string) ($in['org_reg_no'] ?? ''), 80);
            if ($reg === null && ($creator['creator_type'] ?? '') !== 'government') {
                return ['ok' => false, 'error' => 'Registration number is required.'];
            }
            $set = ['org_reg_no' => $reg];
            $required = ($creator['creator_type'] ?? '') === 'government' ? ['photo_id'] : ['registration_certificate', 'photo_id'];
        }

        // Required files: a new upload, or one already on file that wasn't rejected.
        $onFile = [];
        foreach (self::documents((int) $creator['id']) as $d) {
            if ($d['purpose'] === 'verification' && $d['status'] !== 'rejected') {
                $onFile[(string) $d['doc_type']] = true;
            }
        }
        $uploads = [];
        foreach (array_keys(self::DOC_TYPES) as $dt) {
            $f = self::singleFile($files, $dt);
            if ($f !== null) {
                $uploads[$dt] = $f;
            }
        }
        foreach ($required as $dt) {
            if (!isset($uploads[$dt]) && !isset($onFile[$dt])) {
                return ['ok' => false, 'error' => 'Please upload: ' . self::DOC_TYPES[$dt] . '.'];
            }
        }
        foreach ($uploads as $dt => $f) {
            $saved = self::storeDocument((int) $creator['id'], 'verification', null, $dt, $f);
            if (!$saved['ok']) {
                return $saved;
            }
        }

        $cols = [];
        $bind = ['id' => (int) $creator['id']];
        foreach ($set as $k => $v) {
            $cols[] = "`$k` = :$k";
            $bind[$k] = $v;
        }
        Database::connection()->prepare(
            'UPDATE wh_creators SET ' . implode(', ', $cols) . ", verify_status = 'pending', verify_requested_at = NOW(), verify_note = NULL WHERE id = :id"
        )->execute($bind);

        $label = $kind === 'doctor' ? 'Verified Doctor' : 'Verified Organisation';
        self::log((int) $creator['id'], 'verify_requested', $label, null);
        self::notifyAdmin(
            $label . ' badge request: ' . $creator['display_name'],
            [$creator['display_name'] . ' uploaded documents for the ' . $label . ' badge.',
             $kind === 'doctor' ? 'Check the registration number on the NMC Indian Medical Register (or the state / dental / AYUSH register) before approving.' : 'Check the organisation registration before approving.'],
            '/admin/wellness/creators/' . (int) $creator['id'],
        );

        return ['ok' => true];
    }

    // -----------------------------------------------------------------
    // Files
    // -----------------------------------------------------------------

    public static function docsRoot(): string
    {
        return self::repoRoot() . '/storage/wellness_docs';
    }

    /**
     * @param array<string,mixed> $file single $_FILES entry
     * @return array{ok:bool,error?:string}
     */
    private static function storeDocument(int $creatorId, string $purpose, ?string $contentType, string $docType, array $file): array
    {
        if (!isset(self::DOC_TYPES[$docType])) {
            $docType = 'other';
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => 'A file failed to upload. Please try again.'];
        }
        if ((int) ($file['size'] ?? 0) > self::DOC_MAX) {
            return ['ok' => false, 'error' => 'Each file must be under 5 MB.'];
        }
        $mime = self::sniff((string) $file['tmp_name']);
        if (!isset(self::DOC_MIMES[$mime])) {
            return ['ok' => false, 'error' => 'Allowed formats: PDF, JPG, PNG, WEBP.'];
        }
        $dir = self::docsRoot() . '/' . $creatorId;
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not save the file. Please try again.'];
        }
        // Defence in depth: these must never be web-readable.
        $guard = self::docsRoot() . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\n");
        }
        $name = $docType . '-' . bin2hex(random_bytes(12)) . '.' . self::DOC_MIMES[$mime];
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Could not save the file. Please try again.'];
        }
        Database::connection()->prepare(
            'INSERT INTO wh_creator_documents (creator_id, purpose, content_type, doc_type, file_path, original_name, file_mime)
             VALUES (:c, :p, :ct, :dt, :fp, :on, :m)'
        )->execute([
            'c' => $creatorId, 'p' => $purpose, 'ct' => $contentType, 'dt' => $docType,
            'fp' => $creatorId . '/' . $name, 'on' => mb_substr((string) ($file['name'] ?? $name), 0, 255), 'm' => $mime,
        ]);

        return ['ok' => true];
    }

    /** Stream a private document (caller has already checked who may see it). */
    public static function documentResponse(array $doc): \App\Http\Response
    {
        $root = realpath(self::docsRoot());
        $abs = $root !== false ? realpath($root . '/' . (string) $doc['file_path']) : false;
        if ($root === false || $abs === false || !str_starts_with($abs, $root . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return \App\Http\Response::html('File not found', 404);
        }

        return new \App\Http\Response((string) file_get_contents($abs), 200, [
            'Content-Type' => isset(self::DOC_MIMES[$doc['file_mime']]) ? (string) $doc['file_mime'] : 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^\w.\-]/', '_', (string) ($doc['original_name'] ?? basename($abs))) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Stream the creator's avatar (lives in the Health Tips photo tree). */
    public static function avatarResponse(array $creator): \App\Http\Response
    {
        $base = realpath(self::repoRoot() . '/storage/health_tips');
        $rel = (string) ($creator['avatar_path'] ?? '');
        $abs = $rel !== '' ? realpath(self::repoRoot() . '/' . $rel) : false;
        if ($base === false || $abs === false || !str_starts_with($abs, $base . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return \App\Http\Response::html('Not found', 404);
        }
        $mime = self::sniff($abs);
        if (!isset(self::PHOTO_MIMES[$mime])) {
            return \App\Http\Response::html('Not found', 404);
        }

        return new \App\Http\Response((string) file_get_contents($abs), 200, [
            'Content-Type' => $mime, 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Avatars go in storage/health_tips/creators/{id}/ so the existing public
     * image endpoint (api/health_tip_image.php, which only serves that tree)
     * can show them on Health Tips pages too.
     *
     * @param array<string,mixed> $file
     * @return array{ok:bool,error?:string,path?:string}
     */
    private static function storePhoto(int $creatorId, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => 'The photo failed to upload. Please try again.'];
        }
        if ((int) ($file['size'] ?? 0) > self::PHOTO_MAX) {
            return ['ok' => false, 'error' => 'Photo must be under 3 MB.'];
        }
        $mime = self::sniff((string) $file['tmp_name']);
        if (!isset(self::PHOTO_MIMES[$mime])) {
            return ['ok' => false, 'error' => 'Photo must be JPG, PNG or WebP.'];
        }
        $rel = 'storage/health_tips/creators/' . $creatorId;
        $dir = self::repoRoot() . '/' . $rel;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not save the photo. Please try again.'];
        }
        $name = 'avatar_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . self::PHOTO_MIMES[$mime];
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Could not save the photo. Please try again.'];
        }

        return ['ok' => true, 'path' => $rel . '/' . $name];
    }

    /** Old avatar may still be the contributor's — only delete files we created. */
    private static function unlinkPhotoIfUnused(string $rel): void
    {
        if (!str_starts_with($rel, 'storage/health_tips/creators/')) {
            return;
        }
        $base = realpath(self::repoRoot() . '/storage/health_tips/creators');
        $abs = realpath(self::repoRoot() . '/' . $rel);
        if ($base !== false && $abs !== false && str_starts_with($abs, $base . DIRECTORY_SEPARATOR) && is_file($abs)) {
            @unlink($abs);
        }
    }

    // -----------------------------------------------------------------
    // Bridge to health_tip_contributors
    // -----------------------------------------------------------------

    /**
     * Keep the Health Tips contributor row in step with the creator:
     *  - active creator approved for recipe / exercise / tip → contributor exists + active,
     *    profile copied, terms marked accepted (creator terms cover Health Tips);
     *  - otherwise → contributor (if any) suspended, which hides their posts.
     */
    public static function syncLegacy(int $creatorId): void
    {
        $c = self::find($creatorId);
        if ($c === null || empty($c['identity_id'])) {
            return;
        }
        $db = Database::connection();
        try {
            $tipApproved = (bool) array_intersect(self::TIP_TYPES, self::approvedTypes($creatorId));
            $want = $tipApproved && $c['status'] === 'active';

            $legacy = null;
            if (!empty($c['legacy_contributor_id'])) {
                $st = $db->prepare('SELECT * FROM health_tip_contributors WHERE id = :id LIMIT 1');
                $st->execute(['id' => (int) $c['legacy_contributor_id']]);
                $legacy = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if ($legacy === null) {
                $st = $db->prepare('SELECT * FROM health_tip_contributors WHERE identity_id = :i LIMIT 1');
                $st->execute(['i' => (int) $c['identity_id']]);
                $legacy = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if ($legacy === null) {
                if (!$want) {
                    return;
                }
                $db->prepare(
                    "INSERT INTO health_tip_contributors (identity_id, slug, display_name, status, granted_at, admin_note)
                     VALUES (:i, :s, :n, 'active', NOW(), 'Wellness Hub creator')"
                )->execute(['i' => (int) $c['identity_id'], 's' => self::legacySlug((string) $c['slug']), 'n' => $c['display_name']]);
                $legacyId = (int) $db->lastInsertId();
            } else {
                $legacyId = (int) $legacy['id'];
            }
            if ((int) ($c['legacy_contributor_id'] ?? 0) !== $legacyId) {
                $db->prepare('UPDATE wh_creators SET legacy_contributor_id = :l WHERE id = :id')->execute(['l' => $legacyId, 'id' => $creatorId]);
            }

            $db->prepare(
                'UPDATE health_tip_contributors
                 SET status = :st, display_name = :n, headline = :h, bio = :b, instagram_handle = :ig, youtube_url = :yt,
                     website_url = :web, avatar_path = COALESCE(:av, avatar_path),
                     terms_accepted_at = COALESCE(terms_accepted_at, :ta), terms_version = COALESCE(terms_version, :tv)
                 WHERE id = :id'
            )->execute([
                'st' => $want ? 'active' : 'suspended', 'n' => $c['display_name'], 'h' => $c['headline'], 'b' => $c['bio'],
                'ig' => $c['instagram_handle'], 'yt' => $c['youtube_url'], 'web' => $c['website_url'],
                'av' => $c['avatar_path'] ?: null, 'ta' => $c['terms_accepted_at'], 'tv' => 'wh-' . ($c['terms_version'] ?? self::TERMS_VERSION),
                'id' => $legacyId,
            ]);
            @unlink(self::repoRoot() . '/storage/cache/sitemap.xml');
        } catch (\Throwable $e) {
            error_log('[wellness syncLegacy] ' . $e->getMessage());
        }
    }

    /**
     * Old admin "grant contributor" path: make sure that contributor also has a
     * creator row (approved for the three Health Tips types).
     */
    public static function adoptLegacy(int $contributorId): void
    {
        try {
            $db = Database::connection();
            $st = $db->prepare('SELECT c.*, pi.email AS identity_email FROM health_tip_contributors c LEFT JOIN patient_identities pi ON pi.id = c.identity_id WHERE c.id = :id');
            $st->execute(['id' => $contributorId]);
            $c = $st->fetch(PDO::FETCH_ASSOC);
            if (!$c) {
                return;
            }
            $creator = self::forIdentity((int) $c['identity_id']);
            if ($creator === null) {
                $db->prepare(
                    "INSERT INTO wh_creators (identity_id, legacy_contributor_id, slug, display_name, creator_type, headline, bio,
                                              avatar_path, email, instagram_handle, youtube_url, website_url, status, terms_accepted_at, terms_version)
                     VALUES (:i, :l, :s, :n, 'individual', :h, :b, :av, :e, :ig, :yt, :web, :st, :ta, :tv)"
                )->execute([
                    'i' => (int) $c['identity_id'], 'l' => $contributorId, 's' => self::uniqueSlug((string) $c['display_name'], (string) $c['slug']),
                    'n' => $c['display_name'], 'h' => $c['headline'], 'b' => $c['bio'], 'av' => $c['avatar_path'],
                    'e' => $c['identity_email'] ?: null, 'ig' => $c['instagram_handle'], 'yt' => $c['youtube_url'], 'web' => $c['website_url'],
                    'st' => $c['status'], 'ta' => $c['terms_accepted_at'], 'tv' => $c['terms_version'],
                ]);
                $creatorId = (int) $db->lastInsertId();
            } else {
                $creatorId = (int) $creator['id'];
                $db->prepare("UPDATE wh_creators SET legacy_contributor_id = :l, status = 'active' WHERE id = :id")->execute(['l' => $contributorId, 'id' => $creatorId]);
            }
            $ins = $db->prepare(
                "INSERT INTO wh_creator_permissions (creator_id, content_type, status, applied_at, reviewed_at, review_note)
                 VALUES (:c, :t, 'approved', NOW(), NOW(), 'Granted from Health Tips contributors')
                 ON DUPLICATE KEY UPDATE status = 'approved', reviewed_at = NOW()"
            );
            foreach (['recipe', 'exercise', 'tip'] as $t) {   // the old contributor grant never covered videos
                $ins->execute(['c' => $creatorId, 't' => $t]);
            }
        } catch (\Throwable $e) {
            error_log('[wellness adoptLegacy] ' . $e->getMessage());   // wellness patch not run yet
        }
    }

    /** Old admin pause / re-activate on a contributor → mirror onto the creator. */
    public static function mirrorLegacyStatus(int $contributorId, string $status): void
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            return;
        }
        try {
            Database::connection()->prepare('UPDATE wh_creators SET status = :s WHERE legacy_contributor_id = :l')
                ->execute(['s' => $status, 'l' => $contributorId]);
        } catch (\Throwable) {
            // wellness patch not run yet
        }
    }

    // -----------------------------------------------------------------
    // Shared helpers
    // -----------------------------------------------------------------

    public static function log(int $creatorId, string $action, ?string $detail, ?int $adminId): void
    {
        try {
            Database::connection()->prepare(
                'INSERT INTO wh_creator_log (creator_id, action, detail, admin_id) VALUES (:c, :a, :d, :ad)'
            )->execute(['c' => $creatorId, 'a' => $action, 'd' => $detail !== null ? mb_substr($detail, 0, 600) : null, 'ad' => $adminId]);
        } catch (\Throwable) {
        }
    }

    /** @param list<string> $lines */
    public static function notifyAdmin(string $subject, array $lines, string $adminPath): void
    {
        $to = trim((string) ($_ENV['HEALTHTIPS_ADMIN_EMAIL'] ?? '')) ?: trim((string) ($_ENV['SMTP_TO_EMAIL'] ?? '')) ?: 'wecare@eclinicpro.com';
        try {
            MailService::send($to, 'wellness_admin_alert', [
                'subject' => $subject,
                'lines' => $lines,
                'review_url' => self::appUrl($adminPath),
            ]);
        } catch (\Throwable $e) {
            error_log('[wellness notifyAdmin] ' . $e->getMessage());
        }
    }

    /** Email the creator, only if they gave an address. @param array<string,mixed> $payload */
    public static function notifyCreator(array $creator, string $template, array $payload): void
    {
        $email = trim((string) ($creator['email'] ?? ''));
        if ($email === '') {
            return;
        }
        try {
            MailService::send($email, $template, $payload + [
                'name' => (string) $creator['display_name'],
                'studio_url' => self::appUrl('/creator/dashboard'),
            ]);
        } catch (\Throwable $e) {
            error_log('[wellness notifyCreator] ' . $e->getMessage());
        }
    }

    public static function appUrl(string $path = ''): string
    {
        return rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/') . $path;
    }

    public static function siteUrl(string $path = ''): string
    {
        return rtrim((string) ($_ENV['SITE_URL'] ?? 'https://eclinicpro.com'), '/') . $path;
    }

    public static function clean(string $v, int $max): ?string
    {
        $v = trim(preg_replace('/[^\P{C}\n]+/u', '', $v) ?? '');

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private static function sniff(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $path) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        return $mime;
    }

    /** @return list<array<string,mixed>> single-file entries from a multi-file input */
    private static function normalizeFiles(array $files): array
    {
        $out = [];
        if (!isset($files['name']) || !is_array($files['name'])) {
            return $out;
        }
        foreach ($files['name'] as $i => $name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || (string) $name === '') {
                continue;
            }
            $out[] = [
                'name' => $name, 'type' => $files['type'][$i] ?? '', 'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $files['size'][$i] ?? 0,
            ];
        }

        return $out;
    }

    /** One file from a doc[<key>] keyed input, or null if none chosen. */
    private static function singleFile(array $files, string $key): ?array
    {
        if (!isset($files['name'][$key]) || ($files['error'][$key] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return [
            'name' => $files['name'][$key], 'type' => $files['type'][$key] ?? '', 'tmp_name' => $files['tmp_name'][$key] ?? '',
            'error' => $files['error'][$key], 'size' => $files['size'][$key] ?? 0,
        ];
    }

    private static function uniqueSlug(string $name, ?string $prefer = null): string
    {
        $base = $prefer ?: self::slugify($name);
        $st = Database::connection()->prepare('SELECT 1 FROM wh_creators WHERE slug = :s LIMIT 1');
        $slug = $base;
        for ($n = 2; $n < 500; $n++) {
            $st->execute(['s' => $slug]);
            if ($st->fetchColumn() === false) {
                return $slug;
            }
            $slug = $base . '-' . $n;
        }

        return $base . '-' . bin2hex(random_bytes(3));
    }

    /** Slug for a new health_tip_contributors row — the creator's own if free there. */
    private static function legacySlug(string $want): string
    {
        $st = Database::connection()->prepare('SELECT 1 FROM health_tip_contributors WHERE slug = :s LIMIT 1');
        $slug = $want;
        for ($n = 2; $n < 500; $n++) {
            $st->execute(['s' => $slug]);
            if ($st->fetchColumn() === false) {
                return $slug;
            }
            $slug = $want . '-' . $n;
        }

        return $want . '-' . bin2hex(random_bytes(3));
    }

    private static function slugify(string $name): string
    {
        $base = strtolower(trim($name));
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
            if ($t !== false) {
                $base = $t;
            }
        }
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', $base) ?? '', '-');

        return $base === '' ? 'creator' : substr($base, 0, 60);
    }

    private static function repoRoot(): string
    {
        return dirname(__DIR__, 4);   // app/app/Services/Wellness → repo root
    }
}
