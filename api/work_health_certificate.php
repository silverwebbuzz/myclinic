<?php
// =====================================================================
// api/work_health_certificate.php?id=N — the signed-in person's own fitness
// certificate PDF (Workforce Health, Phase 1C). Auth: ecp_pid cookie; only
// certificates of their own CONFIRMED employment links (no IDOR).
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/work_health.php';

$me = ecp_patient_current();
if (!$me) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Please sign in again.';
    exit;
}
ecp_wh_certificate_download((int) $me['id'], (int) ($_GET['id'] ?? 0));
