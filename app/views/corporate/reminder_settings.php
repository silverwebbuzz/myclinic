<?php
/**
 * /company/settings/reminders — switch automatic emails on / off.
 *
 * @var array<string, bool> $on
 */
use App\Services\Corporate\ReminderService;

$pageTitle = 'Reminders';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$employeeKinds = ['due_30', 'due_7', 'overdue', 'booking', 'cert_expiry'];
ob_start();
?>
<div>
    <h1 class="text-[20px] font-semibold tracking-[-.015em]">Reminders</h1>
    <p class="mt-0.5 text-tx3">Automatic emails, sent each morning after 08:00 your time. Nobody gets the same reminder twice.</p>
</div>
<form method="post" action="/company/settings/reminders" class="mt-4 space-y-4">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <section class="rounded-[10px] border border-ln bg-sf">
        <div class="border-b border-ln2 px-5 py-3">
            <h2 class="font-semibold">To employees</h2>
            <p class="text-[13px] text-tx3">Sent to the work email on the employee's record (people without one get none). They list check names and dates only, never results.</p>
        </div>
        <ul class="divide-y divide-ln2">
            <?php foreach ($employeeKinds as $k): [$label, $hint] = ReminderService::KINDS[$k]; ?>
                <li><label class="flex cursor-pointer items-start gap-3 px-5 py-3">
                    <input type="checkbox" name="on[<?= $e($k) ?>]" value="1" <?= $on[$k] ? 'checked' : '' ?> class="mt-1">
                    <span><span class="font-medium"><?= $e($label) ?></span><span class="block text-[13px] text-tx3"><?= $e($hint) ?></span></span>
                </label></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <section class="rounded-[10px] border border-ln bg-sf">
        <div class="border-b border-ln2 px-5 py-3"><h2 class="font-semibold">To your team</h2></div>
        <?php [$label, $hint] = ReminderService::KINDS['hr_digest']; ?>
        <label class="flex cursor-pointer items-start gap-3 px-5 py-3">
            <input type="checkbox" name="on[hr_digest]" value="1" <?= $on['hr_digest'] ? 'checked' : '' ?> class="mt-1">
            <span><span class="font-medium"><?= $e($label) ?></span><span class="block text-[13px] text-tx3"><?= $e($hint) ?> Branch managers see only their branches.</span></span>
        </label>
    </section>
    <div class="flex flex-wrap items-center gap-3">
        <button class="<?= $btn ?> h-9 bg-ac px-4 text-white hover:opacity-90">Save</button>
        <span class="text-[13px] text-tx3">Email only for now. WhatsApp reminders will come later, only for people who agreed to receive them.</span>
    </div>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
