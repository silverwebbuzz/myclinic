<label class="block text-sm"><span class="font-medium">Present illness</span>
    <textarea :disabled="!editable" x-model="case_taking.present_illness" rows="3" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
</label>
<label class="block text-sm"><span class="font-medium">Past medical history</span>
    <textarea :disabled="!editable" x-model="case_taking.past_history" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
</label>
<label class="block text-sm"><span class="font-medium">Family history</span>
    <textarea :disabled="!editable" x-model="case_taking.family_history" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
</label>
<label class="block text-sm"><span class="font-medium">Systemic review</span>
    <textarea :disabled="!editable" x-model="case_taking.systemic_review" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
</label>
