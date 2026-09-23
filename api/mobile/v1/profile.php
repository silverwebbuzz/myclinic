<?php
// =====================================================================
// api/mobile/v1/profile.php — "My Profile" facade.
//
// Thin adapter over partials/patient_profile.php — the SAME data layer the
// website's patient-panel My Profile tab uses (api/patient_profile.php).
// The column whitelist, partial-save semantics, email-uniqueness check,
// photo validation/storage and avatar streaming all stay in ecp_profile_*;
// this file only adapts transport.
//
// WHAT THIS EDITS: the logged-in person's OWN patient_identities row —
// contact details, postal address, emergency contact, medical notes,
// lifestyle and a photo. Every field is optional except `name`, which may
// be updated but never blanked.
//
// PRIMARY PHONE IS READ-ONLY here. It is the verified, unique account key;
// changing it runs through the OTP flow (check_phone → send_phone_otp →
// verify_phone_otp on the WEB endpoint), which the app does not expose in
// v1. Sending `phone` to ?action=save is ignored, not an error.
//
// PARTIAL SAVE: only keys actually present in the body are written, so the
// app can PATCH a single field without resending the whole form.
//
//   GET  ?action=get                       (Bearer) → { profile, options }
//   POST ?action=save                      (Bearer) JSON or form → { profile }
//   POST ?action=photo                     (Bearer) multipart: file → { has_photo }
//   GET  ?action=photo                     (Bearer) → raw image bytes
//   POST ?action=photo_remove              (Bearer) → clear the avatar
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../partials/patient_profile.php';

$action = $_GET['action'] ?? 'get';

// GET ?action=photo streams bytes and must not emit the JSON envelope.
// (POST ?action=photo is the UPLOAD and stays JSON — hence the method check.)
if ($action === 'photo' && ecp_m_method() === 'GET') {
    $me = ecp_m_require_patient();
    // Shared streamer: re-reads photo_path, guards the realpath to the
    // patient_photos tree and sets its own headers (404s when unset).
    ecp_profile_stream_photo((int) $me['id']);
    exit;
}

switch ($action) {

    // ----- read the profile ----------------------------------------------
    case 'get': {
        ecp_m_require_method('GET');
        $me      = ecp_m_require_patient();
        $profile = ecp_profile_get((int) $me['id']);
        if ($profile === null) ecp_m_err('not_found', 404);
        ecp_m_ok([
            'profile' => ecp_m_shape_profile($profile),
            // Authoritative pick-lists so the app never hardcodes enums.
            'options' => [
                'genders'      => ECP_PROFILE_GENDERS,
                'blood_groups' => ECP_PROFILE_BLOOD,
                'veg_types'    => ECP_PROFILE_VEG,
            ],
        ]);
        break;
    }

    // ----- partial save ---------------------------------------------------
    case 'save': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        $in = ecp_m_input();

        // The primary phone is not writable here; drop it before mapping so
        // a client that echoes the whole profile back cannot try to set it.
        unset($in['phone']);

        $res = ecp_profile_save((int) $me['id'], $in);
        if (!$res['ok']) {
            $status = match ($res['error'] ?? '') {
                'name_required'  => 400,
                'email_in_use'   => 409,
                'db_unavailable' => 503,
                default          => 400,
            };
            ecp_m_err($res['error'] ?? 'save_failed', $status);
        }
        ecp_m_ok([
            'profile' => $res['profile'] ? ecp_m_shape_profile($res['profile']) : null,
        ]);
        break;
    }

    // ----- upload / replace the avatar (multipart) ------------------------
    case 'photo': {
        ecp_m_require_method('POST');   // GET was handled above
        $me  = ecp_m_require_patient();
        $res = ecp_profile_save_photo((int) $me['id'], $_FILES['file'] ?? null);
        if (!$res['ok']) {
            $status = match ($res['error'] ?? '') {
                'no_file'               => 400,
                'file_too_large'        => 413,
                'file_type_not_allowed' => 415,
                'storage_unavailable',
                'store_failed'          => 500,
                'db_unavailable'        => 503,
                default                 => 400,
            };
            // Pass through only the hints the data layer actually set.
            $extra = array_filter([
                'max_mb'  => $res['max_mb']  ?? null,
                'allowed' => $res['allowed'] ?? null,
            ], static fn($v) => $v !== null);
            ecp_m_err($res['error'] ?? 'photo_failed', $status, $extra);
        }
        ecp_m_ok([
            'has_photo'  => true,
            // Relative on purpose: the app prefixes its API base URL and
            // sends the Bearer token, since the avatar is NOT public.
            'photo_url'  => 'api/mobile/v1/profile.php?action=photo',
            // Cache-buster the app can append so a replaced avatar refreshes.
            'photo_tag'  => substr(hash('sha256', (string) ($res['path'] ?? '')), 0, 12),
        ]);
        break;
    }

    // ----- clear the avatar ------------------------------------------------
    case 'photo_remove': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        ecp_m_remove_photo((int) $me['id']);
        ecp_m_ok(['has_photo' => false]);
        break;
    }

    default:
        ecp_m_err('unknown_action', 400);
}

// ---------------------------------------------------------------------

/**
 * Client shape for the profile. ecp_profile_get() already strips photo_path
 * and the verification timestamps; this adds the derived fields the app's
 * profile header renders (initials, age, avatar pointer) and normalises the
 * booleans so Flutter never has to parse 0/1 strings.
 *
 * @param array<string,mixed> $p
 * @return array<string,mixed>
 */
function ecp_m_shape_profile(array $p): array {
    $name     = (string) ($p['name'] ?? '');
    $hasPhoto = !empty($p['has_photo']);

    $out = $p;
    $out['id']             = (int) ($p['id'] ?? 0);
    $out['initials']       = ecp_m_profile_initials($name);
    $out['age']            = ecp_m_profile_age($p['dob'] ?? null);
    $out['phone_verified'] = !empty($p['phone_verified']);
    $out['email_verified'] = !empty($p['email_verified']);
    $out['has_photo']      = $hasPhoto;
    $out['photo_url']      = $hasPhoto ? 'api/mobile/v1/profile.php?action=photo' : null;
    // Signals to the app that the field is display-only (OTP flow owns it).
    $out['phone_editable'] = false;

    return $out;
}

/** Up to two initials for the avatar fallback ("Ananya Sharma" → "AS"). */
function ecp_m_profile_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));
    if ($parts === []) return '';
    $first = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) === 1) return $first;
    return $first . mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1));
}

/** Whole years from a YYYY-MM-DD dob, or null when unset/invalid. */
function ecp_m_profile_age(?string $dob): ?int {
    if ($dob === null || trim($dob) === '' || str_starts_with($dob, '0000')) return null;
    try {
        $d = new DateTimeImmutable($dob);
    } catch (Exception) {
        return null;
    }
    $age = (int) $d->diff(new DateTimeImmutable('today'))->y;
    return ($age >= 0 && $age < 130) ? $age : null;
}

/**
 * Clear the avatar: blank photo_path and unlink the file.
 *
 * Implemented here because the shared layer has no remover (the web tab only
 * ever replaces a photo). Mirrors the containment guard ecp_profile_save_photo
 * uses when it deletes a superseded file.
 */
function ecp_m_remove_photo(int $ownerId): void {
    $db = ecp_db();
    if (!$db || $ownerId <= 0) return;

    $stmt = $db->prepare('SELECT photo_path FROM patient_identities WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $ownerId]);
    $old = (string) ($stmt->fetchColumn() ?: '');

    $db->prepare('UPDATE patient_identities SET photo_path = NULL WHERE id = :id AND is_active = 1')
       ->execute(['id' => $ownerId]);

    if ($old !== '') {
        $abs = __DIR__ . '/../../../' . ltrim($old, '/');
        if (is_file($abs) && str_contains($abs, '/storage/patient_photos/')) {
            @unlink($abs);
        }
    }
}
