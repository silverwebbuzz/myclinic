<?php
// =====================================================================
// api/doctor_claim.php — endpoints for the doctor claim / list-me flow.
//
//   POST ?action=send_otp     body: { phone }
//                              → issues a 6-digit code for verification
//
//   POST ?action=verify_otp   body: { phone, code }
//                              → marks the phone as verified (modal advances)
//
//   POST ?action=submit       multipart/form-data:
//                                type, directory_doctor_id?, full_name, phone,
//                                clinic_name, city, state, specialty,
//                                email?, reg_number?, reg_council?, message?,
//                                document (file)?
//                              → creates the doctor_claim_requests row.
//                                Requires the phone to be OTP-verified first
//                                (a row in doctor_otp_codes with consumed_at).
//
// All responses are JSON. Files saved under storage/claims/.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../partials/db.php';
require_once __DIR__ . '/../partials/sms.php';

set_exception_handler(function (Throwable $e) {
    error_log('[api/doctor_claim] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'hint' => $e->getMessage()]);
    exit;
});

const DOC_OTP_TTL_SECONDS      = 600;
const DOC_OTP_MAX_ATTEMPTS     = 5;
const DOC_OTP_RESEND_SECONDS   = 30;
const DOC_DOC_MAX_BYTES        = 5 * 1024 * 1024;   // 5 MB upload cap

function out(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input_json(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '' && str_starts_with(trim($raw), '{')) {
        $j = json_decode($raw, true);
        if (is_array($j)) return $j;
    }
    return $_POST;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$db = ecp_db();
if (!$db) out(503, ['ok' => false, 'error' => 'db_unavailable']);

// ---------------------------------------------------------------------
// send_otp
// ---------------------------------------------------------------------
if ($action === 'send_otp') {
    if ($method !== 'POST') out(405, ['ok' => false, 'error' => 'method_not_allowed']);

    $in    = input_json();
    $phone = ecp_normalize_phone((string) ($in['phone'] ?? ''));
    if ($phone === '' || strlen($phone) < 8) {
        out(400, ['ok' => false, 'error' => 'invalid_phone']);
    }

    // Throttle re-issue.
    $stmt = $db->prepare(
        'SELECT created_at FROM doctor_otp_codes
         WHERE phone = :p AND purpose = "claim" AND consumed_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute(['p' => $phone]);
    $last = $stmt->fetchColumn();
    if ($last) {
        $age = time() - strtotime((string) $last);
        if ($age < DOC_OTP_RESEND_SECONDS) {
            out(429, ['ok' => false, 'error' => 'resend_too_soon',
                      'retry_after' => DOC_OTP_RESEND_SECONDS - $age]);
        }
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = hash('sha256', $code);

    $db->prepare(
        'INSERT INTO doctor_otp_codes (phone, purpose, code_hash, expires_at)
         VALUES (:p, "claim", :h, DATE_ADD(NOW(), INTERVAL :ttl SECOND))'
    )->execute(['p' => $phone, 'h' => $hash, 'ttl' => DOC_OTP_TTL_SECONDS]);

    $sent = ecp_sms_send_otp($phone, $code);
    out(200, [
        'ok'       => $sent['ok'],
        'mode'     => $sent['mode'],
        'phone'    => $phone,
        'dev_code' => $sent['dev_code'],   // null in live mode
        'error'    => $sent['error'],
    ]);
}

// ---------------------------------------------------------------------
// verify_otp — only marks the phone as verified for THIS submission.
//              Doesn't create the request yet; that happens in 'submit'.
// ---------------------------------------------------------------------
if ($action === 'verify_otp') {
    if ($method !== 'POST') out(405, ['ok' => false, 'error' => 'method_not_allowed']);

    $in    = input_json();
    $phone = ecp_normalize_phone((string) ($in['phone'] ?? ''));
    $code  = preg_replace('/\D/', '', (string) ($in['code'] ?? '')) ?? '';

    if ($phone === '' || strlen($code) !== 6) {
        out(400, ['ok' => false, 'error' => 'invalid_input']);
    }

    $stmt = $db->prepare(
        'SELECT id, code_hash, expires_at, attempts
         FROM doctor_otp_codes
         WHERE phone = :p AND purpose = "claim" AND consumed_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute(['p' => $phone]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row)                                              out(410, ['ok' => false, 'error' => 'no_code_issued']);
    if (strtotime((string) $row['expires_at']) < time())    out(410, ['ok' => false, 'error' => 'expired']);
    if ((int) $row['attempts'] >= DOC_OTP_MAX_ATTEMPTS)     out(429, ['ok' => false, 'error' => 'too_many_attempts']);

    $db->prepare('UPDATE doctor_otp_codes SET attempts = attempts + 1 WHERE id = :id')
       ->execute(['id' => $row['id']]);

    if (!hash_equals((string) $row['code_hash'], hash('sha256', $code))) {
        out(400, ['ok' => false, 'error' => 'invalid_code']);
    }
    $db->prepare('UPDATE doctor_otp_codes SET consumed_at = NOW() WHERE id = :id')
       ->execute(['id' => $row['id']]);

    out(200, ['ok' => true, 'phone' => $phone]);
}

// ---------------------------------------------------------------------
// submit — final form submission. Requires a recently-verified phone.
// ---------------------------------------------------------------------
if ($action === 'submit') {
    if ($method !== 'POST') out(405, ['ok' => false, 'error' => 'method_not_allowed']);

    // Form-encoded because we have a file upload.
    $type        = (string) ($_POST['type'] ?? '');
    $directoryId = (int)    ($_POST['directory_doctor_id'] ?? 0);
    $fullName    = trim((string) ($_POST['full_name']   ?? ''));
    $phoneRaw    = (string) ($_POST['phone']            ?? '');
    $phone       = ecp_normalize_phone($phoneRaw);
    $email       = trim((string) ($_POST['email']       ?? '')) ?: null;
    $clinicName  = trim((string) ($_POST['clinic_name'] ?? '')) ?: null;
    $city        = trim((string) ($_POST['city']        ?? '')) ?: null;
    $state       = trim((string) ($_POST['state']       ?? '')) ?: null;
    $specialty   = trim((string) ($_POST['specialty']   ?? '')) ?: null;
    $regNumber   = trim((string) ($_POST['reg_number']  ?? '')) ?: null;
    $regCouncil  = trim((string) ($_POST['reg_council'] ?? '')) ?: null;
    $message     = trim((string) ($_POST['message']     ?? '')) ?: null;

    if (!in_array($type, ['claim', 'new_listing'], true)) {
        out(400, ['ok' => false, 'error' => 'invalid_type']);
    }
    if ($type === 'claim' && $directoryId <= 0) {
        out(400, ['ok' => false, 'error' => 'directory_doctor_id_required']);
    }
    if ($fullName === '' || $phone === '' || $specialty === null || $city === null || $clinicName === null) {
        out(400, ['ok' => false, 'error' => 'missing_required_fields']);
    }

    // Phone must have been OTP-verified within the last 15 minutes.
    $stmt = $db->prepare(
        'SELECT consumed_at FROM doctor_otp_codes
         WHERE phone = :p AND purpose = "claim" AND consumed_at IS NOT NULL
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute(['p' => $phone]);
    $consumed = $stmt->fetchColumn();
    if (!$consumed || strtotime((string) $consumed) < time() - 900) {
        out(403, ['ok' => false, 'error' => 'phone_not_verified']);
    }

    // Optional document upload.
    $docPath = null;
    if (!empty($_FILES['document']['tmp_name']) && is_uploaded_file($_FILES['document']['tmp_name'])) {
        $f = $_FILES['document'];
        if ((int) $f['size'] > DOC_DOC_MAX_BYTES) {
            out(413, ['ok' => false, 'error' => 'file_too_large', 'max_mb' => DOC_DOC_MAX_BYTES / 1024 / 1024]);
        }
        $mime = mime_content_type($f['tmp_name']) ?: '';
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($mime, $allowed, true)) {
            out(415, ['ok' => false, 'error' => 'file_type_not_allowed', 'allowed' => $allowed]);
        }
        $ext = match ($mime) {
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'application/pdf' => 'pdf',
        };
        $dir = __DIR__ . '/../storage/claims';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
            out(500, ['ok' => false, 'error' => 'upload_failed']);
        }
        $docPath = 'storage/claims/' . $name;
    }

    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    $ip = $ip ? substr(explode(',', (string) $ip)[0], 0, 45) : null;

    $ins = $db->prepare(
        'INSERT INTO doctor_claim_requests
            (type, directory_doctor_id, full_name, phone, phone_verified_at,
             email, clinic_name, city, state, specialty, reg_number,
             reg_council, document_path, message, status, ip, user_agent)
         VALUES
            (:type, :did, :name, :phone, NOW(),
             :email, :clinic, :city, :state, :spec, :reg,
             :council, :doc, :msg, "phone_verified", :ip, :ua)'
    );
    $ins->execute([
        'type'    => $type,
        'did'     => $type === 'claim' ? $directoryId : null,
        'name'    => $fullName,
        'phone'   => $phone,
        'email'   => $email,
        'clinic'  => $clinicName,
        'city'    => $city,
        'state'   => $state,
        'spec'    => $specialty,
        'reg'     => $regNumber,
        'council' => $regCouncil,
        'doc'     => $docPath,
        'msg'     => $message,
        'ip'      => $ip,
        'ua'      => $ua ?: null,
    ]);

    out(200, ['ok' => true, 'request_id' => (int) $db->lastInsertId()]);
}

out(400, ['ok' => false, 'error' => 'unknown_action']);
