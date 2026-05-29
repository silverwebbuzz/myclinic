<div class="max-w-xl space-y-4">
    <h2 class="ui-section-title"><?= $lead ? 'Edit lead' : 'Add lead' ?></h2>
    <form method="post" action="/crm/save" class="ui-card ui-card-pad space-y-4 text-sm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <?php if ($lead): ?><input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>"><?php endif; ?>
        <label class="block">Name<input name="name" required value="<?= htmlspecialchars($lead['name'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2"></label>
        <label class="block">Phone<input name="phone" value="<?= htmlspecialchars($lead['phone'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2"></label>
        <label class="block">Email<input name="email" type="email" value="<?= htmlspecialchars($lead['email'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2"></label>
        <label class="block">Inquiry<textarea name="inquiry_about" rows="3" class="mt-1 w-full rounded-lg border px-3 py-2"><?= htmlspecialchars($lead['inquiry_about'] ?? '') ?></textarea></label>
        <label class="block">Source
            <select name="source" class="mt-1 w-full rounded-lg border px-3 py-2">
                <?php foreach (['website','google_ads','instagram','facebook','walk_in','referral','whatsapp','ivr','other'] as $s): ?>
                <option value="<?= $s ?>" <?= ($lead['source'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block">Status
            <select name="status" class="mt-1 w-full rounded-lg border px-3 py-2">
                <?php foreach (\App\Services\CrmLeadService::STATUSES as $s): ?>
                <option value="<?= $s ?>" <?= ($lead['status'] ?? 'new') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block">Follow-up date<input name="follow_up_date" type="date" value="<?= htmlspecialchars($lead['follow_up_date'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2"></label>
        <label class="block">Assigned to
            <select name="assigned_to" class="mt-1 w-full rounded-lg border px-3 py-2">
                <option value="">—</option>
                <?php foreach ($staff as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= (int)($lead['assigned_to']??0)===(int)$u['id']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block">Notes<textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2"><?= htmlspecialchars($lead['notes'] ?? '') ?></textarea></label>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-white">Save</button>
            <a href="/crm" class="rounded-lg border px-4 py-2">Cancel</a>
        </div>
    </form>
</div>
