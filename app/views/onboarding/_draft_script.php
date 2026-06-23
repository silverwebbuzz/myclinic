<script>
(function () {
    var form = document.querySelector('form[data-onboarding-draft]');
    if (!form) return;

    var draftUrl = form.getAttribute('data-onboarding-draft');
    var csrf = form.querySelector('[name="_csrf"]');
    var statusEl = document.getElementById('onboarding-draft-status');
    var dirty = false;
    var timer = null;
    var saving = false;

    function setStatus(text, tone) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.className = 'text-xs ' + (tone === 'ok' ? 'text-emerald-600' : tone === 'err' ? 'text-red-600' : 'text-slate-400');
    }

    function markDirty() {
        dirty = true;
        setStatus('Unsaved changes…', 'muted');
        clearTimeout(timer);
        timer = setTimeout(saveDraft, 2500);
    }

    async function saveDraft() {
        if (!dirty || saving || !draftUrl) return;
        saving = true;
        setStatus('Saving draft…', 'muted');
        try {
            var body = new FormData(form);
            var r = await fetch(draftUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrf ? csrf.value : '',
                },
            });
            var data = {};
            try { data = await r.json(); } catch (e) { /* non-json */ }
            if (r.ok && data.ok) {
                dirty = false;
                var when = data.saved_at ? new Date(data.saved_at).toLocaleTimeString() : '';
                setStatus(when ? 'Draft saved · ' + when : 'Draft saved', 'ok');
            } else {
                setStatus(data.error || 'Could not save draft', 'err');
            }
        } catch (e) {
            setStatus('Draft save failed — check connection', 'err');
        }
        saving = false;
    }

    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    form.addEventListener('submit', function () { dirty = false; });

    window.addEventListener('beforeunload', function (e) {
        if (!dirty) return;
        e.preventDefault();
        e.returnValue = '';
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden' && dirty) {
            saveDraft();
        }
    });

    setStatus('Changes save automatically as you go', 'muted');
})();
</script>
