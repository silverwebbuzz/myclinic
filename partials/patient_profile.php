<?php
// =====================================================================
// patient_profile.php — data layer for the patient-panel "My Profile" tab.
//
// The logged-in person's OWN record lives in patient_identities. The Family
// tab (family_member_identities) already covers relation/name/dob/gender/
// blood/ABHA for dependents; this tab fills everything the members box does
// NOT: contact details, postal address, emergency contact, medical notes,
// lifestyle, and a profile photo.
//
// Everything here is OPTIONAL — we save whatever the patient chooses to fill
// and never require a field (name aside, which every identity already has).
//
// AUTHORIZATION: every read/write is scoped to the logged-in identity id.
// A patient can only ever touch their own patient_identities row (no IDOR).
//
// Photos live under storage/patient_photos/{owner_id}/ (NOT the public
// webroot) and are streamed via api/patient_profile?action=photo.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const ECP_PROFILE_GENDERS   = ['M', 'F', 'Other'];
const ECP_PROFILE_BLOOD     = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
const ECP_PROFILE_VEG       = ['veg', 'nonveg', 'vegan', 'eggetarian'];
const ECP_PROFILE_PHOTO_MAX = 4 * 1024 * 1024; // 4 MB
const ECP_PROFILE_PHOTO_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/**
 * Return the profile fields the "My Profile" tab renders/edits, shaped for the
 * client. Reads the logged-in identity's own row; never exposes another
 * identity's data.
 *
 * @return array<string,mixed>|null
 */
function ecp_profile_get(int $ownerId): ?array
{
    $db = ecp_db();
    if (!$db || $ownerId <= 0) return null;

    $stmt = $db->prepare(
        'SELECT id, name, first_name, middle_name, last_name, preferred_name,
                phone, phone_alt, email, phone_verified_at, email_verified_at,
                dob, gender, blood_group, veg_type,
                allergies, chronic_conditions,
                address_line1, address_line2, address_city, address_state,
                address_postal_code, address_country,
                emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                abha_id, gov_id_last4, photo_path
         FROM patient_identities
         WHERE id = :id AND is_active = 1 LIMIT 1'
    );
    $stmt->execute(['id' => $ownerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    // phone is verified & unique — surface as read-only. Never let the panel
    // rewrite the primary number here (that runs through the OTP flow).
    $row['phone_verified']   = !empty($row['phone_verified_at']);
    $row['email_verified']   = !empty($row['email_verified_at']);
    $row['has_photo']        = !empty($row['photo_path']);
    unset($row['photo_path'], $row['phone_verified_at'], $row['email_verified_at']);

    return $row;
}

/**
 * Save the editable profile fields. Only whitelisted columns are written and
 * only when present in $data — a partial save never wipes untouched fields.
 * The primary `phone` is intentionally NOT writable here.
 *
 * @param array<string,mixed> $data
 * @return array{ok:bool, error?:string, profile?:array<string,mixed>}
 */
function ecp_profile_save(int $ownerId, array $data): array
{
    $db = ecp_db();
    if (!$db || $ownerId <= 0) return ['ok' => false, 'error' => 'db_unavailable'];

    // Name is the one field every identity already has — keep it non-empty if
    // the client sends it, but do not require it in the payload.
    if (array_key_exists('name', $data) && trim((string) $data['name']) === '') {
        return ['ok' => false, 'error' => 'name_required'];
    }

    // email is UNIQUE across patient_identities — reject a collision cleanly
    // rather than surfacing a raw 23000 from the driver.
    if (array_key_exists('email', $data)) {
        $email = ecp_profile_clean_email($data['email']);
        if ($email !== null && ecp_profile_email_taken($ownerId, $email)) {
            return ['ok' => false, 'error' => 'email_in_use'];
        }
    }

    $set = ecp_profile_map_fields($data);
    if ($set === []) {
        // Nothing valid to write — treat as a successful no-op.
        return ['ok' => true, 'profile' => ecp_profile_get($ownerId)];
    }

    // Changing the email clears its verification stamp (must re-verify).
    if (array_key_exists('email', $set)) {
        $set['email_verified_at'] = null;
    }

    $cols   = array_keys($set);
    $assign = implode(', ', array_map(static fn($c) => "`$c` = :$c", $cols));
    $params = $set + ['id' => $ownerId];

    $db->prepare(
        'UPDATE patient_identities SET ' . $assign . ' WHERE id = :id AND is_active = 1'
    )->execute($params);

    return ['ok' => true, 'profile' => ecp_profile_get($ownerId)];
}

/**
 * Whitelist + normalize the incoming payload into DB columns. Only keys the
 * client actually sent are returned, so callers get a true partial update.
 *
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function ecp_profile_map_fields(array $data): array
{
    $out = [];

    // Plain text fields (trim; empty string → NULL so we don't store blanks).
    $text = [
        'name'                       => 120,
        'first_name'                 => 60,
        'middle_name'                => 60,
        'last_name'                  => 60,
        'preferred_name'             => 60,
        'address_line1'              => 200,
        'address_line2'              => 200,
        'address_city'               => 80,
        'address_state'              => 80,
        'address_postal_code'        => 20,
        'emergency_contact_name'     => 120,
        'emergency_contact_relation' => 40,
    ];
    foreach ($text as $col => $max) {
        if (!array_key_exists($col, $data)) continue;
        $v = trim((string) $data[$col]);
        $out[$col] = $v === '' ? null : mb_substr($v, 0, $max);
    }

    // name must never become NULL — validated above, but guard here too.
    if (array_key_exists('name', $out) && $out['name'] === null) {
        unset($out['name']);
    }

    // Free-text medical notes (longer, no hard truncation beyond column type).
    foreach (['allergies', 'chronic_conditions'] as $col) {
        if (!array_key_exists($col, $data)) continue;
        $v = trim((string) $data[$col]);
        $out[$col] = $v === '' ? null : $v;
    }

    // Phone-shaped fields (alt phone + emergency phone). Primary phone excluded.
    foreach (['phone_alt', 'emergency_contact_phone'] as $col) {
        if (!array_key_exists($col, $data)) continue;
        $out[$col] = ecp_profile_clean_phone($data[$col]);
    }

    if (array_key_exists('email', $data)) {
        $out['email'] = ecp_profile_clean_email($data['email']);
    }

    if (array_key_exists('dob', $data)) {
        $out['dob'] = ecp_profile_clean_date($data['dob']);
    }

    if (array_key_exists('gender', $data)) {
        $out['gender'] = in_array($data['gender'], ECP_PROFILE_GENDERS, true) ? $data['gender'] : null;
    }
    if (array_key_exists('blood_group', $data)) {
        $out['blood_group'] = in_array($data['blood_group'], ECP_PROFILE_BLOOD, true) ? $data['blood_group'] : null;
    }
    if (array_key_exists('veg_type', $data)) {
        $out['veg_type'] = in_array($data['veg_type'], ECP_PROFILE_VEG, true) ? $data['veg_type'] : null;
    }

    // Two-letter ISO country; default IN when blanked.
    if (array_key_exists('address_country', $data)) {
        $cc = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $data['address_country']) ?? '');
        $out['address_country'] = strlen($cc) === 2 ? $cc : 'IN';
    }

    if (array_key_exists('abha_id', $data)) {
        $out['abha_id'] = ecp_profile_clean_abha($data['abha_id']);
    }
    if (array_key_exists('gov_id_last4', $data)) {
        $d = preg_replace('/[^0-9]/', '', (string) $data['gov_id_last4']) ?? '';
        $out['gov_id_last4'] = strlen($d) === 4 ? $d : null;
    }

    return $out;
}

function ecp_profile_email_taken(int $ownerId, string $email): bool
{
    $db = ecp_db();
    if (!$db) return false;
    $stmt = $db->prepare(
        'SELECT 1 FROM patient_identities WHERE email = :e AND id <> :id LIMIT 1'
    );
    $stmt->execute(['e' => $email, 'id' => $ownerId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Store an uploaded profile photo under storage/patient_photos/{owner}/ and
 * point patient_identities.photo_path at it. Returns the new relative path.
 *
 * @param array<string,mixed> $file a single $_FILES entry
 * @return array{ok:bool, error?:string, max_mb?:int, allowed?:list<string>, path?:string}
 */
function ecp_profile_save_photo(int $ownerId, ?array $file): array
{
    $db = ecp_db();
    if (!$db || $ownerId <= 0) return ['ok' => false, 'error' => 'db_unavailable'];

    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'no_file'];
    }
    if ((int) ($file['size'] ?? 0) > ECP_PROFILE_PHOTO_MAX) {
        return ['ok' => false, 'error' => 'file_too_large', 'max_mb' => (int) (ECP_PROFILE_PHOTO_MAX / 1024 / 1024)];
    }

    $mime = ecp_profile_detect_mime((string) $file['tmp_name']);
    if (!isset(ECP_PROFILE_PHOTO_MIMES[$mime])) {
        return ['ok' => false, 'error' => 'file_type_not_allowed', 'allowed' => array_keys(ECP_PROFILE_PHOTO_MIMES)];
    }

    $dir = __DIR__ . '/../storage/patient_photos/' . $ownerId;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'storage_unavailable'];
    }

    $ext  = ECP_PROFILE_PHOTO_MIMES[$mime];
    $name = 'avatar_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'store_failed'];
    }

    // Remove the previous photo so we don't orphan files on every re-upload.
    $prev = $db->prepare('SELECT photo_path FROM patient_identities WHERE id = :id LIMIT 1');
    $prev->execute(['id' => $ownerId]);
    $old = (string) ($prev->fetchColumn() ?: '');

    $path = 'storage/patient_photos/' . $ownerId . '/' . $name;
    $db->prepare('UPDATE patient_identities SET photo_path = :p WHERE id = :id AND is_active = 1')
       ->execute(['p' => $path, 'id' => $ownerId]);

    if ($old !== '' && $old !== $path) {
        $abs = __DIR__ . '/../' . ltrim($old, '/');
        if (is_file($abs) && str_contains($abs, '/storage/patient_photos/')) {
            @unlink($abs);
        }
    }

    return ['ok' => true, 'path' => $path];
}

/** Stream the logged-in patient's photo inline (ownership already scoped). */
function ecp_profile_stream_photo(int $ownerId): void
{
    $db = ecp_db();
    if (!$db) { http_response_code(404); return; }
    $stmt = $db->prepare('SELECT photo_path FROM patient_identities WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $ownerId]);
    $rel = (string) ($stmt->fetchColumn() ?: '');
    if ($rel === '') { http_response_code(404); return; }

    $real = realpath(__DIR__ . '/../' . ltrim($rel, '/')) ?: '';
    $base = realpath(__DIR__ . '/../storage/patient_photos') ?: '';
    if ($real === '' || $base === '' || !str_starts_with($real, $base) || !is_file($real)) {
        http_response_code(404);
        return;
    }

    header('Content-Type: ' . (ecp_profile_detect_mime($real) ?: 'application/octet-stream'));
    header('Content-Disposition: inline; filename="avatar"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, no-store');
    readfile($real);
}

// ---------------------------------------------------------------------
// small normalizers
// ---------------------------------------------------------------------

function ecp_profile_clean_phone($raw): ?string
{
    $s = preg_replace('/[^0-9+]/', '', (string) $raw) ?? '';
    return $s !== '' ? mb_substr($s, 0, 20) : null;
}

function ecp_profile_clean_email($raw): ?string
{
    $s = trim((string) $raw);
    if ($s === '') return null;
    return filter_var($s, FILTER_VALIDATE_EMAIL) ? mb_substr($s, 0, 160) : null;
}

function ecp_profile_clean_date($raw): ?string
{
    $s = trim((string) $raw);
    if ($s === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}

function ecp_profile_clean_abha($raw): ?string
{
    $s = preg_replace('/[^0-9]/', '', (string) $raw) ?? '';
    return $s !== '' ? mb_substr($s, 0, 20) : null;
}

function ecp_profile_detect_mime(string $path): string
{
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $m = finfo_file($f, $path) ?: '';
            finfo_close($f);
            if ($m !== '') return $m;
        }
    }
    $info = @getimagesize($path);
    return $info['mime'] ?? '';
}
