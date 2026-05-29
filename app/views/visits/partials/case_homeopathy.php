<label class="block text-sm"><span class="font-medium">Mental generals</span>
    <textarea :disabled="!editable" x-model="case_taking.mental_generals" rows="2" class="ui-input"></textarea>
</label>
<label class="block text-sm"><span class="font-medium">Physical generals</span>
    <textarea :disabled="!editable" x-model="case_taking.physical_generals" rows="2" class="ui-input"></textarea>
</label>
<label class="block text-sm"><span class="font-medium">Modalities</span>
    <textarea :disabled="!editable" x-model="case_taking.modalities" rows="2" class="ui-input" placeholder="Better/worse from…"></textarea>
</label>
<label class="block text-sm"><span class="font-medium">Strange, rare, peculiar</span>
    <textarea :disabled="!editable" x-model="case_taking.srp" rows="2" class="ui-input"></textarea>
</label>
