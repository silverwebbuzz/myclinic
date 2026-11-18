<?php
/**
 * /admin/corporate/geo/countries/{code} — edit one country + its regions.
 *
 * @var array<string,mixed> $country
 * @var list<array<string,mixed>> $regions
 * @var list<array<string,mixed>> $currencies
 * @var list<string> $timezones
 * @var bool $isSuper
 */
use App\Services\Corporate\GeoAdminService as G;

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$c = $country;
$input = 'mt-1 w-full rounded border border-slate-200 px-2.5 py-1.5 text-sm';
$csrfField = '<input type="hidden" name="_csrf" value="' . $h($csrf) . '">';
$text = static function (string $name, string $label, $value, string $hint = '') use ($h, $input): string {
    return '<label class="block text-xs text-slate-600">' . $h($label) . ($hint !== '' ? ' <span class="text-slate-400">' . $h($hint) . '</span>' : '')
        . '<input name="' . $h($name) . '" value="' . $h($value ?? '') . '" class="' . $input . '"></label>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($c['name']) ?> · Countries · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl px-6 py-6">
        <a href="/admin/corporate/geo" class="text-sm text-slate-500 hover:underline">← Countries</a>
        <h1 class="mt-2 text-xl font-semibold"><?= $h($c['name']) ?> <span class="text-base font-normal text-slate-400"><?= $h($c['code']) ?></span></h1>
        <?php require __DIR__ . '/_corporate_flash.php'; ?>

        <form method="post" action="/admin/corporate/geo/countries/<?= $h($c['code']) ?>" class="mt-4 ui-card space-y-4 p-5">
            <?= $csrfField ?>
            <div class="grid gap-4 sm:grid-cols-3">
                <?= $text('name', 'Country name', $c['name']) ?>
                <?= $text('phone_code', 'Calling code', $c['phone_code'], 'without +') ?>
                <?= $text('phone_nsn_lengths', 'Phone number lengths', $c['phone_nsn_lengths'], 'digits after the code, e.g. 8,9') ?>
                <label class="block text-xs text-slate-600">Currency
                    <select name="currency_code" class="<?= $input ?>">
                        <?php foreach ($currencies as $cur): ?>
                            <option value="<?= $h($cur['code']) ?>" <?= $cur['code'] === $c['currency_code'] ? 'selected' : '' ?>><?= $h($cur['code'] . ' · ' . $cur['name']) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label class="block text-xs text-slate-600">Default time zone
                    <select name="default_timezone" class="<?= $input ?>">
                        <?php foreach ($timezones as $tz): ?>
                            <option value="<?= $h($tz) ?>" <?= $tz === $c['default_timezone'] ? 'selected' : '' ?>><?= $h($tz) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label class="block text-xs text-slate-600">First day of week
                    <select name="week_start" class="<?= $input ?>">
                        <?php foreach (G::WEEK_STARTS as $v => $l): ?><option value="<?= $v ?>" <?= (int) $c['week_start'] === $v ? 'selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
                    </select></label>
                <label class="block text-xs text-slate-600">Date format
                    <select name="date_format" class="<?= $input ?>">
                        <?php foreach (G::DATE_FORMATS as $v => $l): ?><option value="<?= $h($v) ?>" <?= $c['date_format'] === $v ? 'selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
                    </select></label>
                <label class="block text-xs text-slate-600">Time format
                    <select name="time_format" class="<?= $input ?>">
                        <?php foreach (G::TIME_FORMATS as $v => $l): ?><option value="<?= $h($v) ?>" <?= $c['time_format'] === $v ? 'selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
                    </select></label>
                <?= $text('sort_order', 'Sort order', $c['sort_order']) ?>
            </div>
            <div class="grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-3">
                <?= $text('region_label', 'Region label', $c['region_label'], 'State / Emirate / County') ?>
                <?= $text('postal_label', 'Postal code label', $c['postal_label'], 'empty = no postcodes') ?>
                <?= $text('company_id_label', 'Company registration label', $c['company_id_label'], 'CIN / Trade licence no.') ?>
                <?= $text('tax_label', 'Tax name', $c['tax_label'], 'GST / VAT; empty = none') ?>
                <?= $text('tax_id_label', 'Tax number label', $c['tax_id_label'], 'GSTIN / TRN') ?>
            </div>
            <?php if ($isSuper): ?>
                <label class="flex items-center gap-2 border-t border-slate-100 pt-4 text-sm">
                    <input type="checkbox" name="is_active" value="1" <?= (int) $c['is_active'] === 1 ? 'checked' : '' ?>>
                    Offer this country at company sign-up
                </label>
            <?php endif; ?>
            <button class="rounded bg-slate-800 px-4 py-2 text-xs font-semibold text-white">Save country</button>
        </form>

        <section class="mt-4 ui-card p-5">
            <h2 class="font-semibold"><?= $h($c['region_label']) ?>s (<?= count($regions) ?>)</h2>
            <form method="post" action="/admin/corporate/geo/countries/<?= $h($c['code']) ?>/regions" class="mt-3 flex flex-wrap items-end gap-2">
                <?= $csrfField ?>
                <label class="text-xs text-slate-600">Name<input name="name" required maxlength="80" class="mt-1 block w-56 rounded border border-slate-200 px-2.5 py-1.5 text-sm"></label>
                <label class="text-xs text-slate-600">Code <span class="text-slate-400">(ISO, optional)</span><input name="code" maxlength="10" class="mt-1 block w-24 rounded border border-slate-200 px-2.5 py-1.5 text-sm"></label>
                <label class="text-xs text-slate-600">Time zone <span class="text-slate-400">(if different)</span>
                    <select name="timezone" class="mt-1 block w-56 rounded border border-slate-200 px-2.5 py-1.5 text-sm">
                        <option value="">Same as country</option>
                        <?php foreach ($timezones as $tz): ?><option value="<?= $h($tz) ?>"><?= $h($tz) ?></option><?php endforeach; ?>
                    </select></label>
                <button class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Add</button>
            </form>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($regions as $r): ?>
                    <div class="flex items-center justify-between gap-2 rounded bg-white px-3 py-2 text-sm ring-1 ring-slate-200 <?= (int) $r['is_active'] === 1 ? '' : 'opacity-60' ?>">
                        <div><?= $h($r['name']) ?> <span class="text-xs text-slate-400"><?= $h($r['code'] ?? '') ?><?= !empty($r['timezone']) ? ' · ' . $h($r['timezone']) : '' ?></span></div>
                        <form method="post" action="/admin/corporate/geo/regions/<?= (int) $r['id'] ?>/toggle">
                            <?= $csrfField ?><button class="text-xs font-semibold underline <?= (int) $r['is_active'] === 1 ? 'text-slate-500' : 'text-emerald-700' ?>"><?= (int) $r['is_active'] === 1 ? 'Hide' : 'Show' ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if ($regions === []): ?><p class="text-sm text-slate-500">None yet. Add the ones companies will choose from.</p><?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
