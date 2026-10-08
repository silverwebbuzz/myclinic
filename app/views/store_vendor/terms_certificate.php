<?php
/**
 * Printable record of the seller terms a seller accepted (/vendor/terms/certificate and
 * /admin/store/vendors/{id}/terms-certificate). "Save as PDF" from the browser's print dialog.
 *
 * @var array{acceptance: array<string,mixed>, version: array<string,mixed>, html: string} $cert
 * @var array<string,mixed> $vendor
 * @var array<string,mixed>|null $pickup
 * @var array{name: string, gstin: string, address: string} $platform
 * @var string $backUrl
 */
use App\Support\IndianDate;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$a = $cert['acceptance'];
$ver = $cert['version'];
$addr = $pickup !== null
    ? implode(', ', array_filter([$pickup['line1'] ?? '', $pickup['line2'] ?? '', $pickup['city'] ?? '', $pickup['state'] ?? '', $pickup['pincode'] ?? '']))
    : '';
$types = ['proprietorship' => 'Proprietorship', 'partnership' => 'Partnership', 'llp' => 'LLP', 'pvt_ltd' => 'Private limited company',
    'public_ltd' => 'Public limited company', 'other' => 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seller agreement v<?= (int) $ver['version'] ?> · <?= $e($vendor['legal_name'] ?: $vendor['display_name']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; color: #0f172a; font: 14px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; }
        .bar { max-width: 820px; margin: 16px auto 0; padding: 0 16px; display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
        .bar a, .bar button { font: inherit; font-size: 13px; padding: 7px 14px; border-radius: 7px; border: 1px solid #cbd5e1; background: #fff; color: #0f172a; cursor: pointer; text-decoration: none; }
        .bar button { background: #0e4d34; border-color: #0e4d34; color: #fff; }
        .doc { max-width: 820px; margin: 12px auto 32px; background: #fff; padding: 40px 44px; border: 1px solid #e2e8f0; border-radius: 10px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: #475569; margin: 0 0 20px; font-size: 13px; }
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }
        .box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; font-size: 13px; }
        .box b { display: block; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
        .record { background: #f0fdf4; border-color: #bbf7d0; }
        .record dl { display: grid; grid-template-columns: 150px 1fr; gap: 3px 10px; margin: 0; }
        .record dt { color: #475569; }
        .record dd { margin: 0; font-weight: 500; word-break: break-word; }
        .policy h2 { font-size: 15px; margin: 18px 0 6px; color: #0e4d34; break-after: avoid; }
        .policy h3 { font-size: 14px; margin: 12px 0 4px; }
        .policy p { margin: 6px 0; }
        .policy ul, .policy ol { padding-left: 20px; margin: 6px 0; }
        .policy li { margin: 3px 0; }
        .foot { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; }
        @media (max-width: 640px) { .doc { padding: 22px 16px; } .parties { grid-template-columns: 1fr; } .record dl { grid-template-columns: 1fr; } }
        @media print { body { background: #fff; } .bar { display: none; } .doc { border: 0; margin: 0; padding: 0; max-width: none; } }
    </style>
</head>
<body>
<div class="bar">
    <a href="<?= $e($backUrl) ?>">← Back</a>
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
</div>
<main class="doc">
    <h1>eClinicPro Store: <?= $e($ver['title']) ?></h1>
    <p class="sub">Version <?= (int) $ver['version'] ?><?= !empty($ver['effective_at']) ? ', effective ' . $e(IndianDate::date($ver['effective_at'])) : '' ?> · Accepted electronically under the Information Technology Act, 2000</p>

    <div class="parties">
        <div class="box">
            <b>Marketplace operator</b>
            <strong><?= $e($platform['name']) ?></strong> (brand: eClinicPro)<br>
            <?= $platform['gstin'] !== '' ? 'GSTIN ' . $e($platform['gstin']) . '<br>' : '' ?>
            <?= $e($platform['address']) ?>
        </div>
        <div class="box">
            <b>Seller</b>
            <strong><?= $e($vendor['legal_name'] ?: $vendor['display_name']) ?></strong><?= $vendor['legal_name'] && $vendor['legal_name'] !== $vendor['display_name'] ? ' (store: ' . $e($vendor['display_name']) . ')' : '' ?><br>
            <?= !empty($vendor['business_type']) ? $e($types[$vendor['business_type']] ?? $vendor['business_type']) . '<br>' : '' ?>
            <?= !empty($vendor['gstin']) ? 'GSTIN ' . $e($vendor['gstin']) . '<br>' : '' ?>
            <?= !empty($vendor['pan_last4']) ? 'PAN ending ' . $e($vendor['pan_last4']) . '<br>' : '' ?>
            <?= $e($addr) ?>
        </div>
    </div>

    <div class="box record">
        <b>Acceptance record</b>
        <dl>
            <dt>Accepted on</dt><dd><?= $e(IndianDate::dateTime($a['accepted_at'])) ?></dd>
            <dt>Accepted by</dt><dd><?= $e(trim(($a['user_name'] ?? '') . ' ' . (!empty($a['user_email']) ? '<' . $a['user_email'] . '>' : ''))) ?: '—' ?>, on behalf of the seller</dd>
            <dt>IP address</dt><dd><?= $e($a['ip_text'] ?: '—') ?></dd>
            <dt>How</dt><dd>Ticked "I agree" in the eClinicPro seller portal after the full text below was shown</dd>
            <dt>Reference</dt><dd>SPA-<?= (int) $a['id'] ?> / seller <?= (int) $vendor['id'] ?> / v<?= (int) $ver['version'] ?></dd>
        </dl>
    </div>

    <article class="policy"><?= $cert['html'] ?></article>

    <p class="foot">This copy was generated from eClinicPro's records on <?= $e(IndianDate::dateTime(time())) ?>. The text above is the exact version the seller accepted; numbers shown in it (time limits, fees) are the current settings.</p>
</main>
</body>
</html>
