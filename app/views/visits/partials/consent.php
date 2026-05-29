<section x-show="activeTab === 'consent'" class="ui-card ui-card-pad space-y-4">
    <h3 class="font-semibold">Consent form</h3>
    <?php if (!empty($_GET['consent_signed'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Consent signed and PDF saved.</p>
    <?php endif; ?>
    <?php if (!empty($consent)): ?>
    <?php $consentOk = \App\Services\ConsentService::verifyHash($consent); ?>
    <div class="space-y-2 text-sm">
        <p>Signed by <?= htmlspecialchars($consent['signed_by_name'] ?? '') ?> (<?= htmlspecialchars($consent['relationship'] ?? '') ?>)</p>
        <p class="text-xs text-slate-500">SHA-256: <code class="break-all"><?= htmlspecialchars($consent['content_hash'] ?? '') ?></code></p>
        <p class="text-xs <?= $consentOk ? 'text-emerald-600' : 'text-red-600' ?>">
            Verification: <?= $consentOk ? 'OK' : 'Hash mismatch' ?>
        </p>
        <?php if (!empty($consent['pdf_path'])): ?>
        <a href="<?= htmlspecialchars($consent['pdf_path']) ?>" target="_blank" class="text-emerald-600 hover:underline">Download signed PDF</a>
        <?php endif; ?>
    </div>
    <?php elseif ($editable): ?>
    <form method="post" action="/visits/<?= (int) $visit['id'] ?>/consent" class="space-y-4" x-data="consentPad()">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div>
            <label class="text-xs font-medium">Template</label>
            <select class="ui-input" @change="loadTemplate($event)">
                <option value="">— Custom —</option>
                <?php foreach ($consentTemplates as $tpl): ?>
                <option value="<?= (int) $tpl['id'] ?>" data-content="<?= htmlspecialchars($tpl['content'] ?? '') ?>"><?= htmlspecialchars($tpl['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="text-xs font-medium">Form content</label>
            <textarea name="form_content" x-model="content" rows="8" class="ui-input font-mono"></textarea>
            <p class="mt-1 text-xs text-slate-500">Merge fields: {{patient_name}}, {{uhid}}, {{clinic_name}}, {{date}}, {{procedure}}, {{doctor_name}}</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="text-xs font-medium">Signed by</label>
                <input name="signed_by_name" value="<?= htmlspecialchars($patient['name']) ?>" class="ui-input">
            </div>
            <div>
                <label class="text-xs font-medium">Relationship</label>
                <select name="relationship" class="ui-input">
                    <option value="self">Self</option>
                    <option value="guardian">Guardian</option>
                    <option value="spouse">Spouse</option>
                </select>
            </div>
        </div>
        <div>
            <label class="text-xs font-medium">Witness (optional)</label>
            <input name="witness_name" class="ui-input">
        </div>
        <div>
            <label class="text-xs font-medium block mb-1">Signature</label>
            <canvas x-ref="canvas" width="400" height="120" class="border rounded-lg bg-white touch-none w-full max-w-md"
                    @mousedown="startDraw" @mousemove="draw" @mouseup="endDraw" @mouseleave="endDraw"
                    @touchstart.prevent="startDrawTouch" @touchmove.prevent="drawTouch" @touchend="endDraw"></canvas>
            <button type="button" @click="clear()" class="mt-1 text-xs text-slate-500">Clear</button>
            <input type="hidden" name="signature" x-model="signature">
        </div>
        <button type="submit" @click="captureSig()" class="ui-btn ui-btn-primary">Sign &amp; save PDF</button>
    </form>
    <script>
    function consentPad() {
        return {
            content: '',
            signature: '',
            drawing: false,
            ctx: null,
            init() { this.$nextTick(() => { this.ctx = this.$refs.canvas.getContext('2d'); this.ctx.strokeStyle = '#111'; this.ctx.lineWidth = 2; }); },
            loadTemplate(e) {
                const opt = e.target.selectedOptions[0];
                if (opt?.dataset.content) this.content = opt.dataset.content;
            },
            startDraw(e) { this.drawing = true; this.ctx.beginPath(); this.ctx.moveTo(e.offsetX, e.offsetY); },
            draw(e) { if (!this.drawing) return; this.ctx.lineTo(e.offsetX, e.offsetY); this.ctx.stroke(); },
            endDraw() { this.drawing = false; },
            startDrawTouch(e) { const r = this.$refs.canvas.getBoundingClientRect(); const t = e.touches[0]; this.drawing = true; this.ctx.beginPath(); this.ctx.moveTo(t.clientX - r.left, t.clientY - r.top); },
            drawTouch(e) { if (!this.drawing) return; const r = this.$refs.canvas.getBoundingClientRect(); const t = e.touches[0]; this.ctx.lineTo(t.clientX - r.left, t.clientY - r.top); this.ctx.stroke(); },
            clear() { this.ctx.clearRect(0, 0, 400, 120); this.signature = ''; },
            captureSig() { this.signature = this.$refs.canvas.toDataURL('image/png'); },
        };
    }
    </script>
    <?php else: ?>
    <p class="text-sm text-slate-500">No consent on file.</p>
    <?php endif; ?>
</section>
