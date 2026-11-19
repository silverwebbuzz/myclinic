<?php
// =====================================================================
// partials/patient_work_health.php — "Work health" tab of /patient.
//
// Shown only when the person has a pending or confirmed link with an
// approved company (patient.php sets $workItems from ecp_wh_list()).
// Pending: "X says you work there. Is this you?" → confirm / decline.
// Confirmed: employer, employee no., job, branch. Screening schedule and
// fitness certificates arrive in Phase 1B / 1C.
// =====================================================================

/** @var list<array<string, mixed>> $workItems */
$whE = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$whDate = static fn (?string $utc): string => $utc ? date('d M Y', strtotime($utc . ' UTC')) : '';
$whDay = static fn (?string $ymd): string => $ymd ? date('d M Y', strtotime($ymd)) : '';   // plain DATE columns
$whTypes = ['contractor' => 'Contractor', 'temporary' => 'Temporary', 'intern' => 'Intern / trainee'];
?>
<div x-show="tab === 'work'" class="pt-tab-pane" x-data="{
       busy: 0, error: '',
       async answer(id, ans) {
         if (ans === 'decline' && !confirm('Tell this company the record isn\'t you? They will be asked to check the mobile number.')) return;
         this.busy = id; this.error = '';
         try {
           const r = await fetch('/api/work_health', { method: 'POST', credentials: 'same-origin',
             headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ id: id, answer: ans }) });
           const j = await r.json();
           if (!j.ok) throw new Error(j.error || 'failed');
           window.location.href = '/patient?tab=work';
         } catch (e) { this.error = 'Something went wrong. Please try again.'; this.busy = 0; }
       } }">
  <div class="pt-section-head">
    <h3>Work health</h3>
  </div>
  <p class="pt-section-note">Health checks your employer needs for your job. Your employer sees only what they entered and, later, whether you are fit for work. Your own health records stay private to you.</p>
  <p class="wh-err" x-show="error" x-text="error"></p>

  <div class="wh-list">
  <?php foreach ($workItems as $w): $id = (int) $w['id']; ?>
    <?php if ($w['link_status'] === 'pending'): ?>
      <div class="wh-card wh-pending">
        <div class="wh-badge">Please confirm</div>
        <p class="wh-ask"><strong><?= $whE($w['company_name']) ?></strong> says you work there<?= !empty($w['job_title']) ? ' as <strong>' . $whE($w['job_title']) . '</strong>' : '' ?>.</p>
        <dl class="wh-facts">
          <div><dt>Name on their record</dt><dd><?= $whE($w['full_name']) ?></dd></div>
          <div><dt>Employee no.</dt><dd><?= $whE($w['employee_no']) ?></dd></div>
          <?php if (!empty($w['branch_name'])): ?><div><dt>Workplace</dt><dd><?= $whE(trim($w['branch_name'] . (!empty($w['branch_city']) ? ', ' . $w['branch_city'] : ''))) ?></dd></div><?php endif; ?>
          <?php if (isset($whTypes[$w['employment_type']])): ?><div><dt>Type</dt><dd><?= $whE($whTypes[$w['employment_type']]) ?><?= !empty($w['contractor_company']) ? ' (' . $whE($w['contractor_company']) . ')' : '' ?></dd></div><?php endif; ?>
        </dl>
        <p class="wh-small">Is this you? If you confirm, they can arrange your work health checks through eClinicPro. They will not see your personal health records.</p>
        <div class="wh-actions">
          <button type="button" class="btn-mini primary" :disabled="busy" @click="answer(<?= $id ?>, 'confirm')">Yes, that's me</button>
          <button type="button" class="btn-mini" :disabled="busy" @click="answer(<?= $id ?>, 'decline')">This isn't me</button>
        </div>
      </div>
    <?php else: ?>
      <div class="wh-card">
        <div class="wh-head">
          <div>
            <div class="wh-company"><?= $whE($w['company_name']) ?></div>
            <div class="wh-sub"><?= $whE(implode(' · ', array_filter([$w['job_title'] ?? '', $w['department_name'] ?? '', $w['branch_name'] ?? '']))) ?></div>
          </div>
          <span class="wh-ok"><?= $w['status'] === 'on_leave' ? 'On leave' : 'Linked' ?></span>
        </div>
        <dl class="wh-facts">
          <div><dt>Employee no.</dt><dd><?= $whE($w['employee_no']) ?></dd></div>
          <?php if (isset($whTypes[$w['employment_type']])): ?><div><dt>Type</dt><dd><?= $whE($whTypes[$w['employment_type']]) ?></dd></div><?php endif; ?>
          <?php if (!empty($w['link_confirmed_at'])): ?><div><dt>Confirmed</dt><dd><?= $whE($whDate($w['link_confirmed_at'])) ?></dd></div><?php endif; ?>
        </dl>
        <?php if (!empty($w['bookings'])): ?>
          <div class="wh-book-title">Booked health checks</div>
          <?php foreach ($w['bookings'] as $bk): ?>
            <div class="wh-book">
              <div class="wh-book-when"><?= $whE(date('D, d M Y', strtotime((string) $bk['scheduled_at']))) ?> · <?= $whE(date('h:i A', strtotime((string) $bk['scheduled_at']))) ?></div>
              <div><strong><?= $whE($bk['clinic_name']) ?></strong><?= !empty($bk['clinic_phone']) ? ' · <a href="tel:' . $whE(preg_replace('/[^0-9+]/', '', (string) $bk['clinic_phone'])) . '">' . $whE($bk['clinic_phone']) . '</a>' : '' ?></div>
              <?php if (!empty($bk['clinic_address'])): ?><div class="wh-sub"><?= $whE($bk['clinic_address']) ?></div><?php endif; ?>
              <?php if (!empty($bk['checks'])): ?><div class="wh-sub">Checks: <?= $whE($bk['checks']) ?></div><?php endif; ?>
            </div>
          <?php endforeach; ?>
          <p class="wh-small">Bring a photo ID. To change the time, ask your HR team.</p>
        <?php else: ?>
        <?php if (empty($w['certificates'])): ?><div class="wh-empty">No health check booked right now. When your employer books one, the clinic, date and time appear here, and afterwards your fitness certificate.</div><?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($w['certificates'])): ?>
          <div class="wh-book-title">Fitness certificates</div>
          <?php foreach ($w['certificates'] as $fc): $fcR = array_map(static fn ($k) => ECP_WH_RESTRICTIONS[$k] ?? $k, json_decode((string) ($fc['restrictions_json'] ?? '[]'), true) ?: []);
              if (!empty($fc['restrictions_note'])) { $fcR[] = (string) $fc['restrictions_note']; }
              $fcExpired = !empty($fc['valid_to']) && (string) $fc['valid_to'] < date('Y-m-d'); $fcSnap = json_decode((string) ($fc['checks_json'] ?? ''), true) ?: []; ?>
            <div class="wh-cert <?= $fc['status'] !== 'valid' || $fcExpired ? 'wh-cert-old' : '' ?>">
              <div class="wh-head" style="margin-bottom:4px">
                <div class="wh-cert-out wh-out-<?= $whE($fc['outcome']) ?>"><?= $whE(ECP_WH_OUTCOMES[$fc['outcome']] ?? $fc['outcome']) ?></div>
                <?php if ($fc['status'] === 'revoked'): ?><span class="wh-tag">Withdrawn by the doctor</span><?php elseif ($fcExpired): ?><span class="wh-tag">Expired</span><?php endif; ?>
              </div>
              <?php if ($fcR !== []): ?><div class="wh-sub">Restrictions: <?= $whE(implode('; ', $fcR)) ?></div><?php endif; ?>
              <div class="wh-sub"><?= !empty($fc['valid_to']) ? 'Valid to ' . $whE($whDay($fc['valid_to'])) : (!empty($fc['review_by']) ? 'Review by ' . $whE($whDay($fc['review_by'])) : '') ?>
                · examined <?= $whE($whDay($fc['exam_date'])) ?><?= !empty($fcSnap['issuer']['clinic']) ? ' at ' . $whE($fcSnap['issuer']['clinic']) : '' ?></div>
              <div class="wh-sub"><?= (int) $fc['consent_shared'] === 1 ? 'Shared with your employer.' : 'Not shared with your employer: they only know you attended.' ?></div>
              <?php if ((int) $fc['has_pdf'] === 1 && $fc['status'] === 'valid'): ?>
                <div class="wh-actions"><a class="btn-mini primary" href="/api/work_health_certificate.php?id=<?= (int) $fc['id'] ?>" target="_blank" rel="noopener">Download certificate (PDF)</a></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <p class="wh-small">Details wrong, or you've left? Please tell your HR team; they keep this record.</p>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
  </div>
</div>
<style>
  .wh-list { display: grid; gap: 14px; }
  .wh-card { border: 1px solid #e4e7ec; border-radius: 12px; padding: 16px 18px; background: #fff; }
  .wh-pending { border-color: #f5c66b; background: #fffbeb; }
  .wh-badge { display: inline-block; font-size: 12px; font-weight: 600; color: #92400e; background: #fde68a; border-radius: 999px; padding: 2px 10px; margin-bottom: 8px; }
  .wh-ask { margin: 0 0 10px; font-size: 15px; line-height: 1.5; }
  .wh-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 8px 16px; margin: 0 0 10px; }
  .wh-facts dt { font-size: 12px; color: #64748b; }
  .wh-facts dd { margin: 0; font-weight: 500; overflow-wrap: anywhere; }
  .wh-small { font-size: 13px; color: #475569; margin: 8px 0 0; }
  .wh-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
  .wh-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
  .wh-company { font-size: 16px; font-weight: 600; }
  .wh-sub { font-size: 13px; color: #64748b; }
  .wh-ok { flex: none; font-size: 12px; font-weight: 600; color: #047857; background: #ecfdf5; border-radius: 999px; padding: 2px 10px; }
  .wh-empty { border: 1px dashed #cbd5e1; border-radius: 10px; padding: 12px 14px; color: #475569; font-size: 13px; background: #f8fafc; }
  .wh-err { color: #b91c1c; }
  .wh-book-title { font-size: 13px; font-weight: 600; color: #334155; margin: 4px 0 6px; }
  .wh-book { border: 1px solid #c7d2fe; background: #eef2ff; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; font-size: 14px; }
  .wh-book-when { font-weight: 600; color: #3730a3; margin-bottom: 2px; }
  .wh-book a { color: inherit; }
  .wh-cert { border: 1px solid #e4e7ec; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; background: #fff; }
  .wh-cert-old { opacity: .65; }
  .wh-cert-out { font-weight: 700; font-size: 15px; }
  .wh-out-fit { color: #047857; } .wh-out-fit_restricted { color: #b45309; }
  .wh-out-temp_unfit, .wh-out-unfit, .wh-out-further_assessment { color: #b91c1c; }
  .wh-tag { flex: none; font-size: 12px; color: #475569; background: #f1f5f9; border-radius: 999px; padding: 2px 10px; }
</style>
