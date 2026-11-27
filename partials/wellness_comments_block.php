<?php
// =====================================================================
// partials/wellness_comments_block.php — Questions & comments section on a
// Wellness Hub post / camp / event page (Phase 5).
//
// Set before require:  $whcType ('tip'|'event'), $whcId (int),
//                      $whcOwnerIdentity (?int, the post creator's identity id),
//                      $viewer (?array, ecp_patient_current()).
// Styles: assets/css/health-tips.css (.whc-*).
// =====================================================================
require_once __DIR__ . '/wellness_comments.php';

$whcDb = ecp_db();
if (!$whcDb || !wh_c_enabled($whcDb)) {
    return;
}
$whcViewerId = $viewer ? (int) $viewer['id'] : null;
if (!isset($whcOwnerIdentity)) {
    $whcOwnerIdentity = wh_c_item($whcDb, $whcType, (int) $whcId)['owner_identity_id'] ?? null;
}
$whcThread = wh_c_thread($whcDb, $whcType, (int) $whcId, $whcViewerId);
$whcCount = wh_c_count($whcDb, $whcType, (int) $whcId);
$whcIsOwner = $whcViewerId !== null && $whcOwnerIdentity !== null && $whcViewerId === (int) $whcOwnerIdentity;
$whcRender = static function (array $c, bool $isReply) use ($whcViewerId, $whcIsOwner): void {
    $mine = $whcViewerId !== null && (int) $c['identity_id'] === $whcViewerId;
    ?>
    <div class="whc-item <?= $isReply ? 'is-reply' : '' ?> <?= $c['is_creator_answer'] ? 'is-creator' : '' ?>" id="c-<?= (int) $c['id'] ?>">
      <div class="whc-meta">
        <?php if ($c['author']['creator_slug']): ?>
          <a class="whc-name" href="/wellness/creators/<?= e($c['author']['creator_slug']) ?>"><?= e($c['author']['name']) ?></a>
        <?php else: ?>
          <span class="whc-name"><?= e($c['author']['name']) ?></span>
        <?php endif; ?>
        <?php if ($c['author']['badge']): ?><span class="ht-verified">✓ <?= e($c['author']['badge']) ?></span><?php endif; ?>
        <?php if ($c['is_creator_answer']): ?><span class="whc-tag is-creator">Creator’s answer</span><?php endif; ?>
        <?php if ($c['kind'] === 'question'): ?><span class="whc-tag">Question</span><?php endif; ?>
        <span class="whc-date"><?= e(date('d M Y', strtotime((string) $c['created_at']))) ?></span>
      </div>
      <?php if ($c['status'] === 'held'): ?><p class="whc-held">Only you can see this until our team checks it (it has a link, number or reported words).</p><?php endif; ?>
      <p class="whc-body"><?= nl2br(e((string) $c['body'])) ?></p>
      <?php if ($whcViewerId !== null && $c['status'] === 'visible'): ?>
        <div class="whc-actions">
          <?php if (!$isReply): ?><button type="button" @click="replyTo = <?= (int) $c['id'] ?>; $nextTick(() => $refs['r<?= (int) $c['id'] ?>'] && $refs['r<?= (int) $c['id'] ?>'].focus())">Reply</button><?php endif; ?>
          <?php if ($mine): ?><button type="button" @click="act('delete', <?= (int) $c['id'] ?>, 'Delete this?')">Delete</button><?php endif; ?>
          <?php if (!$mine && $whcIsOwner): ?><button type="button" @click="act('hide', <?= (int) $c['id'] ?>, 'Hide this from your post?')">Hide</button><?php endif; ?>
          <?php if (!$mine): ?><button type="button" @click="reportId = <?= (int) $c['id'] ?>">Report</button><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (!$isReply): ?>
        <?php foreach ($c['replies'] as $r) { ($GLOBALS['whcRenderRef'])($r, true); } ?>
        <form class="whc-replyform" x-show="replyTo === <?= (int) $c['id'] ?>" x-cloak @submit.prevent="send(<?= (int) $c['id'] ?>, $event.target)">
          <textarea name="body" rows="2" maxlength="2000" x-ref="r<?= (int) $c['id'] ?>" placeholder="Write a reply…" required></textarea>
          <div><button class="btn btn-primary" type="submit" :disabled="busy">Reply</button> <button type="button" class="whc-link" @click="replyTo = 0">Cancel</button></div>
        </form>
      <?php endif; ?>
    </div>
    <?php
};
$GLOBALS['whcRenderRef'] = $whcRender;
?>
<link rel="stylesheet" href="/assets/css/wellness-comments.css?v=<?= @filemtime(__DIR__ . '/../assets/css/wellness-comments.css') ?: 1 ?>">
<section class="whc" id="comments" x-data="whComments(<?= e(json_encode(['type' => $whcType, 'id' => (int) $whcId])) ?>)">
  <h2>Questions &amp; comments <?= $whcCount ? '<span>(' . $whcCount . ')</span>' : '' ?></h2>
  <p class="whc-banner">Answers here are general information, not medical advice. For your own symptoms or medicines, please talk to a doctor. <a href="/find-a-doctor">Find a doctor near you →</a></p>

  <?php if ($whcViewerId !== null): ?>
    <form class="whc-form" @submit.prevent="send(0, $event.target)">
      <div class="whc-kind">
        <label><input type="radio" name="kind" value="question" x-model="kind"> Ask a question</label>
        <label><input type="radio" name="kind" value="comment" x-model="kind"> Comment</label>
      </div>
      <textarea name="body" rows="3" maxlength="2000" required :placeholder="kind === 'question' ? 'Ask the creator something about this…' : 'Share how it went, a tip, or a thank-you…'"></textarea>
      <div class="whc-formfoot">
        <span>Shown as your first name. Please don’t share phone numbers, links or other people’s details.</span>
        <button class="btn btn-primary" type="submit" :disabled="busy" x-text="kind === 'question' ? 'Ask' : 'Post'">Ask</button>
      </div>
    </form>
  <?php else: ?>
    <div class="whc-signin">
      <span>Sign in free to ask a question or comment.</span>
      <button type="button" class="btn btn-primary" onclick="window.ecpAuth ? window.ecpAuth.require('wellness_comment', () => location.reload()) : (location.href = '/patient')">Sign in</button>
    </div>
  <?php endif; ?>
  <p class="whc-msg" x-show="msg" x-text="msg"></p>

  <?php if ($whcThread): ?>
    <div class="whc-list"><?php foreach ($whcThread as $c) { $whcRender($c, false); } ?></div>
  <?php else: ?>
    <p class="whc-empty">No questions yet — be the first to ask.</p>
  <?php endif; ?>

  <div class="whc-report" x-show="reportId" x-cloak>
    <p><strong>Report this comment</strong></p>
    <select x-model="reportReason">
      <option value="">Why?</option>
      <?php foreach (WH_C_REPORT_REASONS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <button type="button" class="btn btn-primary" @click="report()" :disabled="busy">Send</button>
    <button type="button" class="whc-link" @click="reportId = 0">Cancel</button>
  </div>
</section>
<script>
  function whComments(cfg) {
    return {
      kind: 'question', replyTo: 0, reportId: 0, reportReason: '', busy: false, msg: '',
      async call(body) {
        const r = await fetch('/api/wellness_comments', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        if (r.status === 401) { if (window.ecpAuth) window.ecpAuth.require('wellness_comment', () => location.reload()); return null; }
        return r.json().catch(() => ({}));
      },
      async send(parentId, form) {
        const text = form.body.value.trim();
        if (!text) return;
        this.busy = true; this.msg = '';
        try {
          const j = await this.call({ action: 'post', item_type: cfg.type, item_id: cfg.id, kind: parentId ? 'reply' : this.kind, body: text, parent_id: parentId || null });
          if (!j) return;
          if (!j.ok) { this.msg = j.message || 'Could not post. Please try again.'; return; }
          location.hash = 'c-' + j.id; location.reload();
        } catch (e) { this.msg = 'Network problem. Please try again.'; }
        finally { this.busy = false; }
      },
      async act(action, id, confirmText) {
        if (confirmText && !confirm(confirmText)) return;
        const j = await this.call({ action, id });
        if (j && j.ok) location.reload();
      },
      async report() {
        if (!this.reportReason) { this.msg = 'Please choose a reason.'; return; }
        this.busy = true;
        try {
          const j = await this.call({ action: 'report', id: this.reportId, reason: this.reportReason });
          this.msg = j && j.ok ? 'Thank you — our team will check it.' : ((j && j.message) || 'Could not send the report.');
          this.reportId = 0; this.reportReason = '';
        } finally { this.busy = false; }
      },
    };
  }
</script>
