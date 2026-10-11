<?php
// =====================================================================
// api/health_tip_image.php — streams Health Tips photos.
//
//   GET /api/health_tip_image?m={media_id}   post photo
//   GET /api/health_tip_image?a={slug}       contributor avatar
//
// Photos live under storage/health_tips/ (web access denied). A post photo is
// served publicly only when its post is published and the contributor is
// active; otherwise only to the contributor who owns it (studio + preview).
// Avatars are public while the contributor is active.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/health_tips.php';

$db = ecp_db();
if (!$db) {
    http_response_code(503);
    exit;
}

$path = null;
$mime = null;
$public = false;

try {
    if (isset($_GET['m'])) {
        $st = $db->prepare(
            'SELECT m.file_path, m.file_mime, t.status, t.is_deleted, c.status AS c_status, c.identity_id
             FROM health_tip_media m
             JOIN health_tips t ON t.id = m.tip_id
             JOIN health_tip_contributors c ON c.id = t.contributor_id
             WHERE m.id = :id LIMIT 1'
        );
        $st->execute(['id' => (int) $_GET['m']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !(int) $row['is_deleted']) {
            $public = $row['status'] === 'published' && $row['c_status'] === 'active';
            if ($public || ecp_ht_image_viewer_owns((int) $row['identity_id'])) {
                $path = (string) $row['file_path'];
                $mime = (string) $row['file_mime'];
            }
        }
    } elseif (isset($_GET['a'])) {
        $st = $db->prepare('SELECT avatar_path, status, identity_id FROM health_tip_contributors WHERE slug = :s LIMIT 1');
        $st->execute(['s' => (string) $_GET['a']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['avatar_path'])) {
            $public = $row['status'] === 'active';
            if ($public || ecp_ht_image_viewer_owns((int) $row['identity_id'])) {
                $path = (string) $row['avatar_path'];
            }
        }
    }
} catch (Throwable $e) {
    error_log('[api/health_tip_image] ' . $e->getMessage());
}

// Defence-in-depth: only ever serve from the health_tips tree.
$base = realpath(__DIR__ . '/../storage/health_tips') ?: '';
$real = $path ? (realpath(__DIR__ . '/../' . $path) ?: '') : '';
if ($real === '' || $base === '' || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) {
    http_response_code(404);
    exit;
}
if ($mime === null || !isset(ECP_HT_PHOTO_MIMES[$mime])) {
    $mime = mime_content_type($real) ?: 'application/octet-stream';
    if (!isset(ECP_HT_PHOTO_MIMES[$mime])) {
        http_response_code(404);
        exit;
    }
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
// File names are random and never rewritten; a day keeps an unpublished post's
// photos from lingering long in shared caches.
header($public ? 'Cache-Control: public, max-age=86400' : 'Cache-Control: private, no-store');
header('Content-Length: ' . (string) filesize($real));
readfile($real);

/** Is the logged-in patient the contributor who owns this file? */
function ecp_ht_image_viewer_owns(int $identityId): bool
{
    $me = ecp_patient_current();
    return $me !== null && (int) $me['id'] === $identityId;
}
