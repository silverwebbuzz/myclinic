<?php
// =====================================================================
// r.php — Refer & Earn link. URL: /r/{code}  (.htaccess → r.php?code=…)
//
// Remembers the friend's code for 30 days (cookie ecp_ref) and sends the
// visitor to sign up. The code is attached ONLY if they create a NEW
// account (ReferralService::capture via store_referral_capture()).
// =====================================================================

declare(strict_types=1);

$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
if (preg_match('/^ECP-[A-Z]{1,6}[2-9A-Z]{3}$/', $code)) {
    setcookie('ecp_ref', $code, [
        'expires' => time() + 30 * 86400,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
header('Cache-Control: no-store');
header('Location: /patient', true, 302);
exit;
