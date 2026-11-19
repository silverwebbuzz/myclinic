<?php
/**
 * /company/activity — company audit trail.
 *
 * @var list<array<string,mixed>> $rows
 * @var array<string,string> $labels
 * @var int $page
 * @var bool $hasMore
 * @var \App\Support\Locale\Locale $loc
 */
$pageTitle = 'Activity log';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$btn = 'inline-flex h-8 items-center justify-center rounded-[7px] border border-ln bg-sf px-3 text-[13px] font-medium hover:bg-sf2';
$warn = ['login_failed', 'login_locked', 'mfa_failed', 'permission_denied', 'user_disabled', 'mfa_reset_by_admin', 'company_suspended'];
ob_start();
?>
<h1 class="text-[20px] font-semibold tracking-[-.015em]">Activity log</h1>
<p class="mt-0.5 text-tx3">Sign-ins and changes in your company account, newest first. Times in <?= $e($loc->timezone) ?>.</p>

<section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <?php if ($rows === []): ?>
        <p class="px-5 py-8 text-center text-tx3">Nothing here yet.</p>
    <?php else: ?>
        <ul class="divide-y divide-ln2">
            <?php foreach ($rows as $r): ?>
                <?php
                $details = json_decode((string) ($r['details'] ?? ''), true);
                $actor = match ((string) $r['actor_type']) {
                    'corp_user' => (string) ($r['actor_name'] ?? 'Unknown user'),
                    'platform_admin' => 'eClinicPro team',
                    'employee' => 'Employee',
                    'provider' => 'Clinic' . (is_array($details) && !empty($details['clinic']) ? ' · ' . $details['clinic'] : ''),
                    default => 'System',
                };
                $label = $labels[(string) $r['action']] ?? (string) $r['action'];
                $extra = [];
                if (!empty($r['target_name']) && (int) $r['entity_id'] !== (int) $r['actor_id']) {
                    $extra[] = (string) $r['target_name'];
                }
                if (is_array($details)) {
                    foreach (['email', 'name', 'role'] as $k) {
                        if (!empty($details[$k]) && is_string($details[$k])) {
                            $extra[] = $details[$k];
                        }
                    }
                    if (!empty($details['roles']) && is_array($details['roles'])) {
                        $extra[] = implode(', ', array_map('strval', $details['roles']));
                    }
                    if (!empty($details['permission']) && is_string($details['permission'])) {
                        $extra[] = $details['permission'];
                    }
                }
                ?>
                <li class="flex flex-wrap items-baseline gap-x-4 gap-y-0.5 px-5 py-2.5">
                    <span class="basis-full text-[12.5px] text-tx3 sm:w-44 sm:flex-none sm:basis-auto"><?= $e($loc->dateTime((string) $r['created_at'])) ?></span>
                    <span class="min-w-0 flex-1">
                        <span class="font-medium"><?= $e($actor) ?></span>
                        <span class="<?= in_array($r['action'], $warn, true) ? 'text-wn' : 'text-tx2' ?>"> · <?= $e($label) ?></span>
                        <?php if ($extra !== []): ?><span class="text-tx3"> · <?= $e(implode(' · ', array_unique($extra))) ?></span><?php endif; ?>
                    </span>
                    <span class="text-[12px] text-tx3 max-sm:basis-full"><?= $e($r['ip'] ?? '') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<div class="mt-3 flex gap-2">
    <?php if ($page > 1): ?><a class="<?= $btn ?>" href="/company/activity?page=<?= $page - 1 ?>">← Newer</a><?php endif; ?>
    <?php if ($hasMore): ?><a class="<?= $btn ?>" href="/company/activity?page=<?= $page + 1 ?>">Older →</a><?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
