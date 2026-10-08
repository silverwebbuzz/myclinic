/* Homepage hero slider — markup in partials/hero-slider.php.
   HH_CONFIG maps the search forms and card buttons to site routes. A search
   form or button with no route falls back to the info dialog. */
const HH_CONFIG = {
    interval: 6500,
    searchRoutes: {
        Doctors: { url: '/find-a-doctor', query: 'q', location: 'loc' },
        Products: { url: '/store/search', query: 'q' },
        Diagnostics: { url: '/lab', query: 'q' }
    },
    actions: {
        'Book Appointment': '/find-a-doctor',
        'Doctor Availability': '/find-a-doctor',
        'Prescriptions': '/for-patients#rx',
        'Secure Payments': '/security',
        'Flat 20% OFF': '/store/',
        'Free Delivery': '/store/',
        'Medicines & Healthcare': '/store/search?q=medicines',
        'Vitamins & Supplements': '/store/search?q=vitamins',
        'Personal Care & Wellness': '/store/search?q=personal%20care',
        'More Categories': '/store/categories',
        'Book Lab Test': '/lab',
        'Home Sample Collection': '/lab',
        'Full Body Checkup': '/lab',
        'Thyrocare Partner': '/lab'
    }
};

(() => {
    const root = document.querySelector('.hh-slider');
    if (!root) return;

    const slides = [...root.querySelectorAll('.hh-slide')];
    const tabs = [...root.querySelectorAll('.hh-tab')];
    const play = root.querySelector('.hh-play');
    const bar = root.querySelector('.hh-timer span');
    const modal = root.querySelector('.hh-modal');
    const reduce = matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0, elapsed = 0, last = performance.now(), playing = !reduce.matches, hover = false, focus = false, start = null;

    const icon = name => `<svg class="hh-icon" aria-hidden="true"><use href="#hh-i-${name}"/></svg>`;

    root.querySelectorAll('[data-feature]').forEach(card => {
        card.innerHTML = `<span class="hh-bubble">${icon(card.dataset.icon)}</span><div><b>${card.dataset.feature}</b><small>${card.dataset.sub}</small></div><button type="button" class="hh-mini-arrow" aria-label="${card.dataset.feature}">${icon('arrow')}</button>`;
        card.querySelector('button').addEventListener('click', () => action(card.dataset.feature));
    });

    function message(title, text) {
        modal.querySelector('h3').textContent = title;
        modal.querySelector('p').textContent = text;
        modal.showModal();
    }

    function action(name) {
        const url = HH_CONFIG.actions[name];
        if (url) { location.href = url; return; }
        message(name, 'Coming soon.');
    }

    function show(n, manual = false) {
        index = (n + slides.length) % slides.length;
        slides.forEach((s, i) => {
            s.classList.toggle('hh-active', i === index);
            s.inert = i !== index;
            s.setAttribute('aria-hidden', String(i !== index));
            tabs[i].setAttribute('aria-current', String(i === index));
        });
        elapsed = 0;
        bar.style.transform = 'scaleX(0)';
        if (manual) root.querySelector('.hh-announce').textContent = `Slide ${index + 1} of ${slides.length}: ${slides[index].dataset.name}`;
    }

    function update() {
        play.textContent = playing ? 'Ⅱ' : '▶';
        play.setAttribute('aria-label', playing ? 'Pause slideshow' : 'Play slideshow');
    }

    root.querySelector('.hh-previous').onclick = () => show(index - 1, true);
    root.querySelector('.hh-next').onclick = () => show(index + 1, true);
    tabs.forEach((t, i) => t.onclick = () => show(i, true));
    play.onclick = () => { playing = !playing; update(); };

    root.addEventListener('keydown', e => {
        if (e.target.matches('input,select,textarea')) return;
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            e.preventDefault();
            show(index + (e.key === 'ArrowRight' ? 1 : -1), true);
            tabs[index].focus();
        }
    });

    root.addEventListener('pointerenter', e => { if (e.pointerType === 'mouse') hover = true; });
    root.addEventListener('pointerleave', () => hover = false);
    root.addEventListener('focusin', () => focus = true);
    root.addEventListener('focusout', e => focus = root.contains(e.relatedTarget));

    root.addEventListener('touchstart', e => {
        if (e.target.closest('input,select,button')) return;
        start = { x: e.touches[0].clientX, y: e.touches[0].clientY };
    }, { passive: true });
    root.addEventListener('touchend', e => {
        if (!start) return;
        const dx = e.changedTouches[0].clientX - start.x, dy = e.changedTouches[0].clientY - start.y;
        start = null;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.4) show(index + (dx < 0 ? 1 : -1), true);
    }, { passive: true });
    root.addEventListener('touchcancel', () => start = null);

    root.querySelectorAll('.hh-chip').forEach(b => b.onclick = () => {
        const input = b.closest('.hh-copy').querySelector('input');
        input.value = b.textContent;
        input.focus();
    });
    root.querySelectorAll('[data-action]').forEach(b => b.onclick = () => action(b.dataset.action));

    root.querySelectorAll('.hh-search').forEach(form => form.addEventListener('submit', e => {
        e.preventDefault();
        const input = form.querySelector('input'), query = input.value.trim(), city = form.querySelector('select').value;
        if (!query) { input.value = ''; input.reportValidity(); return; }
        const route = HH_CONFIG.searchRoutes[form.dataset.kind];
        if (!route) { message(form.dataset.kind + ' search', `“${query}” in ${city}`); return; }
        const url = new URL(route.url, location.href);
        url.searchParams.set(route.query, query);
        if (route.location) url.searchParams.set(route.location, city);
        location.href = url.href;
    }));

    reduce.addEventListener('change', () => { if (reduce.matches) { playing = false; update(); } });

    function frame(t) {
        const delta = Math.min(t - last, 100);
        last = t;
        if (playing && !hover && !focus && !document.hidden && !modal.open) {
            elapsed += delta;
            if (elapsed >= HH_CONFIG.interval) show(index + 1);
            bar.style.transform = `scaleX(${elapsed / HH_CONFIG.interval})`;
        }
        requestAnimationFrame(frame);
    }
    update();
    requestAnimationFrame(frame);
})();
