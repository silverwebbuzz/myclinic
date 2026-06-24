<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/ProfileController.php';

$result = ProfileController::show(
    (string) ($_GET['city'] ?? ''),
    (string) ($_GET['entity_type'] ?? ''),
    (string) ($_GET['slug'] ?? '')
);

if (!$result['ok']) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$p = $result['profile'];
$activePage = 'find';
$hideFinalCta = true;
$pageTitle = (string) ($p['meta_title'] ?? 'Profile — eClinicPro');
$metaDesc = (string) ($p['meta_description'] ?? '');
$canonicalUrl = ecp_site_url((string) ($p['canonical'] ?? ''));
$hoursLines = ecp_profile_hours_lines($p['opening_hours'] ?? null);
$showDoctorsTab = ($p['entity_type'] ?? '') === 'clinic';

require_once __DIR__ . '/partials/book_bridge.php';
$bookingCtx = ecp_profile_booking_context($p);
$profileBrand = htmlspecialchars((string) ($bookingCtx['brandColor'] ?? '#0F9B6E'), ENT_QUOTES, 'UTF-8');
$extraHead = '<link rel="stylesheet" href="/assets/css/profile-booking.css?v=2">';
if (($bookingCtx['mode'] ?? '') === 'claimed') {
    $extraHead .= '<script>tailwind.config={important:"#dp-book-widget",corePlugins:{preflight:false}}</script>'
        . '<script src="https://cdn.tailwindcss.com"></script>'
        . '<style>#dp-book-widget{--brand:' . $profileBrand . ';}[x-cloak]{display:none!important}</style>';
}

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/views/directory-profile.php';
require __DIR__ . '/partials/footer.php';
