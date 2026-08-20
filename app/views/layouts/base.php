<?php require_once dirname(__DIR__) . '/components/ui.php'; ?>
<!DOCTYPE html>
<html lang="en" x-data="appShell()" class="antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf ?? '') ?>">
    <title><?= htmlspecialchars($title ?? 'eClinicPro') ?></title>
    <script>
    // Attach the session CSRF token to every same-origin mutating fetch().
    // One global hook instead of per-call-site headers, so /api/v1 POSTs pass
    // CsrfMiddleware without touching (or missing) any of the existing calls.
    (function () {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!token) return;
        const orig = window.fetch;
        window.fetch = function (input, init) {
            try {
                const url = typeof input === 'string' ? input : ((input && input.url) || '');
                const method = ((init && init.method) || (typeof input === 'object' && input && input.method) || 'GET').toUpperCase();
                const sameOrigin = url.startsWith('/') || url.startsWith(window.location.origin);
                if (sameOrigin && method !== 'GET' && method !== 'HEAD') {
                    init = init || {};
                    const headers = new Headers(init.headers || (typeof input === 'object' && input ? input.headers : undefined) || {});
                    if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token);
                    init.headers = headers;
                }
            } catch (e) { /* fall back to the original call untouched */ }
            return orig.call(this, input, init);
        };
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="https://eclinicpro.com/assets/img/logos/favicon.svg">
    <link rel="icon" type="image/png" sizes="64x64" href="https://eclinicpro.com/assets/img/logos/favicon.png">
    <link rel="apple-touch-icon" href="https://eclinicpro.com/assets/img/logos/apple-touch-icon.png">
    <?php $assetVer = @filemtime(dirname(__DIR__, 2) . '/public/assets/app.css') ?: '1'; ?>
    <link rel="stylesheet" href="/assets/app.css?v=<?= $assetVer ?>">
    <!-- Collapse plugin must load before Alpine core so x-collapse registers. -->
    <script defer src="/assets/alpine-collapse.min.js?v=<?= $assetVer ?>"></script>
    <script defer src="/assets/alpine.min.js?v=<?= $assetVer ?>"></script>
    <?php
        // Design-system primary: Teal 600. Clinics that never picked a custom
        // brand carry the legacy green default in the DB — migrate it here so
        // the whole fleet moves to the new palette without a data change.
        $brandHex = $brandColor ?? '#0F766E';
        if (strtoupper(ltrim((string) $brandHex, '#')) === '0F9B6E') {
            $brandHex = '#0F766E';
        }

        if (strtoupper(ltrim((string) $brandHex, '#')) === '0F766E') {
            // Spec palette: exact hover / light / soft values.
            $brandDark = '#115E59';
            $brandLight = '#CCFBF1';
            $brandSoft = '#F0FDFA';
        } else {
            // Custom clinic brand — derive variants.
            $h = ltrim((string) $brandHex, '#');
            $r = (int) hexdec(substr($h, 0, 2));
            $g = (int) hexdec(substr($h, 2, 2));
            $b = (int) hexdec(substr($h, 4, 2));
            $brandLight = sprintf('rgba(%d, %d, %d, 0.12)', $r, $g, $b);
            $brandSoft = sprintf('rgba(%d, %d, %d, 0.05)', $r, $g, $b);
            $brandDark = sprintf('rgb(%d, %d, %d)', (int) max(0, $r - 30), (int) max(0, $g - 30), (int) max(0, $b - 30));
        }
    ?>
    <style>
        :root {
            --brand: <?= htmlspecialchars($brandHex) ?>;
            --brand-light: <?= htmlspecialchars($brandLight) ?>;
            --brand-dark: <?= htmlspecialchars($brandDark) ?>;
            --brand-soft: <?= htmlspecialchars($brandSoft) ?>;
        }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        .bg-brand { background-color: var(--brand); }
        .bg-brand-light { background-color: var(--brand-light); }
        .bg-brand-soft { background-color: var(--brand-soft); }
        .text-brand { color: var(--brand); }
        .border-brand { border-color: var(--brand); }
        .hover\:bg-brand-dark:hover { background-color: var(--brand-dark); }
        .ring-brand:focus { --tw-ring-color: var(--brand); }
        /* Sidebar active state — solid brand pill, white text + icon */
        .nav-item-active {
            background-color: var(--brand);
            color: #fff;
            font-weight: 600;
        }
        .nav-item-active svg { color: #fff; }
        .nav-item-active:hover { background-color: var(--brand-dark); }
        /* Hide Alpine-controlled overlays until JS boots (prevents flash on navigation). */
        [x-cloak] { display: none !important; }
    </style>
    <?php require dirname(__DIR__) . '/components/ui_tokens.php'; ?>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require dirname(__DIR__) . '/components/impersonation-banner.php'; ?>
    <div class="flex min-h-screen" @keydown.escape.window="sidebarOpen = false">
        <!-- Sidebar overlay (tablet/mobile) -->
        <div x-show="sidebarOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-black/40 lg:hidden" @click="sidebarOpen = false"></div>

        <?php
            $currentUri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            $isActive = static function (string $href) use ($currentUri): bool {
                $hrefPath = (string) parse_url($href, PHP_URL_PATH);
                if ($hrefPath === '' || $hrefPath === '/') return $currentUri === '/';
                // Dashboard matches only on /dashboard exactly.
                if ($hrefPath === '/dashboard') return $currentUri === '/dashboard';
                // Clinic settings — exact path only (not /settings/team, /settings/password, etc.).
                if ($hrefPath === '/settings') return $currentUri === '/settings';
                // Other items active when path begins with the href path segment.
                return $currentUri === $hrefPath || str_starts_with($currentUri, $hrefPath . '/');
            };

            // Map legacy emoji icons (config/modules_nav.php) to SVG registry
            // names for the enterprise look, without changing the config shape.
            $emojiToIcon = [
                '🏠' => 'dashboard', '👤' => 'patients', '📋' => 'emr', '💊' => 'prescription',
                '❤️' => 'vitals',
                '📅' => 'appointments', '🧾' => 'billing', '💬' => 'whatsapp',
                '📊' => 'analytics', '👥' => 'staff',
            ];
            $navIcon = static function (string $raw) use ($emojiToIcon): string {
                // Already an SVG name in the registry? use as-is; else map emoji.
                $name = $emojiToIcon[$raw] ?? $raw;
                return ui_icon($name, 18, 'shrink-0');
            };
        ?>
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="fixed inset-y-0 left-0 z-50 flex w-[280px] flex-col border-r border-slate-200 bg-white transition-transform duration-150 lg:static lg:z-auto">
            <div class="flex h-[72px] items-center gap-3 border-b border-slate-100 px-5">
                <?php if (!empty($logoUrl)): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="" class="h-9 w-9 rounded-lg object-cover shadow-sm">
                <?php else: ?>
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand text-sm font-bold text-white shadow-sm">
                        <?= htmlspecialchars(mb_substr($clinic['name'] ?? 'M', 0, 1)) ?>
                    </span>
                <?php endif; ?>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900"><?= htmlspecialchars($clinic['name'] ?? 'Clinic') ?></p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-400"><?= htmlspecialchars($panelRoleLabel ?? \App\Services\RoleAccessService::panelRoleLabel($user ?? [])) ?></p>
                </div>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm">
                <?php
                    $dashHref = $nav['dashboard']['href'] ?? '/dashboard';
                    $dashActive = $isActive($dashHref);
                ?>
                <a href="<?= htmlspecialchars($dashHref) ?>"
                   class="relative mb-2 flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $dashActive ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <?= $navIcon((string) ($nav['dashboard']['icon'] ?? 'dashboard')) ?>
                    <span>Dashboard</span>
                </a>
                <?php foreach ($nav['groups'] ?? [] as $group): ?>
                <p class="ui-group-label mb-1 mt-5 px-3"><?= htmlspecialchars($group['label']) ?></p>
                <?php foreach ($group['items'] as $item):
                    if (!\App\Services\RoleAccessService::canSeeNavHref($user ?? [], (string) $item['href'])) {
                        continue;
                    }
                    $active = $isActive((string) $item['href']);
                ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"
                   class="relative flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $active ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <?= $navIcon((string) $item['icon']) ?>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
                <?php endforeach; ?>
                <?php endforeach; ?>

                <p class="ui-group-label mb-1 mt-5 px-3">Account</p>
                <?php if (\App\Services\RoleAccessService::canSeeNavHref($user ?? [], '/follow-ups')): ?>
                <a href="/follow-ups"
                   class="relative flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $isActive('/follow-ups') ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <?= ui_icon('bell', 18, 'shrink-0') ?><span>Follow-ups</span>
                </a>
                <?php endif; ?>
                <?php if (\App\Services\RoleAccessService::canManageOwnSchedule($user ?? []) || \App\Services\RoleAccessService::isClinicAdmin($user ?? [])): ?>
                <a href="/blogs"
                   class="relative flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $isActive('/blogs') ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <?= ui_icon('emr', 18, 'shrink-0') ?><span>Blogs</span>
                </a>
                <?php endif; ?>
                <?php if (\App\Services\RoleAccessService::canManageOwnSchedule($user ?? [])): ?>
                <a href="/doctor/schedule"
                   class="relative flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $isActive('/doctor/schedule') ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <?= ui_icon('appointments', 18, 'shrink-0') ?><span>My schedule</span>
                </a>
                <?php endif; ?>
                <?php if (\App\Services\RoleAccessService::isClinicAdmin($user ?? [])): ?>
                <a href="/settings"
                   class="relative flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $isActive('/settings') ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <?= ui_icon('settings', 18, 'shrink-0') ?><span>Settings</span>
                </a>
                <a href="/listing"
                   class="relative flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $isActive('/listing') ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <?= ui_icon('clinic', 18, 'shrink-0') ?><span>Listed on eClinicPro</span>
                </a>
                <a href="/settings/team"
                   class="relative flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $isActive('/settings/team') ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <?= ui_icon('staff', 18, 'shrink-0') ?><span>Team</span>
                </a>
                <a href="/leaves"
                   class="relative flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $isActive('/leaves') ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <?= ui_icon('appointments', 18, 'shrink-0') ?><span>Leaves</span>
                </a>
                <a href="/subscription"
                   class="relative flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $isActive('/subscription') ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <?= ui_icon('billing', 18, 'shrink-0') ?><span>Subscription</span>
                </a>
                <?php endif; ?>
                <a href="/help"
                   class="relative flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $isActive('/help') ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <?= ui_icon('help', 18, 'shrink-0') ?><span>Help &amp; Guide</span>
                </a>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-40 flex h-[72px] items-center justify-between border-b border-slate-200 bg-white/95 px-6 backdrop-blur">
                <div class="flex items-center gap-3">
                    <button type="button" @click="sidebarOpen = !sidebarOpen" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Menu">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <h1 class="ui-page-title hidden sm:block"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
                </div>

                <!-- Global patient search — jump to any patient from anywhere -->
                <div x-data="patientQuickSearch()" @keydown.escape="close()" @click.outside="close()"
                     class="relative mx-3 max-w-md flex-1">
                    <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 focus-within:border-emerald-400 focus-within:bg-white">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-400"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" x-model="q" @input.debounce.250ms="run()" @focus="run()"
                               @keydown.enter.prevent="openFirst()"
                               placeholder="Search patient by name or phone…"
                               class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-0">
                        <span x-show="loading" x-cloak class="text-xs text-slate-400">…</span>
                    </div>
                    <div x-show="open && (results.length || (q.trim().length >= 2 && !loading))" x-cloak
                         class="absolute left-0 right-0 z-40 mt-1 max-h-80 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                        <template x-for="p in results" :key="p.id">
                            <a :href="'/patients/' + p.id" target="_blank" rel="noopener"
                               class="flex items-center justify-between gap-3 border-b border-slate-50 px-3 py-2 hover:bg-emerald-50">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-slate-800" x-text="p.name"></span>
                                    <span class="block truncate text-xs text-slate-500">
                                        <span x-text="p.uhid || ''"></span>
                                        <span x-show="p.phone" x-text="' · ' + p.phone"></span>
                                        <span x-show="p.gender" x-text="' · ' + p.gender"></span>
                                    </span>
                                </span>
                                <span class="shrink-0 text-xs text-emerald-600">Open ↗</span>
                            </a>
                        </template>
                        <div x-show="!loading && results.length === 0 && q.trim().length >= 2"
                             class="px-3 py-3 text-center text-xs text-slate-400">No patient found for "<span x-text="q.trim()"></span>"</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Notifications">
                        <?= ui_icon('bell', 18) ?>
                    </button>
                    <div x-data="{ open: false }" class="relative z-50">
                        <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg p-1 hover:bg-slate-100">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand text-sm font-semibold text-white shadow-sm">
                                <?= htmlspecialchars(mb_substr($user['name'] ?? 'U', 0, 1)) ?>
                            </span>
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false" x-transition
                             class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="truncate text-sm font-semibold text-slate-900"><?= htmlspecialchars($user['name'] ?? '') ?></p>
                                <p class="truncate text-xs text-slate-500">
                                    <?= htmlspecialchars($user['email'] ?? ($user['phone'] ?? '')) ?>
                                </p>
                            </div>
                            <?php if (\App\Services\RoleAccessService::isClinicAdmin($user ?? [])): ?>
                            <a href="/settings?tab=general" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Clinic settings</a>
                            <a href="/settings/team" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Team</a>
                            <?php endif; ?>
                            <a href="/settings/profile" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">My profile</a>
                            <a href="/settings/password" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Password</a>
                            <a href="/settings/sessions" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Sessions</a>
                            <form method="post" action="/logout" class="border-t border-slate-100">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                <button type="submit" class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">Log out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1600px] flex-1 p-4 lg:p-6">
                <?= $content ?? '' ?>
            </main>
            <?php
            $hidePoweredBy = \App\Services\WhiteLabelService::hidePoweredBy($clinic ?? []);
            if (!$hidePoweredBy):
            ?>
            <footer class="border-t px-4 py-3 text-center text-xs text-slate-400">
                © <?= date('Y') ?> eClinicPro — a brand of <a href="https://silverwebbuzz.com" target="_blank" rel="noopener" class="hover:text-slate-600">Silver Webbuzz Pvt Ltd</a> · Made with care for clinics across India 🌿
            </footer>
            <?php endif; ?>
        </div>
    </div>

    <?php require dirname(__DIR__) . '/components/toast.php'; ?>
    <?php require dirname(__DIR__) . '/components/modal.php'; ?>

    <script>
    window.copyClinicText = function (text, btn) {
        const showCopied = function () {
            if (!btn || !btn.parentElement) return;
            const hint = btn.parentElement.querySelector('.copy-done');
            if (hint) {
                hint.classList.remove('hidden');
                window.setTimeout(function () { hint.classList.add('hidden'); }, 2000);
            }
        };
        const fallback = function () {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
                document.execCommand('copy');
                showCopied();
            } catch (e) { /* ignore */ }
            document.body.removeChild(ta);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(showCopied).catch(fallback);
        } else {
            fallback();
        }
    };

    // Global patient quick-search (header). Jumps to any patient by name/phone;
    // opens the profile in a new tab so the doctor keeps their current screen.
    function patientQuickSearch() {
        return {
            q: '',
            results: [],
            loading: false,
            open: false,
            async run() {
                const q = (this.q || '').trim();
                if (q.length < 2) { this.results = []; this.open = q.length > 0; return; }
                this.open = true;
                this.loading = true;
                try {
                    const r = await fetch('/api/v1/patients/search?q=' + encodeURIComponent(q), {
                        credentials: 'same-origin', headers: { 'Accept': 'application/json' },
                    });
                    const data = await r.json();
                    this.results = (data.rows || []).slice(0, 8);
                } catch (e) { this.results = []; }
                this.loading = false;
            },
            openFirst() {
                if (this.results.length > 0) {
                    window.open('/patients/' + this.results[0].id, '_blank', 'noopener');
                    this.close();
                }
            },
            close() { this.open = false; },
        };
    }

    function appShell() {
        return {
            sidebarOpen: false,
            toast: { show: false, message: '', type: 'success' },
            modalOpen: false,
            modalTitle: '',
            modalBody: '',
            modalConfirm: null,
            showToast(message, type = 'success') {
                this.toast = { show: true, message, type };
                setTimeout(() => { this.toast.show = false; }, 4000);
            },
            showModal(title, body, onConfirm = null) {
                this.modalTitle = title;
                this.modalBody = body;
                this.modalConfirm = onConfirm;
                this.modalOpen = true;
            },
            closeModal() {
                this.modalOpen = false;
                this.modalConfirm = null;
            }
        };
    }
    </script>
</body>
</html>
