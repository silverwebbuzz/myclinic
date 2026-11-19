<?php
/**
 * /company/bookings — upcoming / past / cancelled bookings.
 *
 * @var string $view upcoming|past|cancelled
 * @var string $q
 * @var array{rows: list<array<string,mixed>>, total: int, page: int, pages: int} $result
 */
use App\Services\Corporate\BookingService;

$pageTitle = 'Bookings';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'w-full rounded-[7px] border border-ln bg-sf px-3 py-1.5 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$pill = ['booked' => 'bg-inb text-in', 'attended' => 'bg-okb text-ok', 'cancelled' => 'bg-ntb text-nt', 'no_show' => 'bg-erb text-er'];
$tabs = ['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled'];
$rows = $result['rows'];
ob_start();
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-[20px] font-semibold tracking-[-.015em]">Bookings</h1>
        <p class="mt-0.5 text-tx3">Health checks booked at your clinics. To book, open the due list or an employee and click <b>Book</b>.</p>
    </div>
    <a href="/company/screening?bucket=overdue" class="<?= $btn ?> bg-ac text-white hover:opacity-90">Book from due checks</a>
</div>
<div class="mt-4 flex flex-wrap items-center gap-3">
    <nav class="flex gap-1 rounded-[9px] bg-sf2 p-1 ring-1 ring-ln">
        <?php foreach ($tabs as $k => $label): ?>
            <a href="/company/bookings?view=<?= $k ?>" class="rounded-[7px] px-3 py-1 text-[13px] <?= $view === $k ? 'bg-sf font-medium shadow-sm' : 'text-tx2 hover:text-tx' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="get" class="flex w-full gap-2 sm:w-auto sm:min-w-0 sm:max-w-sm sm:flex-1">
        <input type="hidden" name="view" value="<?= $e($view) ?>">
        <input name="q" value="<?= $e($q) ?>" placeholder="Name, employee no. or clinic" class="<?= $input ?>">
        <button class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Search</button>
    </form>
</div>

<section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <?php if ($rows === []): ?>
        <p class="px-5 py-10 text-center text-tx3"><?= $view === 'upcoming' ? 'No upcoming bookings.' : 'Nothing here.' ?></p>
    <?php else: ?>
        <ul class="divide-y divide-ln2">
            <?php foreach ($rows as $b): ?>
                <li class="px-5 py-3" x-data="{ cancel: false }">
                    <div class="grid gap-x-4 gap-y-1 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1.5fr)_minmax(0,1fr)_auto] md:items-center">
                        <div class="min-w-0">
                            <a href="/company/employees/<?= (int) $b['employment_id'] ?>#screening" class="font-medium hover:underline"><?= $e($b['full_name']) ?></a>
                            <div class="text-[13px] text-tx3"><?= $e($b['employee_no'] ?? '') ?></div>
                        </div>
                        <div class="min-w-0 text-[13px]">
                            <div class="truncate"><?= $e($b['clinic_name']) ?><?= !empty($b['doctor_name']) ? ' · ' . $e($b['doctor_name']) : '' ?></div>
                            <div class="truncate text-tx3"><?= $e($b['checks'] ?? '') ?></div>
                        </div>
                        <div class="text-[13px]">
                            <div class="font-medium"><?= $e(date('d M Y', strtotime((string) $b['scheduled_at']))) ?></div>
                            <div class="text-tx3"><?= $e(date('h:i A', strtotime((string) $b['scheduled_at']))) ?> clinic time</div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 md:justify-end">
                            <span class="rounded-full px-2 py-0.5 text-[12px] font-medium <?= $pill[$b['status']] ?>"><?= $e(BookingService::STATUSES[$b['status']]) ?></span>
                            <?php if ($b['status'] === 'booked'): ?>
                                <a href="/company/bookings/new?employee=<?= (int) $b['employment_id'] ?>&replace=<?= (int) $b['id'] ?>" class="text-[13px] text-ac hover:underline">Move</a>
                                <button type="button" @click="cancel = !cancel" class="text-[13px] text-er hover:underline">Cancel</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($b['cancelled_reason']) && $b['status'] !== 'booked'): ?><p class="mt-1 text-[12px] text-tx3"><?= $e($b['cancelled_reason']) ?></p><?php endif; ?>
                    <?php if ($b['status'] === 'booked'): ?>
                        <form x-show="cancel" x-cloak method="post" action="/company/bookings/<?= (int) $b['id'] ?>/cancel" class="mt-2 flex flex-wrap gap-2">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                            <input name="reason" required minlength="5" maxlength="500" placeholder="Why? e.g. Employee on leave that week" class="<?= $input ?> min-w-0 flex-1">
                            <button class="<?= $btn ?> bg-er text-white hover:opacity-90">Cancel booking</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php if ($result['pages'] > 1): ?>
    <div class="mt-3 flex gap-2 text-[13px]">
        <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
            <a href="/company/bookings?view=<?= $e($view) ?>&q=<?= urlencode($q) ?>&page=<?= $p ?>" class="rounded-[7px] px-2.5 py-1 ring-1 <?= $p === $result['page'] ? 'bg-ac text-white ring-ac' : 'bg-sf ring-ln' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
