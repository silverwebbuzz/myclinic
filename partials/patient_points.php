<?php
// =====================================================================
// partials/patient_points.php — "Points & Refer" tab of /patient.
//
// Server-rendered from PointsService::summary() (patient.php sets
// $pointsPanel). Always "points", never rupees/wallet money.
// Plan: document/store-rewards-plan.md
// =====================================================================

/** @var array<string, mixed> $pointsPanel */
$pp = $pointsPanel;
$ppBal = $pp['balance'];
$ppRules = $pp['rules'];
$ppRef = $pp['referral'];
$ppDate = static fn (?string $d): string => $d ? date('d M Y', strtotime($d)) : '';
$ppKind = static fn (string $k): string => ['welcome' => 'Welcome', 'referral' => 'Referral', 'loyalty' => 'Loyalty', 'admin' => 'Bonus'][$k] ?? 'Points';
$ppTxn = static fn (string $t): string => [
    'earn' => 'Earned', 'available' => 'Ready to use', 'spend' => 'Used on order', 'refund' => 'Returned',
    'expire' => 'Expired', 'reverse' => 'Cancelled', 'adjust' => 'Adjusted',
][$t] ?? $t;
$ppMin = (int) round($ppRules['welcome_min_order_paise'] / 100);
$ppShare = 'Join me on eClinicPro! Sign up with my code ' . $ppRef['code'] . ' and get ' . (int) $ppRules['welcome_points']
    . ' welcome points (worth ₹' . (int) $ppRules['welcome_points'] . ' off your first ₹' . $ppMin . '+ order) on the eClinicPro Store: ' . $ppRef['link'];
?>
<div x-show="tab === 'points'" class="pt-tab-pane">
  <div class="pt-section-head">
    <h3>Points &amp; Refer</h3>
    <a href="/store/" class="pt-counter" style="text-decoration:none">Shop now →</a>
  </div>

  <div class="pp-stats">
    <div class="pp-stat pp-stat-main">
      <div class="pp-stat-num"><?= (int) $ppBal['available'] ?></div>
      <div class="pp-stat-lbl">points ready to use</div>
    </div>
    <div class="pp-stat">
      <div class="pp-stat-num"><?= (int) $ppBal['pending'] ?></div>
      <div class="pp-stat-lbl">points pending</div>
    </div>
  </div>
  <p class="pt-section-note pp-rules">
    1 point = ₹1 off on the eClinicPro Store, applied automatically at checkout.
    Welcome points are used together on an order of ₹<?= $ppMin ?> or more.
    Referral and loyalty points cover up to <?= (int) $ppRules['redeem_cap_pct'] ?>% of an order.
    An order that uses no points earns <?= (int) $ppRules['loyalty_pct'] ?>% back <?= (int) $ppRules['return_window_days'] ?> days after delivery.
    Points expire <?= (int) $ppRules['expiry_days'] ?> days after they become usable.
  </p>

  <?php if ($pp['lots']): ?>
    <div class="pp-list">
      <?php foreach ($pp['lots'] as $lot): ?>
        <div class="pp-row">
          <span class="pp-kind pp-kind-<?= e($lot['kind']) ?>"><?= e($ppKind($lot['kind'])) ?></span>
          <div class="pp-row-text">
            <?php if ($lot['status'] === 'available'): ?>
              <strong>Ready to use</strong>
              <span><?= $lot['expires_at'] ? 'Expires ' . e($ppDate($lot['expires_at'])) : '' ?></span>
            <?php else: ?>
              <strong>Pending</strong>
              <span><?= e((string) ($lot['note'] ?? '')) ?><?= $lot['available_at'] ? ' · from ' . e($ppDate($lot['available_at'])) : '' ?></span>
            <?php endif; ?>
          </div>
          <span class="pp-pts <?= $lot['status'] === 'pending' ? 'is-pending' : '' ?>">+<?= (int) $lot['points'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="pt-empty">
      <div class="glyph">⭐</div>
      <h3>No points yet</h3>
      <p>Shop on the eClinicPro Store to earn <?= (int) $ppRules['loyalty_pct'] ?>% back as points, or refer a friend below.</p>
    </div>
  <?php endif; ?>

  <div class="pp-refer">
    <h3>Refer &amp; Earn</h3>
    <p class="pp-refer-lede">
      Your friend signs up with your code. When their first store order is delivered, you <strong>both</strong> get
      <?= (int) $ppRules['referral_points'] ?> points (usable after the <?= (int) $ppRules['return_window_days'] ?>-day return window).
      There's no limit on how many friends you refer.
    </p>
    <?php if ($ppRef['active']): ?>
      <div class="pp-code" x-data="{ copied: false }">
        <span class="pp-code-val"><?= e($ppRef['code']) ?></span>
        <button type="button" class="btn-mini"
          @click="navigator.clipboard && navigator.clipboard.writeText(<?= e(json_encode($ppRef['link'])) ?>).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
          x-text="copied ? 'Link copied' : 'Copy link'">Copy link</button>
        <a class="btn-mini primary" target="_blank" rel="noopener"
          href="https://wa.me/?text=<?= e(rawurlencode($ppShare)) ?>">Share on WhatsApp</a>
      </div>
    <?php else: ?>
      <div class="pp-locked">
        🔒 Your referral code unlocks after your first store order is delivered.
        <a href="/store/">Start shopping</a>
      </div>
    <?php endif; ?>

    <?php if ($ppRef['friends']): ?>
      <div class="pp-list">
        <?php foreach ($ppRef['friends'] as $f): ?>
          <div class="pp-row">
            <div class="pt-avatar"><?= e(mb_strtoupper(mb_substr($f['name'], 0, 1))) ?></div>
            <div class="pp-row-text">
              <strong><?= e($f['name']) ?></strong>
              <span>Joined <?= e($ppDate($f['joined_at'])) ?></span>
            </div>
            <span class="pp-chip pp-chip-<?= e($f['status']) ?>"><?= e([
                'signed_up' => 'Waiting for first order', 'qualified' => 'Delivered · in return window',
                'rewarded' => 'Points given', 'rejected' => 'Not eligible',
            ][$f['status']] ?? $f['status']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($pp['history']): ?>
    <details class="pp-history">
      <summary>Points history</summary>
      <div class="pp-list">
        <?php foreach ($pp['history'] as $t): ?>
          <div class="pp-row">
            <div class="pp-row-text">
              <strong><?= e($ppTxn($t['type'])) ?> · <?= e($ppKind($t['kind'])) ?></strong>
              <span><?= e($ppDate($t['at'])) ?><?= $t['order_no'] ? ' · ' . e($t['order_no']) : '' ?></span>
            </div>
            <span class="pp-pts <?= $t['points'] < 0 ? 'is-minus' : '' ?>"><?= $t['points'] > 0 ? '+' : '' ?><?= (int) $t['points'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>

  <p class="pp-terms">
    Points are a discount on eClinicPro Store orders only. They have no cash value and can't be withdrawn,
    bought or transferred. eClinicPro may change or end the programme; see the <a href="/terms#points">terms</a>.
  </p>
</div>

<style>
  .pp-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
  .pp-stat { border: 1px solid var(--line); border-radius: 16px; padding: 16px 18px; background: #fff; }
  .pp-stat-main { background: var(--teal-50); border-color: var(--teal-100); }
  .pp-stat-num { font-size: 28px; font-weight: 700; letter-spacing: -0.5px; color: var(--ink); line-height: 1.1; }
  .pp-stat-main .pp-stat-num { color: var(--teal-800); }
  .pp-stat-lbl { font-size: 13px; color: var(--mute); margin-top: 2px; }
  .pp-rules { font-size: 13px; line-height: 1.55; }
  .pp-list { display: flex; flex-direction: column; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; margin: 12px 0; background: #fff; }
  .pp-row { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-top: 1px solid var(--line-2); }
  .pp-row:first-child { border-top: 0; }
  .pp-row-text { flex: 1; min-width: 0; display: flex; flex-direction: column; }
  .pp-row-text strong { font-size: 14px; font-weight: 600; color: var(--ink); }
  .pp-row-text span { font-size: 12.5px; color: var(--mute); overflow-wrap: anywhere; }
  .pp-kind { flex: none; min-width: 64px; text-align: center; font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: var(--bg-2); color: var(--ink-2); }
  .pp-kind-welcome { background: var(--teal-50); color: var(--teal-800); }
  .pp-kind-referral { background: var(--blue-50); color: var(--blue-600); }
  .pp-kind-loyalty { background: #FFF4E0; color: #8A5300; }
  .pp-pts { flex: none; font-weight: 700; font-size: 15px; color: var(--teal-700); }
  .pp-pts.is-pending { color: var(--mute); }
  .pp-pts.is-minus { color: var(--ink-2); }
  .pp-refer { margin-top: 22px; padding: 18px; border: 1px solid var(--teal-100); border-radius: 16px; background: linear-gradient(180deg, var(--teal-50), #fff 70%); }
  .pp-refer h3 { font-size: 16px; font-weight: 600; margin: 0 0 6px; }
  .pp-refer-lede { font-size: 13.5px; color: var(--ink-2); line-height: 1.55; margin: 0 0 12px; }
  .pp-code { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
  .pp-code-val { font: 700 18px/1 ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: 1px; padding: 10px 14px; border: 1px dashed var(--teal-600); border-radius: 10px; background: #fff; color: var(--teal-800); }
  .pp-locked { font-size: 13.5px; color: var(--ink-2); padding: 12px 14px; background: #fff; border: 1px solid var(--line); border-radius: 12px; }
  .pp-locked a { color: var(--teal-700); font-weight: 600; margin-left: 4px; }
  .pp-chip { flex: none; font-size: 11.5px; font-weight: 600; padding: 4px 10px; border-radius: 999px; background: var(--bg-2); color: var(--mute); text-align: right; }
  .pp-chip-qualified { background: var(--blue-50); color: var(--blue-600); }
  .pp-chip-rewarded { background: var(--teal-50); color: var(--teal-800); }
  .pp-history { margin-top: 18px; }
  .pp-history summary { cursor: pointer; font-size: 14px; font-weight: 600; color: var(--ink-2); }
  .pp-terms { font-size: 12px; color: var(--mute); line-height: 1.5; margin-top: 18px; }
  .pp-terms a { color: var(--teal-700); }
  @media (max-width: 480px) {
    .pp-stat { padding: 12px 14px; }
    .pp-stat-num { font-size: 24px; }
    .pp-row { flex-wrap: wrap; }
    .pp-row-text { flex: 1 1 150px; }   /* a wide status chip drops to its own line instead of squeezing the name */
    .pp-chip { margin-left: 46px; }
  }
</style>
