<?php
// =====================================================================
// api/family_doc.php — private documents for family members.
//
//   POST /api/family_doc                     (multipart) → upload
//   POST /api/family_doc?action=delete       → delete a doc
//   GET  /api/family_doc?id=N                → stream a private doc
//
// Files live under storage/patient_docs/ (outside webroot, htaccess-denied).
// Access is ALWAYS gated: a doc can only be read/written by an account
// holder who is linked to the document's owner identity (no IDOR).
//
// Accepts images + PDF only, ≤5 MB, MIME sniffed (extension not trusted).
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/patient_family.php';

const ECP_DOC_ROOT     = __DIR__ . '/../storage/patient_docs';
const ECP_DOC_MAX_BYTES = 5 * 1024 * 1024;   // 5 MB
// mime => extension
const ECP_DOC_ALLOWED = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];

set_exception_handler(function (Throwable $e) {
    error_log('[api/family_doc] ' . $e->getMessage());
    http_response_code(500);
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
});

$me = ecp_patient_current();
if (!$me) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
}
$ownerId = (int) $me['id'];
$action  = (string) ($_GET['action'] ?? '');

// ---- GET: stream a private document the owner is allowed to see ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $docId = (int) ($_GET['id'] ?? 0);
    $doc = ecp_doc_owned($ownerId, $docId);
    if (!$doc) { http_response_code(404); exit; }

    $abs = ECP_DOC_ROOT . '/' . $doc['file_path'];
    // Defense in depth: never escape the doc root.
    $real = realpath($abs);
    if ($real === false || !str_starts_with($real, realpath(ECP_DOC_ROOT) . DIRECTORY_SEPARATOR)) {
        http_response_code(404); exit;
    }
    header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($real));
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($doc['title'] ?: 'document')) . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($real);
    exit;
}

// ---- POST from here on ----
header('Content-Type: application/json');

if ($action === 'delete') {
    $docId = (int) ($_POST['id'] ?? 0);
    $doc = ecp_doc_owned($ownerId, $docId);
    if (!$doc) { echo json_encode(['ok' => false, 'error' => 'not_authorized']); exit; }
    $abs = ECP_DOC_ROOT . '/' . $doc['file_path'];
    if (is_file($abs)) @unlink($abs);
    ecp_db()->prepare('DELETE FROM patient_owned_documents WHERE id = :id')->execute(['id' => $docId]);
    echo json_encode(['ok' => true]);
    exit;
}

// ---- POST (multipart): upload ----
$memberId = (int) ($_POST['member_id'] ?? 0);
if (!ecp_fam_can_manage($ownerId, $memberId)) {
    echo json_encode(['ok' => false, 'error' => 'not_authorized']);
    exit;
}

if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'no_file']);
    exit;
}
$file = $_FILES['file'];
if ((int) $file['size'] > ECP_DOC_MAX_BYTES) {
    echo json_encode(['ok' => false, 'error' => 'too_large']);
    exit;
}

// Sniff the real MIME — do not trust the client-sent type or extension.
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = (string) $finfo->file($file['tmp_name']);
if (!isset(ECP_DOC_ALLOWED[$mime])) {
    echo json_encode(['ok' => false, 'error' => 'unsupported_type']);
    exit;
}
$ext = ECP_DOC_ALLOWED[$mime];

$docType = in_array($_POST['doc_type'] ?? '', ECP_DOC_TYPES, true) ? $_POST['doc_type'] : 'other';
$title   = trim((string) ($_POST['title'] ?? '')) ?: ucfirst(str_replace('_', ' ', $docType));
$nextDue = ecp_fam_clean_date($_POST['next_due_on'] ?? '');

// Store under storage/patient_docs/{memberId}/{random}.ext (private).
$dir = ECP_DOC_ROOT . '/' . $memberId;
if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
    echo json_encode(['ok' => false, 'error' => 'storage_unavailable']);
    exit;
}
$fname = bin2hex(random_bytes(16)) . '.' . $ext;
$rel   = $memberId . '/' . $fname;
$abs   = ECP_DOC_ROOT . '/' . $rel;
if (!move_uploaded_file($file['tmp_name'], $abs)) {
    echo json_encode(['ok' => false, 'error' => 'save_failed']);
    exit;
}
@chmod($abs, 0640);

$db = ecp_db();
$db->prepare(
    'INSERT INTO patient_owned_documents
        (family_member_id, owner_identity_id, doc_type, title, file_path, mime_type, size_bytes, next_due_on)
     VALUES (:mid, :owner, :type, :title, :path, :mime, :size, :due)'
)->execute([
    'mid'   => $memberId,
    'owner' => $ownerId,
    'type'  => $docType,
    'title' => $title,
    'path'  => $rel,
    'mime'  => $mime,
    'size'  => (int) $file['size'],
    'due'   => $nextDue,
]);

echo json_encode([
    'ok'    => true,
    'doc'   => [
        'id'         => (int) $db->lastInsertId(),
        'family_member_id' => $memberId,
        'doc_type'   => $docType,
        'title'      => $title,
        'mime_type'  => $mime,
        'size_bytes' => (int) $file['size'],
        'next_due_on' => $nextDue,
    ],
]);

/**
 * Fetch a document row IF the logged-in owner is allowed to see it
 * (i.e. it belongs to a member they manage). Returns null otherwise.
 *
 * @return array<string,mixed>|null
 */
function ecp_doc_owned(int $ownerId, int $docId): ?array
{
    if ($docId <= 0) return null;
    $db = ecp_db();
    if (!$db) return null;
    // Owner-scoped fetch: a doc is only visible to the account that owns it.
    $stmt = $db->prepare(
        'SELECT * FROM patient_owned_documents
         WHERE id = :id AND owner_identity_id = :o LIMIT 1'
    );
    $stmt->execute(['id' => $docId, 'o' => $ownerId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    return $doc ?: null;
}
