<label class="block text-sm"><span class="font-medium">Lesion description</span>
    <textarea :disabled="!editable" x-model="case_taking.lesion_description" rows="3" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
</label>
<p class="text-xs text-slate-500">Tap body regions to mark affected areas (stored in case data).</p>
<svg viewBox="0 0 120 200" class="mx-auto h-48 w-32 text-slate-300" @click="case_taking.body_map_note = 'Region marked at ' + new Date().toISOString()">
    <ellipse cx="60" cy="25" rx="18" ry="22" fill="currentColor" opacity="0.2" class="cursor-pointer hover:opacity-40"/>
    <rect x="40" y="50" width="40" height="55" rx="8" fill="currentColor" opacity="0.2" class="cursor-pointer hover:opacity-40"/>
    <rect x="25" y="55" width="12" height="45" rx="4" fill="currentColor" opacity="0.15"/>
    <rect x="83" y="55" width="12" height="45" rx="4" fill="currentColor" opacity="0.15"/>
    <rect x="42" y="108" width="14" height="55" rx="4" fill="currentColor" opacity="0.15"/>
    <rect x="64" y="108" width="14" height="55" rx="4" fill="currentColor" opacity="0.15"/>
</svg>
<label class="block text-sm"><span class="font-medium">Body map notes</span>
    <input type="text" :disabled="!editable" x-model="case_taking.body_map_note" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
</label>
