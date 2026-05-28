<div class="space-y-4">
    <?php if (!empty($_GET['discharge_saved'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Draft saved.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['discharge_finalized'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Finalized — PDF sent via WhatsApp; patient portal link created.</p>
    <?php endif; ?>
    <?php
    $isFinal = ($discharge['status'] ?? '') === 'finalized';
    $d = $discharge ?? [];
    ?>
    <?php if ($isFinal && !empty($d['pdf_path'])): ?>
    <a href="<?= htmlspecialchars($d['pdf_path']) ?>" target="_blank" class="text-sm text-emerald-600 hover:underline">Download discharge PDF</a>
    <?php endif; ?>
    <form method="post" action="/visits/<?= (int) $visit['id'] ?>/discharge" class="space-y-3 text-sm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label class="block">Final diagnosis
            <textarea name="final_diagnosis" rows="2" <?= $isFinal ? 'readonly' : '' ?> class="mt-1 w-full rounded-lg border px-3 py-2"><?= htmlspecialchars($d['final_diagnosis'] ?? $visit['diagnosis'] ?? '') ?></textarea>
        </label>
        <label class="block">Procedures done
            <textarea name="procedures_done" rows="2" <?= $isFinal ? 'readonly' : '' ?> class="mt-1 w-full rounded-lg border px-3 py-2"><?= htmlspecialchars($d['procedures_done'] ?? '') ?></textarea>
        </label>
        <label class="block">Treatment summary
            <textarea name="treatment_summary" rows="3" <?= $isFinal ? 'readonly' : '' ?> class="mt-1 w-full rounded-lg border px-3 py-2"><?= htmlspecialchars($d['treatment_summary'] ?? $visit['clinical_notes'] ?? '') ?></textarea>
        </label>
        <label class="block">Follow-up instructions
            <textarea name="follow_up_instructions" rows="2" <?= $isFinal ? 'readonly' : '' ?> class="mt-1 w-full rounded-lg border px-3 py-2"><?= htmlspecialchars($d['follow_up_instructions'] ?? $visit['follow_up_notes'] ?? '') ?></textarea>
        </label>
        <label class="block">Diet at discharge
            <input name="diet_at_discharge" value="<?= htmlspecialchars($d['diet_at_discharge'] ?? '') ?>" <?= $isFinal ? 'readonly' : '' ?> class="mt-1 w-full rounded-lg border px-3 py-2">
        </label>
        <label class="block">Condition at discharge
            <select name="condition_at_discharge" <?= $isFinal ? 'disabled' : '' ?> class="mt-1 w-full rounded-lg border px-3 py-2">
                <?php foreach (['stable', 'improved', 'critical', 'referred'] as $c): ?>
                <option value="<?= $c ?>" <?= ($d['condition_at_discharge'] ?? 'stable') === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (!$isFinal): ?>
        <button type="submit" class="rounded-lg border px-4 py-2 text-sm">Save draft</button>
        <?php endif; ?>
    </form>
    <?php if (!$isFinal && $editable): ?>
    <form method="post" action="/visits/<?= (int) $visit['id'] ?>/discharge/finalize" class="border-t pt-4 space-y-3" x-data="dischargeSig()">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <p class="text-xs text-slate-500">Doctor signature (required to finalize)</p>
        <canvas x-ref="canvas" width="400" height="80" class="border rounded-lg bg-white w-full max-w-md"
                @mousedown="startDraw" @mousemove="draw" @mouseup="endDraw"></canvas>
        <input type="hidden" name="signature" x-model="signature">
        <button type="submit" @click="captureSig()" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Finalize &amp; send</button>
    </form>
    <script>
    function dischargeSig() {
        return {
            signature: '', drawing: false, ctx: null,
            init() { this.$nextTick(() => { this.ctx = this.$refs.canvas.getContext('2d'); this.ctx.strokeStyle = '#111'; this.ctx.lineWidth = 2; }); },
            startDraw(e) { this.drawing = true; this.ctx.beginPath(); this.ctx.moveTo(e.offsetX, e.offsetY); },
            draw(e) { if (!this.drawing) return; this.ctx.lineTo(e.offsetX, e.offsetY); this.ctx.stroke(); },
            endDraw() { this.drawing = false; },
            captureSig() { this.signature = this.$refs.canvas.toDataURL('image/png'); },
        };
    }
    </script>
    <?php endif; ?>
</div>
