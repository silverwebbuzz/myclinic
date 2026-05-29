<!-- Global modal slot — parent may set modalOpen, modalTitle, modalBody via Alpine -->
<template x-teleport="body">
    <div x-show="typeof modalOpen !== 'undefined' && modalOpen" x-transition.opacity
         class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4"
         @keydown.escape.window="modalOpen = false">
        <div @click.outside="modalOpen = false" class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 class="ui-section-title" x-text="modalTitle || 'Confirm'"></h3>
            <div class="mt-3 text-sm text-slate-600" x-html="modalBody || ''"></div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="modalOpen = false" class="ui-btn ui-btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
</template>
