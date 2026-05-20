<label class="block text-sm"><span class="font-medium">Tooth / quadrant</span>
    <input type="text" :disabled="!editable" x-model="case_taking.tooth_quadrant" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
</label>
<label class="block text-sm"><span class="font-medium">Dental findings</span>
    <textarea :disabled="!editable" x-model="case_taking.dental_findings" rows="3" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
</label>
<label class="block text-sm"><span class="font-medium">Treatment plan</span>
    <textarea :disabled="!editable" x-model="case_taking.treatment_plan" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
</label>
