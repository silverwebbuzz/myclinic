<?php
/**
 * /company/register — company + first admin.
 *
 * @var list<array<string,mixed>> $countries  active geo_countries rows
 * @var array<string, list<array{id:int,name:string}>> $regions  by country code
 * @var list<array<string,mixed>> $industries
 * @var array<string,string> $sizeBands
 * @var array<string,mixed> $old
 */
$pageTitle = 'Register your company';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$old = $old ?? [];
$input = 'mt-1 w-full rounded-[7px] border border-ln bg-sf px-3 py-2 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';

// Only what the form needs on the client: phone code, region label, regions.
$countryJs = [];
foreach ($countries as $c) {
    $countryJs[(string) $c['code']] = [
        'phone' => (string) $c['phone_code'],
        'regionLabel' => (string) $c['region_label'],
        'regions' => $regions[(string) $c['code']] ?? [],
    ];
}
$selCountry = (string) ($old['country_code'] ?? ($countries[0]['code'] ?? ''));
$selRegion = (int) ($old['region_id'] ?? 0);
ob_start();
?>
<div class="rounded-[10px] border border-ln bg-sf p-7 shadow-sm"
     x-data="{ countries: <?= $e(json_encode($countryJs, JSON_UNESCAPED_UNICODE)) ?>, country: <?= $e(json_encode($selCountry)) ?>, region: <?= $selRegion ?: "''" ?>,
               get c() { return this.countries[this.country] || { phone: '', regionLabel: 'State', regions: [] }; } }">
    <h1 class="text-xl font-semibold">Register your company</h1>
    <p class="mt-1 text-sm text-tx3">Manage your workforce's medical screening, fitness certificates and compliance in one place.
        We review every company before employees can be added; this usually takes one working day.</p>
    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-erb px-3 py-2 text-sm text-er"><?= $e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/company/register" class="mt-5 space-y-5">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

        <fieldset class="space-y-4">
            <legend class="text-[11px] font-semibold uppercase tracking-[.06em] text-tx3">Company</legend>
            <label class="block text-sm">
                <span class="text-tx2">Company legal name</span>
                <input name="legal_name" required minlength="2" maxlength="200" value="<?= $e($old['legal_name'] ?? '') ?>" class="<?= $input ?>" placeholder="As on your trade licence / registration">
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-tx2">Country</span>
                    <select name="country_code" required x-model="country" @change="region = ''" class="<?= $input ?>">
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= $e($c['code']) ?>" <?= $selCountry === $c['code'] ? 'selected' : '' ?>><?= $e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-tx2" x-text="c.regionLabel">State</span>
                    <select name="region_id" x-model="region" class="<?= $input ?>">
                        <option value="">Select…</option>
                        <template x-for="r in c.regions" :key="r.id">
                            <option :value="r.id" x-text="r.name" :selected="r.id == region"></option>
                        </template>
                    </select>
                </label>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-tx2">City</span>
                    <input name="city" maxlength="100" value="<?= $e($old['city'] ?? '') ?>" class="<?= $input ?>">
                </label>
                <label class="block text-sm">
                    <span class="text-tx2">Number of employees</span>
                    <select name="employee_size_band" required class="<?= $input ?>">
                        <option value="">Select…</option>
                        <?php foreach ($sizeBands as $val => $label): ?>
                            <option value="<?= $e($val) ?>" <?= ($old['employee_size_band'] ?? '') === $val ? 'selected' : '' ?>><?= $e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label class="block text-sm">
                <span class="text-tx2">Industry</span>
                <select name="industry_id" required class="<?= $input ?>">
                    <option value="">Select…</option>
                    <?php foreach ($industries as $ind): ?>
                        <option value="<?= (int) $ind['id'] ?>" <?= (int) ($old['industry_id'] ?? 0) === (int) $ind['id'] ? 'selected' : '' ?>><?= $e($ind['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="text-xs text-tx3">Used to suggest the right screening programmes for your workforce.</span>
            </label>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="text-[11px] font-semibold uppercase tracking-[.06em] text-tx3">Your login (company admin)</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-tx2">Full name</span>
                    <input name="name" required minlength="2" maxlength="160" autocomplete="name" value="<?= $e($old['name'] ?? '') ?>" class="<?= $input ?>">
                </label>
                <label class="block text-sm">
                    <span class="text-tx2">Mobile</span>
                    <div data-fv-box class="mt-1 flex rounded-[7px] border border-ln bg-sf focus-within:border-ac">
                        <span class="flex items-center border-r border-ln px-3 text-tx3" x-text="'+' + c.phone"></span>
                        <input name="phone" type="tel" inputmode="tel" required maxlength="20" autocomplete="tel-national" value="<?= $e($old['phone_raw'] ?? '') ?>" class="w-full rounded-r-[7px] px-3 py-2 focus:outline-none">
                    </div>
                </label>
            </div>
            <label class="block text-sm">
                <span class="text-tx2">Work email (your login)</span>
                <input name="email" type="email" required maxlength="190" autocomplete="username" value="<?= $e($old['email'] ?? '') ?>" class="<?= $input ?>">
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-tx2">Password</span>
                    <input name="password" type="password" required minlength="10" maxlength="128" autocomplete="new-password" class="<?= $input ?>">
                    <span class="text-xs text-tx3">10+ characters</span>
                </label>
                <label class="block text-sm">
                    <span class="text-tx2">Confirm password</span>
                    <input name="password_confirm" type="password" required minlength="10" maxlength="128" data-match="password" autocomplete="new-password" class="<?= $input ?>">
                </label>
            </div>
        </fieldset>

        <label class="flex items-start gap-2 text-sm text-tx2">
            <input type="checkbox" name="accept_terms" value="1" required class="mt-0.5" <?= !empty($old['accept_terms']) ? 'checked' : '' ?>>
            <span><span class="hidden" aria-hidden="true">*</span>I agree to the <a href="https://eclinicpro.com/terms" target="_blank" rel="noopener" class="text-act underline">terms of service</a>
                and <a href="https://eclinicpro.com/privacy-policy" target="_blank" rel="noopener" class="text-act underline">privacy policy</a>,
                and I'm authorised to register this company.</span>
        </label>

        <?php if (!empty($captchaEnabled)): ?>
            <div class="g-recaptcha" data-sitekey="<?= $e($captchaSiteKey) ?>"></div>
        <?php endif; ?>
        <button class="w-full rounded-[7px] bg-ac py-2.5 text-sm font-medium text-white hover:opacity-90">Create company account</button>
    </form>
</div>
<p class="mt-4 text-center text-sm text-tx2">Already registered? <a href="/company/login" class="font-medium text-act hover:underline">Sign in</a></p>
<?php if (!empty($captchaEnabled)): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
<?php
$content = ob_get_clean();
$maxWidth = 'max-w-2xl';
require __DIR__ . '/_auth_layout.php';
