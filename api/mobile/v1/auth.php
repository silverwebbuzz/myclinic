<?php
// =====================================================================
// api/mobile/v1/auth.php — mobile auth facade.
//
// Delegates 100% to the same OTP + session functions the website uses
// (partials/patient_auth.php). The ONLY mobile-specific behaviour is that
// verify returns the raw session token so the app can store it and send
// it back as `Authorization: Bearer <token>` on later calls.
//
//   POST /api/mobile/v1/auth.php?action=send_otp
//        { phone, intent? }               intent: signin|signup|''
//   POST /api/mobile/v1/auth.php?action=verify_otp
//        { phone, code, name? }           → { token, patient, is_new }
//   GET  /api/mobile/v1/auth.php?action=me            (Bearer)
//   POST /api/mobile/v1/auth.php?action=logout        (Bearer)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // -----------------------------------------------------------------
    case 'send_otp': {
        ecp_m_require_method('POST');
        $in     = ecp_m_input();
        $phone  = (string) ($in['phone'] ?? '');
        $intent = (string) ($in['intent'] ?? '');
        if ($phone === '') ecp_m_err('phone_required', 400);

        // Same pre-check the web modal does: reject mismatched intent.
        $normalized   = ecp_normalize_phone($phone);
        $exists       = false;
        $existingName = null;
        if ($normalized !== '') {
            $db = ecp_db();
            if ($db) {
                $stmt = $db->prepare('SELECT id, name, first_name FROM patient_identities WHERE phone = :p LIMIT 1');
                $stmt->execute(['p' => $normalized]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $exists = true;
                    $existingName = $row['first_name'] ?: explode(' ', (string) $row['name'])[0];
                }
            }
        }
        if ($intent === 'signin' && !$exists) ecp_m_err('account_not_found', 404);
        if ($intent === 'signup' && $exists)  ecp_m_err('account_exists', 409);

        $res = ecp_patient_send_otp($phone, $intent);
        if (!$res['ok']) {
            $status = match ($res['error']) {
                'invalid_phone'   => 400,
                'resend_too_soon',
                'otp_locked'      => 429,
                'db_unavailable'  => 503,
                default           => 500,
            };
            ecp_m_err($res['error'], $status, ['retry_after' => $res['retry_after'] ?? null]);
        }

        ecp_m_ok([
            'phone'     => $res['phone'],
            'mode'      => $res['mode'],           // 'dev' | 'live'
            'exists'    => $exists,                // whether to ask for name
            'name_hint' => $existingName,
            'dev_code'  => $res['dev_code'],       // null in live mode
        ]);
        break;
    }

    // -----------------------------------------------------------------
    case 'verify_otp': {
        ecp_m_require_method('POST');
        $in    = ecp_m_input();
        $phone = (string) ($in['phone'] ?? '');
        $code  = (string) ($in['code']  ?? '');
        $name  = isset($in['name']) ? (string) $in['name'] : null;
        if ($phone === '' || $code === '') ecp_m_err('phone_and_code_required', 400);

        // Same shared verify: consumes the code, links clinic charts, and
        // starts a patient_sessions row (also sets the cookie — harmless).
        $res = ecp_patient_verify_otp($phone, $code, $name);
        if (!$res['ok']) {
            $status = match ($res['error']) {
                'invalid_code', 'invalid_input' => 400,
                'expired', 'no_code_issued'     => 410,
                'too_many_attempts'             => 429,
                'db_unavailable'                => 503,
                default                         => 500,
            };
            ecp_m_err($res['error'], $status);
        }

        // Hand the app the raw session token so it can auth via Bearer.
        // verify_otp already created the session; read back the freshest
        // one for this identity rather than change the shared function's
        // return signature (keeps the web path byte-for-byte unchanged).
        $identityId = (int) $res['identity']['id'];
        $token = ecp_m_latest_session_token($identityId);
        if ($token === '') ecp_m_err('session_start_failed', 500);

        ecp_m_ok([
            'token'   => $token,               // → app secure storage
            'is_new'  => $res['is_new'],
            'patient' => ecp_m_shape_patient($res['identity']),
        ]);
        break;
    }

    // -----------------------------------------------------------------
    case 'me': {
        $me = ecp_m_current();               // null if no/invalid token
        ecp_m_ok(['patient' => $me ? ecp_m_shape_patient($me) : null]);
        break;
    }

    // -----------------------------------------------------------------
    case 'logout': {
        ecp_m_require_method('POST');
        // Delete the specific session this token belongs to (not just the
        // cookie) so the Bearer token is truly revoked server-side.
        $token = ecp_m_bearer_token();
        if ($token !== '') {
            $db = ecp_db();
            if ($db) {
                $db->prepare('DELETE FROM patient_sessions WHERE id = :id')->execute(['id' => $token]);
            }
        }
        ecp_patient_logout();   // also clears cookie state if present
        ecp_m_ok();
        break;
    }

    // -----------------------------------------------------------------
    default:
        ecp_m_err('unknown_action', 400);
}

/**
 * The most recent, still-valid session token for an identity.
 * Used right after ecp_patient_verify_otp() (which just created one) to
 * return it to the mobile client. Scoped to non-expired rows only.
 */
function ecp_m_latest_session_token(int $identityId): string {
    $db = ecp_db();
    if (!$db) return '';
    $stmt = $db->prepare(
        'SELECT id FROM patient_sessions
         WHERE identity_id = :iid AND expires_at > NOW()
         ORDER BY created_at DESC, id DESC LIMIT 1'
    );
    $stmt->execute(['iid' => $identityId]);
    $id = $stmt->fetchColumn();
    return is_string($id) ? $id : '';
}
