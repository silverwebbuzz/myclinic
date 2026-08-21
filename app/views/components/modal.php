<!--
  Global confirm / alert dialog. Replaces the browser's native confirm()/alert()
  chrome ("app.eclinicpro.com says…") with an in-app modal.

  Use from anywhere:
      if (!await uiConfirm('Complete this visit?')) return;
      await uiAlert('Could not save the visit.', { title: 'Not completed' });

  Both return a Promise (uiConfirm resolves true/false), so calling code reads
  exactly like the native versions it replaced.
-->
<template x-teleport="body">
    <div x-data="uiDialog()" x-init="window.__uiDialog = $data">
        <div x-show="open" x-cloak x-transition.opacity
             class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/40 p-4"
             @keydown.escape.window="cancel()">
            <div @click.outside="cancel()" x-transition
                 role="dialog" aria-modal="true" :aria-label="title"
                 class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="ui-section-title" x-text="title"></h3>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-600" x-show="body" x-text="body"></p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" x-show="mode === 'confirm'" @click="cancel()"
                            class="ui-btn ui-btn-secondary" x-text="cancelLabel"></button>
                    <button type="button" x-ref="confirmBtn" @click="accept()"
                            class="ui-btn"
                            :class="danger ? 'ui-btn-danger' : 'ui-btn-primary'"
                            x-text="confirmLabel"></button>
                </div>
            </div>
        </div>
    </div>
</template>
