<?php
/**
 * Printable GST document (tax invoice / bill of supply / credit note).
 * Shared by the customer order page, the seller portal and admin.
 * Standalone page: no app layout, prints cleanly on A4.
 *
 * @var array<string, mixed> $doc  TaxDocumentService::load()
 * @var string|null $backUrl
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rs = static fn (int $p): string => '₹' . number_format($p / 100, 2);
$title = \App\Services\Store\TaxDocumentService::title($doc);
$isCn = $doc['doc_type'] === 'credit_note';
$intra = $doc['supply_type'] === 'intra';
$bos = (int) $doc['is_bill_of_supply'] === 1;
$iss = $doc['issuer_party'];
$buy = $doc['buyer_party'];
$reasons = ['return' => 'Goods returned', 'cancel' => 'Cancelled after invoice', 'rto' => 'Undelivered: returned to seller',
    'lost' => 'Lost in transit', 'damaged' => 'Damaged in transit'];
$pos = (string) ($doc['place_of_supply'] ?? '');
$ecoGstin = \App\Services\Store\StoreSettings::get('store_platform_gstin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $h($title . ' ' . $doc['doc_no']) ?></title>
<style>
  :root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f1f5f9; }
  * { box-sizing: border-box; }
  body { margin:0; background:var(--bg); color:var(--ink); font:14px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  .bar { max-width:900px; margin:16px auto 0; padding:0 16px; display:flex; gap:8px; justify-content:space-between; }
  .bar a, .bar button { font:inherit; font-size:13px; padding:7px 12px; border:1px solid var(--line); border-radius:6px; background:#fff; color:var(--ink); text-decoration:none; cursor:pointer; }
  .page { max-width:900px; margin:12px auto 32px; background:#fff; padding:28px; border:1px solid var(--line); border-radius:8px; }
  .head { display:flex; flex-wrap:wrap; justify-content:space-between; gap:16px; border-bottom:2px solid var(--ink); padding-bottom:14px; }
  h1 { margin:0; font-size:22px; letter-spacing:.02em; text-transform:uppercase; }
  .meta { text-align:right; font-size:13px; }
  .meta div { margin-top:2px; }
  .parties { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin:18px 0; }
  .parties h2 { margin:0 0 4px; font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }
  .parties p { margin:0; }
  .wrap { overflow-x:auto; }
  table { width:100%; border-collapse:collapse; font-size:13px; min-width:640px; }
  th, td { border:1px solid var(--line); padding:6px 8px; vertical-align:top; }
  th { background:#f8fafc; font-weight:600; text-align:left; font-size:12px; }
  td.n, th.n { text-align:right; white-space:nowrap; }
  tfoot td { font-weight:600; }
  .note { margin-top:16px; font-size:12px; color:var(--muted); }
  .cn { display:inline-block; margin-top:6px; padding:2px 8px; border-radius:4px; background:#fef3c7; color:#92400e; font-size:12px; }
  @media (max-width:600px) { .page { padding:16px; } .parties { grid-template-columns:1fr; } .meta { text-align:left; } }
  @media print { body { background:#fff; } .bar { display:none; } .page { border:0; margin:0; max-width:none; padding:0; } }
</style>
</head>
<body>
<div class="bar">
  <?php if (!empty($backUrl)): ?><a href="<?= $h($backUrl) ?>">← Back</a><?php else: ?><span></span><?php endif; ?>
  <button type="button" onclick="window.print()">Print / save as PDF</button>
</div>
<main class="page">
  <div class="head">
    <div>
      <h1><?= $h($title) ?></h1>
      <?php if ($isCn && $doc['refers_to']): ?>
        <span class="cn">Against invoice <?= $h($doc['refers_to']['doc_no']) ?> dated <?= $h(date('d M Y', (int) strtotime((string) $doc['refers_to']['issued_at']))) ?></span>
      <?php endif; ?>
    </div>
    <div class="meta">
      <div><strong>No. <?= $h($doc['doc_no']) ?></strong></div>
      <div>Date: <?= $h(date('d M Y', (int) strtotime((string) $doc['issued_at']))) ?></div>
      <div>Order: <?= $h($doc['order_no']) ?> · Package <?= $h($doc['sub_order_no']) ?></div>
      <?php if ($isCn && !empty($doc['reason'])): ?><div>Reason: <?= $h($reasons[$doc['reason']] ?? ucfirst((string) $doc['reason'])) ?></div><?php endif; ?>
    </div>
  </div>

  <div class="parties">
    <div>
      <h2><?= $doc['issuer'] === 'platform' ? 'Supplier (delivery service)' : 'Sold by' ?></h2>
      <p><strong><?= $h($iss['legal_name'] ?? '') ?></strong><?= !empty($iss['trade_name']) && ($iss['trade_name'] !== ($iss['legal_name'] ?? '')) ? ' (' . $h($iss['trade_name']) . ')' : '' ?></p>
      <p><?= $h($iss['address'] ?? '') ?></p>
      <?php if (!empty($iss['gstin'])): ?><p>GSTIN: <strong><?= $h($iss['gstin']) ?></strong></p><?php endif; ?>
      <p>State: <?= $h(($iss['state'] ?? '') . (!empty($iss['state_code']) ? ' (' . $iss['state_code'] . ')' : '')) ?></p>
    </div>
    <div>
      <h2>Bill to / ship to</h2>
      <p><strong><?= $h($buy['name'] ?? '') ?></strong></p>
      <p><?= $h($buy['address'] ?? '') ?></p>
      <p>Place of supply: <?= $h(\App\Services\Store\GstStates::name($pos) ?: ($buy['state'] ?? '')) ?><?= $pos !== '' ? ' (' . $h($pos) . ')' : '' ?></p>
    </div>
  </div>

  <div class="wrap">
  <table>
    <thead>
      <tr>
        <th>#</th><th>Description</th><th><?= $doc['issuer'] === 'platform' ? 'SAC' : 'HSN' ?></th><th class="n">Qty</th>
        <?php if (!$bos): ?><th class="n">GST %</th><?php endif; ?>
        <th class="n"><?= $bos ? 'Value' : 'Taxable value' ?></th>
        <?php if (!$bos): ?>
          <?php if ($intra): ?><th class="n">CGST</th><th class="n">SGST</th><?php else: ?><th class="n">IGST</th><?php endif; ?>
        <?php endif; ?>
        <th class="n">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($doc['lines'] as $i => $l): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= $h($l['description']) ?></td>
          <td><?= $h($l['hsn_sac'] ?? '—') ?></td>
          <td class="n"><?= (int) $l['qty'] ?></td>
          <?php if (!$bos): ?><td class="n"><?= $h(rtrim(rtrim(number_format((int) $l['gst_bp'] / 100, 2), '0'), '.')) ?>%</td><?php endif; ?>
          <td class="n"><?= $rs((int) $l['taxable_paise']) ?></td>
          <?php if (!$bos): ?>
            <?php if ($intra): ?>
              <td class="n"><?= $rs((int) $l['cgst_paise']) ?></td><td class="n"><?= $rs((int) $l['sgst_paise']) ?></td>
            <?php else: ?>
              <td class="n"><?= $rs((int) $l['igst_paise']) ?></td>
            <?php endif; ?>
          <?php endif; ?>
          <td class="n"><?= $rs((int) $l['total_paise']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="<?= $bos ? 4 : 5 ?>"><?= $isCn ? 'Total credited' : 'Total' ?></td>
        <td class="n"><?= $rs((int) $doc['taxable_paise']) ?></td>
        <?php if (!$bos): ?>
          <?php if ($intra): ?>
            <td class="n"><?= $rs((int) $doc['cgst_paise']) ?></td><td class="n"><?= $rs((int) $doc['sgst_paise']) ?></td>
          <?php else: ?>
            <td class="n"><?= $rs((int) $doc['igst_paise']) ?></td>
          <?php endif; ?>
        <?php endif; ?>
        <td class="n"><?= $rs((int) $doc['total_paise']) ?></td>
      </tr>
    </tfoot>
  </table>
  </div>

  <p class="note">
    <?php if ($bos): ?>Supplier not registered under GST: no tax charged.<?php else: ?>Prices are inclusive of GST. Tax payable on reverse charge: No.<?php endif; ?>
    <?php if ($doc['issuer'] === 'vendor'): ?>
      Sold through eClinicPro Store (e-commerce operator)<?= $ecoGstin !== '' ? ', GSTIN ' . $h($ecoGstin) : '' ?>.
      <?php if (!$isCn): ?>Any discount funded by eClinicPro is not a discount by the seller and is shown on your order page, not on this invoice.<?php endif; ?>
    <?php endif; ?>
    This is a computer-generated document and needs no signature.
  </p>
</main>
</body>
</html>
