(function () {
    function fdRoot() {
        return document.querySelector('[x-data*="findDoctor"]');
    }

    function fdSearchWrap() {
        return document.querySelector('.fd-search');
    }

    function fdInputs() {
        const wrap = fdSearchWrap();
        if (!wrap) return { q: '', loc: '' };
        const inputs = wrap.querySelectorAll('input[type="text"]');
        return {
            q: (inputs[0] && inputs[0].value ? inputs[0].value : '').trim(),
            loc: (inputs[1] && inputs[1].value ? inputs[1].value : '').trim(),
        };
    }

    function fdAlpine() {
        const root = fdRoot();
        if (!root || !window.Alpine) return null;
        try {
            return Alpine.$data(root);
        } catch (e) {
            return null;
        }
    }

    function fdAppendLoc(params) {
        if (params.has('city') || params.has('state') || params.has('area') || params.has('loc')) {
            return params;
        }
        const data = fdAlpine();
        if (data?.locValue) {
            return params;
        }
        const u = new URLSearchParams(window.location.search);
        const { loc } = fdInputs();
        if (!loc || u.get('loc') !== loc) {
            return params;
        }
        params.set('loc', loc);
        return params;
    }

    function fdNavigateSearch() {
        const { q, loc } = fdInputs();
        const data = fdAlpine();
        const params = new URLSearchParams();

        if (q) params.set('q', q);
        if (loc) params.set('loc', loc);
        if (data) {
            if (data.country) params.set('country', data.country);
            if (data.spec && data.spec !== 'all') params.set('spec', data.spec);
            if (data.minRating > 0) params.set('min_rating', String(data.minRating));
            if (data.sort && data.sort !== 'relevance') params.set('sort', data.sort);
            if (data.locValue) {
                if (data.locValue.state) params.set('state', data.locValue.state);
                if (data.locValue.city) params.set('city', data.locValue.city);
                if (data.locValue.area) params.set('area', data.locValue.area);
            }
        }

        const target = '/find-a-doctor' + (params.toString() ? '?' + params.toString() : '');
        if (window.location.pathname + window.location.search === target) {
            const alpine = fdAlpine();
            if (alpine && typeof alpine.refresh === 'function') {
                alpine.refresh();
            }
            return;
        }
        window.location.assign(target);
    }

    function fdHydrateLocFromUrl() {
        const u = new URLSearchParams(window.location.search);
        const loc = u.get('loc');
        if (!loc) return;
        const wrap = fdSearchWrap();
        if (!wrap) return;
        const inputs = wrap.querySelectorAll('input[type="text"]');
        if (inputs[1]) inputs[1].value = loc;
        const alpine = fdAlpine();
        if (alpine) alpine.loc = loc;
    }

    const nativeFetch = window.fetch.bind(window);
    window.fetch = function (url, opts) {
        if (typeof url === 'string' && url.indexOf('/api/search_doctors') !== -1) {
            try {
                const u = new URL(url, window.location.origin);
                fdAppendLoc(u.searchParams);
                return nativeFetch(u.toString(), opts);
            } catch (e) {}
        }
        return nativeFetch(url, opts);
    };

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.fd-search .btn-search');
        if (!btn) return;
        e.preventDefault();
        fdNavigateSearch();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const input = e.target.closest('.fd-search input[type="text"]');
        if (!input) return;
        e.preventDefault();
        fdNavigateSearch();
    });

    document.addEventListener('alpine:initialized', fdHydrateLocFromUrl);
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(fdHydrateLocFromUrl, 0);
    });
})();
