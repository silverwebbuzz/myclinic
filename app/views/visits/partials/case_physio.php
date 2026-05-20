<label class="block text-sm"><span class="font-medium">Pain location</span>
    <input type="text" :disabled="!editable" x-model="case_taking.pain_location" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
</label>
<label class="block text-sm"><span class="font-medium">ROM assessment</span>
    <textarea :disabled="!editable" x-model="case_taking.rom_assessment" rows="3" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
</label>
<label class="block text-sm"><span class="font-medium">Functional goals</span>
    <textarea :disabled="!editable" x-model="case_taking.functional_goals" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
</label>
