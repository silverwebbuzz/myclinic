<?php
/** /admin/email — SMTP / Mailgun delivery status + test sender. */
$e = $email;
$dot = static fn (bool $on): string => $on
    ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Configured</span>'
    : '<span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Not set</span>';

$activeLabel = match ($e['active'] ?? 'log') {
    'mailgun' => 'Mailgun API',
    'smtp' => 'SMTP',
    default => 'Log file only (no live delivery)',
};
$activeClass = match ($e['active'] ?? 'log') {
    'mailgun', 'smtp' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    default => 'bg-amber-50 text-amber-900 border-amber-200',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-3xl p-6 space-y-5">

        <div>
            <h1 class="text-xl font-semibold">Email delivery</h1>
            <p class="text-sm text-slate-500">
                Clinic app emails (welcome, password reset, staff invites, invoices, etc.).
                Credentials live in <code>app/.env</code> — read-only here.
            </p>
        </div>

        <section class="rounded-xl border p-5 shadow-sm <?= $activeClass ?>">
            <div class="text-xs uppercase tracking-wide opacity-70">Active provider</div>
            <div class="mt-1 text-lg font-semibold"><?= htmlspecialchars($activeLabel) ?></div>
            <?php if (($e['active'] ?? 'log') === 'log'): ?>
            <p class="mt-2 text-sm">
                Without <code>MAILGUN_*</code> or <code>SMTP_*</code>, outbound mail is only appended to
                <code>storage/logs/mail.log</code> and never reaches inboxes.
            </p>
            <?php endif; ?>
        </section>

        <section class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-semibold">Providers</h2>

            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <div class="font-medium">SMTP <span class="text-xs text-slate-400">(recommended)</span></div>
                    <div class="text-xs text-slate-500">SMTP_HOST · SMTP_PORT · SMTP_SECURE · SMTP_USERNAME · SMTP_PASSWORD</div>
                </div>
                <?= $dot((bool) ($e['smtp_set'] ?? false)) ?>
            </div>

            <?php if (!empty($e['smtp_set'])): ?>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Host</dt><dd class="font-mono text-slate-800"><?= htmlspecialchars((string) $e['smtp_host']) ?>:<?= (int) $e['smtp_port'] ?></dd></div>
                <div><dt class="text-slate-500">Security</dt><dd class="font-mono text-slate-800"><?= htmlspecialchars((string) $e['smtp_secure']) ?></dd></div>
                <div><dt class="text-slate-500">TLS peer name</dt><dd class="font-mono text-slate-800"><?= htmlspecialchars((string) (($e['smtp_peer_name'] ?? '') !== '' ? $e['smtp_peer_name'] : $e['smtp_host'] . ' (defaults to host)')) ?></dd></div>
                <div><dt class="text-slate-500">Username</dt><dd class="font-mono text-slate-800"><?= htmlspecialchars((string) $e['smtp_username']) ?></dd></div>
                <div><dt class="text-slate-500">From</dt><dd class="font-mono text-slate-800"><?= htmlspecialchars((string) $e['smtp_from_name']) ?> &lt;<?= htmlspecialchars((string) $e['smtp_from_email']) ?>&gt;</dd></div>
            </dl>
            <?php endif; ?>

            <div class="flex items-center justify-between pt-2">
                <div>
                    <div class="font-medium">Mailgun <span class="text-xs text-slate-400">(legacy)</span></div>
                    <div class="text-xs text-slate-500">MAILGUN_DOMAIN · MAILGUN_API_KEY — used when set, overrides SMTP</div>
                </div>
                <?= $dot((bool) ($e['mailgun_set'] ?? false)) ?>
            </div>

            <?php if (!empty($e['archive_bcc'])): ?>
            <p class="text-xs text-slate-500 border-t pt-3">Archive BCC: <code><?= htmlspecialchars((string) $e['archive_bcc']) ?></code></p>
            <?php endif; ?>
        </section>

        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold">Send test email</h2>
            <p class="mt-1 text-xs text-slate-500">Uses the same routing as production (Mailgun if configured, else SMTP). Shows the SMTP conversation when applicable.</p>

            <?php if (!empty($testResult)): ?>
            <div class="mt-4 rounded-lg border px-4 py-3 text-sm <?= !empty($testResult['ok']) ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900' ?>">
                <?php if (!empty($testResult['ok'])): ?>
                <p class="font-semibold">Sent successfully via <?= htmlspecialchars((string) ($testResult['provider'] ?? 'smtp')) ?></p>
                <?php else: ?>
                <p class="font-semibold">Failed</p>
                <p class="mt-1"><?= htmlspecialchars((string) ($testResult['error'] ?? 'Unknown error')) ?></p>
                <?php endif; ?>
            </div>
            <?php if (!empty($testResult['steps'])): ?>
            <pre class="mt-3 max-h-64 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100"><?= htmlspecialchars(implode("\n", $testResult['steps'])) ?></pre>
            <?php endif; ?>
            <?php endif; ?>

            <form method="post" action="/admin/email/test" class="mt-4 flex flex-wrap items-end gap-3">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <label class="block text-xs">
                    <span class="text-slate-600">Send to</span>
                    <input type="email" name="test_to" required
                           value="<?= htmlspecialchars((string) ($testTo ?? ($admin['email'] ?? ''))) ?>"
                           class="mt-1 block w-64 rounded border px-2 py-1.5 text-sm" placeholder="you@example.com">
                </label>
                <label class="block text-xs">
                    <span class="text-slate-600">Template</span>
                    <select name="test_template" class="mt-1 block rounded border px-2 py-1.5 text-sm">
                        <option value="welcome" <?= ($testTemplate ?? 'welcome') === 'welcome' ? 'selected' : '' ?>>Welcome</option>
                        <option value="password_reset" <?= ($testTemplate ?? '') === 'password_reset' ? 'selected' : '' ?>>Password reset</option>
                        <option value="staff_invite" <?= ($testTemplate ?? '') === 'staff_invite' ? 'selected' : '' ?>>Staff invite</option>
                        <option value="appointment_reminder" <?= ($testTemplate ?? '') === 'appointment_reminder' ? 'selected' : '' ?>>Appointment reminder</option>
                        <option value="rx_delivery" <?= ($testTemplate ?? '') === 'rx_delivery' ? 'selected' : '' ?>>Prescription ready</option>
                        <option value="invoice_paid" <?= ($testTemplate ?? '') === 'invoice_paid' ? 'selected' : '' ?>>Invoice paid</option>
                    </select>
                </label>
                <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Send test</button>
            </form>
        </section>

        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold">Recent mail log</h2>
            <p class="mt-1 text-xs text-slate-500"><code><?= htmlspecialchars((string) ($e['log_path'] ?? '')) ?></code> — fallback when no provider is configured, or SMTP failures.</p>
            <?php if (empty($logLines)): ?>
            <p class="mt-3 text-sm text-slate-500">No log entries yet.</p>
            <?php else: ?>
            <pre class="mt-3 max-h-72 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100"><?= htmlspecialchars(implode("\n", $logLines)) ?></pre>
            <?php endif; ?>
        </section>

        <section class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600 space-y-2">
            <p class="font-semibold text-slate-700">Troubleshooting</p>
            <ul class="list-disc pl-4 space-y-1">
                <li>Port <strong>465</strong> → <code>SMTP_SECURE=ssl</code>. Port <strong>587</strong> → <code>SMTP_SECURE=tls</code>.</li>
                <li>If the SMTP banner shows <code>mail.silverwebbuzz.in</code> but you connect to <code>mail.eclinicpro.com</code>, set <code>SMTP_PEER_NAME=mail.silverwebbuzz.in</code> (TLS certificate hostname).</li>
                <li>Or use port <strong>465</strong> + <code>SMTP_SECURE=ssl</code> — often more reliable on cPanel/Exim hosts.</li>
                <li><code>SMTP_FROM_EMAIL</code> must usually match the authenticated mailbox.</li>
                <li>If Mailgun keys are still in <code>.env</code>, they take priority over SMTP — clear them to use SMTP.</li>
                <li>Check spam folder; verify DNS (SPF/DKIM) on your mail host.</li>
            </ul>
        </section>
    </main>
</body>
</html>
