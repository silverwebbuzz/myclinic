<form method="post" action="/settings/general" enctype="multipart/form-data" class="space-y-6 rounded-xl border bg-white p-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <h2 class="text-lg font-semibold">General</h2>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="text-xs font-medium text-slate-600">Clinic name</label>
            <input name="clinic_name" required value="<?= htmlspecialchars($clinic['name'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label class="text-xs font-medium text-slate-600">Address</label>
            <textarea name="address" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"><?= htmlspecialchars($clinic['address'] ?? '') ?></textarea>
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Phone</label>
            <input name="phone" value="<?= htmlspecialchars($clinic['phone'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Email</label>
            <input name="email" type="email" value="<?= htmlspecialchars($clinic['email'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">GSTIN</label>
            <input name="gstin" value="<?= htmlspecialchars($clinic['gstin'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Brand color</label>
            <input name="brand_color" type="color" value="<?= htmlspecialchars($clinic['brand_color'] ?? '#0F9B6E') ?>" class="mt-1 h-10 w-full rounded border">
        </div>
        <div class="sm:col-span-2">
            <label class="text-xs font-medium text-slate-600">Logo</label>
            <input name="logo" type="file" accept="image/png,image/jpeg" class="mt-1 w-full text-sm">
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Country</label>
            <select name="country_code" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                <?php foreach ($countries as $code => $name): ?>
                <option value="<?= $code ?>" <?= ($clinic['country_code'] ?? 'IN') === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Currency</label>
            <input name="currency" value="<?= htmlspecialchars($clinic['currency'] ?? 'INR') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Timezone</label>
            <input name="timezone" value="<?= htmlspecialchars($clinic['timezone'] ?? 'Asia/Kolkata') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">UHID prefix</label>
            <input name="uhid_prefix" maxlength="6" value="<?= htmlspecialchars($config['uhid_prefix'] ?? 'MC') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Invoice prefix</label>
            <input name="invoice_prefix" value="<?= htmlspecialchars($config['invoice_prefix'] ?? 'INV') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Consultation fee</label>
            <input name="consultation_fee" type="number" step="0.01" value="<?= htmlspecialchars((string) ($config['consultation_fee'] ?? '0')) ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Tax label</label>
            <input name="invoice_tax_label" value="<?= htmlspecialchars($config['invoice_tax_label'] ?? 'GST') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Tax %</label>
            <input name="invoice_tax_percent" type="number" step="0.01" value="<?= htmlspecialchars((string) ($config['invoice_tax_percent'] ?? '0')) ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </div>
    </div>
    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Save general</button>
</form>
