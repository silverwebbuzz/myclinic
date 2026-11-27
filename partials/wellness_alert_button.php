<?php
// =====================================================================
// partials/wellness_alert_button.php — "🔔 Email me new free camps in {City}"
// (Wellness Hub Phase 5). Email only.
//
// Set before require: $waKind ('free_camp'|'event'), $waCity (slug),
// $waCityName, $viewer (?array). Styles: assets/css/wellness.css (.wa-*).
// =====================================================================
require_once __DIR__ . '/wellness_alerts.php';

if (!ecp_wa_enabled() || empty($waCity)) {
    return;
}
$waWhat = ($waKind === 'event' ? 'health events' : 'free health camps') . ' in ' . $waCityName;
$waSub = $viewer ? ecp_wa_find((int) $viewer['id'], $waKind, (string) $waCity) : null;
$waHasEmail = $viewer && trim((string) ($viewer['email'] ?? '')) !== '';
?>
<div class="wa-box" x-data="waAlert(<?= e(json_encode(['kind' => $waKind, 'city' => $waCity, 'signedIn' => (bool) $viewer, 'hasEmail' => $waHasEmail, 'on' => (bool) $waSub, 'id' => $waSub ? (int) $waSub['id'] : 0])) ?>)">
  <template x-if="on">
    <p class="wa-on">🔔 We’ll email you when new <?= e($waWhat) ?> are listed. <button type="button" class="wa-link" @click="stop()">Stop</button></p>
  </template>
  <template x-if="!on">
    <div class="wa-off">
      <span>🔔 Get an email when new <?= e($waWhat) ?> are listed.</span>
      <input type="email" x-show="signedIn && !hasEmail" x-model="email" placeholder="your@email.com" aria-label="Your email">
      <button type="button" class="btn btn-secondary" :disabled="busy" @click="start()">Email me</button>
    </div>
  </template>
  <p class="wa-msg" x-show="msg" x-text="msg"></p>
</div>
<script>
  if (!window.waAlert) {
    window.waAlert = function (cfg) {
      return {
        ...cfg, email: '', busy: false, msg: '',
        async call(body) {
          const r = await fetch('/api/wellness_alerts', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
          return { status: r.status, j: await r.json().catch(() => ({})) };
        },
        async start() {
          if (!this.signedIn) { if (window.ecpAuth) window.ecpAuth.require('camp_alert', () => location.reload()); else location.href = '/patient'; return; }
          this.busy = true; this.msg = '';
          try {
            const { j } = await this.call({ action: 'subscribe', kind: this.kind, city: this.city, email: this.email });
            if (j.ok) { this.on = true; this.msg = 'Done — we’ll email ' + j.email + '.'; setTimeout(() => location.reload(), 900); }
            else this.msg = j.error === 'email_required' ? 'Please enter your email.' : 'Could not save. Please try again.';
          } finally { this.busy = false; }
        },
        async stop() {
          this.busy = true;
          try { const { j } = await this.call({ action: 'unsubscribe', id: this.id }); if (j.ok) { this.on = false; this.msg = 'Stopped.'; } }
          finally { this.busy = false; }
        },
      };
    };
  }
</script>
