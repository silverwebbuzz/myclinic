<label class="block text-sm"><span class="font-medium">Pain location</span>
    <input type="text" :disabled="!editable" x-model="case_taking.pain_location" class="ui-input">
</label>
<label class="block text-sm"><span class="font-medium">ROM assessment</span>
    <textarea :disabled="!editable" x-model="case_taking.rom_assessment" rows="3" class="ui-input"></textarea>
</label>
<label class="block text-sm"><span class="font-medium">Functional goals</span>
    <textarea :disabled="!editable" x-model="case_taking.functional_goals" rows="2" class="ui-input"></textarea>
</label>
