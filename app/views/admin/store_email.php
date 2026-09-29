<?php
/**
 * /admin/store/email — store email delivery: sender, reply-to, team alerts, test send, log.
 *
 * @var bool $smtpOk
 * @var string $fromName
 * @var string $fromEmail
 * @var string $replyTo
 * @var string $team
 * @var array<string, array{group: string, label: string, vars: list<string>, on: bool}> $registry
 * @var array{sent: int, failed: int, disabled: int} $stats
 * @var list<array<string,mixed>> $log
 * @var string $status
 * @var string $adminEmail
 */
use App\Services\Store\StoreEmailTemplates;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 block w-full rounded border px-3 py-1.5 text-sm';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store email — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-4xl space-y-5 p-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Store email</h1>
            <p class="text-sm text-slate-500">How store emails are sent to customers, sellers and your team. The SMTP login is shared with clinic emails (<a href="/admin/email" class="text-sky-700 hover:underline">Email delivery</a>).</p>
        </div>
        <a href="/admin/store/email-templates" class="rounded border bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Edit email wording →</a>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>

    <section class="grid gap-3 sm:grid-cols-4">
        <div class="rounded-xl border bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-400">SMTP</div>
            <div class="mt-1 font-semibold <?= $smtpOk ? 'text-emerald-700' : 'text-red-600' ?>"><?= $smtpOk ? 'Configured' : 'Not configured' ?></div>
        </div>
        <?php foreach (['sent' => ['Sent', 'text-slate-900'], 'failed' => ['Failed', 'text-red-600'], 'disabled' => ['Switched off', 'text-slate-500']] as $k => [$label, $cls]): ?>
            <a href="?status=<?= $k ?>#log" class="rounded-xl border bg-white p-4 shadow-sm hover:border-slate-300">
                <div class="text-xs uppercase tracking-wide text-slate-400"><?= $label ?> · last 7 days</div>
                <div class="mt-1 text-xl font-semibold <?= $cls ?>"><?= (int) $stats[$k] ?></div>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">Sender &amp; addresses</h2>
        <form method="post" action="/admin/store/email" class="mt-4 grid gap-4 sm:grid-cols-2">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="save">
            <label class="block text-sm">
                <span class="text-slate-600">Sender name</span>
                <input name="from_name" value="<?= $e($fromName) ?>" maxlength="80" class="<?= $input ?>">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Sender address</span>
                <input name="from_email" type="email" value="<?= $e($fromEmail) ?>" class="<?= $input ?>">
                <span class="mt-1 block text-xs text-slate-400">Must be a mailbox your SMTP server is allowed to send from (e.g. noreply@eclinicpro.com).</span>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Reply-to address</span>
                <input name="reply_to" type="email" value="<?= $e($replyTo) ?>" class="<?= $input ?>">
                <span class="mt-1 block text-xs text-slate-400">When a customer or seller clicks Reply, it goes here.</span>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Team alert addresses</span>
                <input name="team" value="<?= $e($team) ?>" class="<?= $input ?>" placeholder="help@eclinicpro.com, ops@eclinicpro.com">
                <span class="mt-1 block text-xs text-slate-400">New orders, returns, payout requests, new sellers… Separate several with commas.</span>
            </label>
            <div class="sm:col-span-2">
                <button class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save</button>
            </div>
        </form>
    </section>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">Send a test email</h2>
        <p class="mt-1 text-sm text-slate-500">Sends the chosen email with sample data and your current wording. The subject starts with “[Test]”.</p>
        <form method="post" action="/admin/store/email" class="mt-3 flex flex-wrap items-end gap-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="test">
            <label class="block text-sm">
                <span class="text-slate-600">Send to</span>
                <input name="to" type="email" required value="<?= $e($adminEmail) ?>" class="mt-1 block w-64 rounded border px-3 py-1.5 text-sm">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Email</span>
                <select name="template" class="mt-1 block rounded border px-3 py-1.5 text-sm">
                    <?php foreach (StoreEmailTemplates::GROUPS as $g => $gLabel): ?>
                        <optgroup label="<?= $e($gLabel) ?>">
                            <?php foreach ($registry as $key => $meta): if ($meta['group'] !== $g) { continue; } ?>
                                <option value="<?= $e($key) ?>"><?= $e($meta['label']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Send test</button>
        </form>
    </section>

    <section id="log" class="rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">Recent store emails</h2>
            <div class="flex gap-1 text-xs">
                <?php foreach (['' => 'All', 'sent' => 'Sent', 'failed' => 'Failed', 'disabled' => 'Switched off'] as $k => $l): ?>
                    <a href="?status=<?= $k ?>#log" class="rounded-full px-2.5 py-1 <?= $status === $k ? 'bg-slate-800 text-white' : 'bg-slate-100' ?>"><?= $l ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (!$log): ?>
            <p class="mt-3 text-sm text-slate-500">Nothing yet. (If you just deployed, run the SQL patch <code>2026_10_03_store_emails.sql</code>.)</p>
        <?php else: ?>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-400"><tr><th class="py-1 pr-3">When</th><th class="pr-3">Email</th><th class="pr-3">To</th><th class="pr-3">Subject</th><th>Status</th></tr></thead>
                    <tbody class="divide-y">
                    <?php foreach ($log as $row): ?>
                        <tr class="align-top">
                            <td class="whitespace-nowrap py-2 pr-3 text-slate-500"><?= $e(date('j M, H:i', (int) strtotime((string) $row['created_at']))) ?></td>
                            <td class="pr-3"><?= $e($registry[$row['template_key']]['label'] ?? $row['template_key']) ?></td>
                            <td class="pr-3 font-mono text-xs"><?= $e($row['recipient']) ?></td>
                            <td class="pr-3"><?= $e($row['subject']) ?></td>
                            <td>
                                <?php if ($row['status'] === 'sent'): ?><span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Sent</span>
                                <?php elseif ($row['status'] === 'failed'): ?><span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700" title="<?= $e($row['error'] ?? '') ?>">Failed</span>
                                    <div class="mt-1 max-w-[220px] text-xs text-red-600"><?= $e($row['error'] ?? '') ?></div>
                                <?php else: ?><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Switched off</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
