<?php
// =====================================================================
// api/mobile/v1/app_version.php — "is there an app update?" (public).
//
//   GET ?platform=android|ios&build=7
//     build = the app's integer build number (the 7 in Flutter's 1.2.0+7).
//     → { ok, platform, update: none|optional|required, latest_version,
//         latest_build, min_build, store_url, title, message, notes[] }
//
// Rules (App\Support\AppUpdatePolicy, shared with the admin preview):
//   build < min_build    → required   (app shows a blocking dialog)
//   build < latest_build → optional   (dismissible dialog)
//   otherwise            → none
// Nothing configured for the platform (no latest build or no store URL) →
// always "none", so empty settings can never block the app.
//
// Admin: /admin/app-versions (platform_settings app_{platform}_*).
// Errors: 400 build_required · 400 invalid_platform · 405 method_not_allowed.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/app/Support/AppUpdatePolicy.php';

use App\Support\AppUpdatePolicy;

// Public and identical for every caller with the same query string: let a
// CDN/proxy cache it briefly, so an admin change is live within 5 minutes.
// (_bootstrap sends no-store; this replaces it.)
header('Cache-Control: public, max-age=300');

ecp_m_require_method('GET');

$platform = strtolower(trim((string) ($_GET['platform'] ?? '')));
if (!isset(AppUpdatePolicy::PLATFORMS[$platform])) {
    ecp_m_err('invalid_platform', 400, ['message' => 'platform must be android or ios.']);
}
$build = AppUpdatePolicy::posInt((string) ($_GET['build'] ?? ''));
if ($build === null) {
    ecp_m_err('build_required', 400, ['message' => 'build must be the app\'s build number, a whole number of 1 or more.']);
}

// DB down / settings missing → empty map → update "none" (never block the app).
$db = ecp_db();
$settings = $db ? ecp_platform_settings($db, AppUpdatePolicy::keys()) : [];

ecp_m_ok(AppUpdatePolicy::answer($settings, $platform, $build));
