<?php
// =====================================================================
// store/go.php — /store/go/{id}   (affiliate "Buy" button)
//
// Records one click in store_affiliate_clicks, then 302s to the product's
// saved affiliate_url. Only ids of LIVE affiliate products resolve, and the
// target always comes from the database, so this is not an open redirect.
// Not gated on store_can_view(): the app opens it in the phone's browser,
// which has no preview cookie.
// =====================================================================

require_once __DIR__ . '/_lib.php';

$id = (int) ($_GET['_r_a'] ?? 0);
$db = ecp_db();
$row = null;
if ($id > 0 && $db) {
    try {
        $st = $db->prepare('SELECT p.id, p.affiliate_url ' . store_visible_sql() . ' AND p.id = :id AND p.affiliate_url IS NOT NULL LIMIT 1');
        $st->execute(['id' => $id]);
        $row = $st->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('[store/go] ' . $e->getMessage());
    }
}
// Re-check the scheme (saved via ProductService::cleanAffiliateUrl; never send javascript: etc.).
$url = $row !== null ? trim((string) $row['affiliate_url']) : '';
if ($url === '' || !preg_match('#^https?://[^\s/]+\.[^\s]+$#i', $url) || preg_match('/[\r\n]/', $url)) {
    store_not_found();
}

// Count the click (skip obvious crawlers / link-preview bots).
$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua !== '' && !preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|curl|wget|python|headless/i', $ua)) {
    try {
        $me = ecp_patient_current();
        $db->prepare('INSERT INTO store_affiliate_clicks (product_id, identity_id, source) VALUES (:p, :i, :s)')
            ->execute([
                'p' => (int) $row['id'],
                'i' => $me ? (int) $me['id'] : null,
                's' => ($_GET['src'] ?? '') === 'app' ? 'app' : 'web',
            ]);
    } catch (Throwable $e) {
        error_log('[store/go click] ' . $e->getMessage());   // never block the redirect
    }
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Location: ' . $url, true, 302);
exit;
