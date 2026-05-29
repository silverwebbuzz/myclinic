<label class="block text-sm"><span class="font-medium">Tooth / quadrant</span>
    <input type="text" :disabled="!editable" x-model="case_taking.tooth_quadrant" class="ui-input">
</label>
<label class="block text-sm"><span class="font-medium">Dental findings</span>
    <textarea :disabled="!editable" x-model="case_taking.dental_findings" rows="3" class="ui-input"></textarea>
</label>
<label class="block text-sm"><span class="font-medium">Treatment plan</span>
    <textarea :disabled="!editable" x-model="case_taking.treatment_plan" rows="2" class="ui-input"></textarea>
</label>
