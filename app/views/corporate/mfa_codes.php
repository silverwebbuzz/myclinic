<?php
/**
 * Backup codes — shown ONCE, right after they're made (never stored in plain text).
 *
 * @var list<string> $codes
 * @var string $next
 * @var bool $firstTime
 */
$pageTitle = 'Save your backup codes';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$txt = "eClinicPro Workforce Health — backup sign-in codes\n"
    . 'Account: ' . $email . ($companyName !== '' ? ' (' . $companyName . ')' : '') . "\n"
    . 'Created: ' . gmdate('d M Y H:i') . " UTC\nEach code works once.\n\n" . implode("\n", $codes) . "\n";
ob_start();
?>
<div class="rounded-[10px] border border-ln bg-sf p-7 shadow-sm" x-data="{ saved: false }">
    <?php if (!empty($firstTime)): ?>
        <div class="mb-4 rounded-lg bg-okb px-3 py-2 text-sm text-ok">Two-step sign-in is on.</div>
    <?php endif; ?>
    <h1 class="text-xl font-semibold">Save your backup codes</h1>
    <p class="mt-1 text-sm text-tx2">If you lose your phone, sign in with one of these codes. Each works once.
        <strong>This is the only time we'll show them</strong>; keep them somewhere safe, like a password manager.</p>

    <ul class="mt-5 grid grid-cols-2 gap-2 rounded-[10px] border border-ln bg-sf2 p-4 font-mono text-[15px] tracking-wider">
        <?php foreach ($codes as $c): ?>
            <li class="rounded-md bg-sf px-2 py-1.5 text-center ring-1 ring-ln"><?= $e($c) ?></li>
        <?php endforeach; ?>
    </ul>

    <div class="mt-4 flex flex-wrap gap-2">
        <a download="eclinicpro-backup-codes.txt" href="data:text/plain;charset=utf-8,<?= $e(rawurlencode($txt)) ?>" @click="saved = true"
           class="inline-flex h-9 items-center rounded-[7px] border border-ln px-3 text-sm font-medium hover:bg-sf2">Download .txt</a>
        <button type="button" class="inline-flex h-9 items-center rounded-[7px] border border-ln px-3 text-sm font-medium hover:bg-sf2"
                @click="navigator.clipboard && navigator.clipboard.writeText(<?= $e(json_encode(implode("\n", $codes))) ?>); saved = true">Copy</button>
        <button type="button" onclick="window.print()" class="inline-flex h-9 items-center rounded-[7px] border border-ln px-3 text-sm font-medium hover:bg-sf2">Print</button>
    </div>

    <label class="mt-5 flex items-center gap-2 text-sm text-tx2">
        <input type="checkbox" x-model="saved"> I've saved my backup codes
    </label>
    <a href="<?= $e($next) ?>" :class="saved ? '' : 'pointer-events-none opacity-50'"
       class="mt-4 block w-full rounded-[7px] bg-ac py-2.5 text-center text-sm font-medium text-white hover:opacity-90">Continue</a>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_auth_layout.php';
