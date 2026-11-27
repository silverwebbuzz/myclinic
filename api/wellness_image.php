<?php
// =====================================================================
// api/wellness_image.php — public Wellness Hub images.
//
//   GET /api/wellness_image?e={eventId}      camp / event poster (published or cancelled, creator active)
//   GET /api/wellness_image?c={creatorSlug}  creator photo / logo (active creators)
//
// Files live outside the web root's reach (storage/ is denied by .htaccess);
// this endpoint only ever serves from the two allowed trees.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../partials/db.php';

const WE_IMG_MIMES = ['image/jpeg' => true, 'image/png' => true, 'image/webp' => true];

$path = null;
$tree = null;
$db = ecp_db();
try {
    if ($db && isset($_GET['e'])) {
        $st = $db->prepare(
            "SELECT e.poster_path FROM wh_events e JOIN wh_creators w ON w.id = e.creator_id
             WHERE e.id = :id AND e.status IN ('published','cancelled') AND e.is_deleted = 0 AND w.status = 'active' LIMIT 1"
        );
        $st->execute(['id' => (int) $_GET['e']]);
        $path = $st->fetchColumn() ?: null;
        $tree = 'storage/wellness/posters';
    } elseif ($db && isset($_GET['c']) && preg_match('/^[a-z0-9\-]{1,80}$/', (string) $_GET['c'])) {
        $st = $db->prepare("SELECT avatar_path FROM wh_creators WHERE slug = :s AND status = 'active' LIMIT 1");
        $st->execute(['s' => (string) $_GET['c']]);
        $path = $st->fetchColumn() ?: null;
        $tree = 'storage/health_tips';
    }
} catch (Throwable $e) {
    error_log('[api/wellness_image] ' . $e->getMessage());
}

$base = $tree ? (realpath(__DIR__ . '/../' . $tree) ?: '') : '';
$real = $path ? (realpath(__DIR__ . '/../' . $path) ?: '') : '';
if ($base === '' || $real === '' || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) {
    http_response_code(404);
    exit;
}
$mime = mime_content_type($real) ?: '';
if (!isset(WE_IMG_MIMES[$mime])) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($real));
readfile($real);
