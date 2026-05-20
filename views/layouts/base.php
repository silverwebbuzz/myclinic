<!DOCTYPE html>
<html lang="en" x-data="appShell()" class="antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'ManageClinic') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: {
                            DEFAULT: 'var(--brand)',
                            light: 'var(--brand-light)',
                            dark: 'var(--brand-dark)',
                        },
                    },
                },
            },
        };
    </script>
    <?php
        $brandHex = $brandColor ?? '#0F9B6E';
        // Compute light/dark variants for nicer hover/active states.
        $h = ltrim((string) $brandHex, '#');
        $r = (int) hexdec(substr($h, 0, 2));
        $g = (int) hexdec(substr($h, 2, 2));
        $b = (int) hexdec(substr($h, 4, 2));
        $brandLight = sprintf('rgba(%d, %d, %d, 0.1)', $r, $g, $b);
        $brandDark = sprintf('rgb(%d, %d, %d)', (int) max(0, $r - 30), (int) max(0, $g - 30), (int) max(0, $b - 30));
    ?>
    <style>
        :root {
            --brand: <?= htmlspecialchars($brandHex) ?>;
            --brand-light: <?= htmlspecialchars($brandLight) ?>;
            --brand-dark: <?= htmlspecialchars($brandDark) ?>;
        }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        .bg-brand { background-color: var(--brand); }
        .bg-brand-light { background-color: var(--brand-light); }
        .text-brand { color: var(--brand); }
        .border-brand { border-color: var(--brand); }
        .hover\:bg-brand-dark:hover { background-color: var(--brand-dark); }
        .ring-brand:focus { --tw-ring-color: var(--brand); }
        /* Smoother sidebar item active state */
        .nav-item-active {
            background-color: var(--brand-light);
            color: var(--brand);
            font-weight: 600;
        }
        .nav-item-active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.25rem;
            bottom: 0.25rem;
            width: 3px;
            border-radius: 0 4px 4px 0;
            background-color: var(--brand);
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require dirname(__DIR__) . '/components/impersonation-banner.php'; ?>
    <div class="flex min-h-screen" @keydown.escape.window="sidebarOpen = false">
        <!-- Sidebar overlay (tablet/mobile) -->
        <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/40 lg:hidden" @click="sidebarOpen = false"></div>

        <?php
            $currentUri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            $isActive = static function (string $href) use ($currentUri): bool {
                $hrefPath = (string) parse_url($href, PHP_URL_PATH);
                if ($hrefPath === '' || $hrefPath === '/') return $currentUri === '/';
                // Dashboard matches only on /dashboard exactly.
                if ($hrefPath === '/dashboard') return $currentUri === '/dashboard';
                // Other items active when path begins with the href path segment.
                return $currentUri === $hrefPath || str_starts_with($currentUri, $hrefPath . '/');
            };
        ?>
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:static lg:z-auto">
            <div class="flex h-16 items-center gap-3 border-b border-slate-100 px-5">
                <?php if (!empty($logoUrl)): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="" class="h-9 w-9 rounded-lg object-cover shadow-sm">
                <?php else: ?>
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand text-sm font-bold text-white shadow-sm">
                        <?= htmlspecialchars(mb_substr($clinic['name'] ?? 'M', 0, 1)) ?>
                    </span>
                <?php endif; ?>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900"><?= htmlspecialchars($clinic['name'] ?? 'Clinic') ?></p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Clinic admin</p>
                </div>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm">
                <?php
                    $dashHref = $nav['dashboard']['href'] ?? '/dashboard';
                    $dashActive = $isActive($dashHref);
                ?>
                <a href="<?= htmlspecialchars($dashHref) ?>"
                   class="relative mb-2 flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $dashActive ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <span class="text-base"><?= $nav['dashboard']['icon'] ?? '🏠' ?></span>
                    <span>Dashboard</span>
                </a>
                <?php foreach ($nav['groups'] ?? [] as $group): ?>
                <p class="mb-1 mt-5 px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars($group['label']) ?></p>
                <?php foreach ($group['items'] as $item):
                    $active = $isActive((string) $item['href']);
                ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"
                   class="relative flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $active ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <span class="text-base"><?= $item['icon'] ?></span>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
                <?php endforeach; ?>
                <?php endforeach; ?>

                <p class="mb-1 mt-5 px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Account</p>
                <a href="/settings"
                   class="relative flex items-center gap-3 rounded-lg px-3 py-2 transition <?= $isActive('/settings') ? 'nav-item-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <span class="text-base">⚙️</span><span>Settings</span>
                </a>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-6 backdrop-blur">
                <div class="flex items-center gap-3">
                    <button type="button" @click="sidebarOpen = !sidebarOpen" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Menu">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <h1 class="text-base font-semibold text-slate-900"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </button>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg p-1 hover:bg-slate-100">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand text-sm font-semibold text-white shadow-sm">
                                <?= htmlspecialchars(mb_substr($user['name'] ?? 'U', 0, 1)) ?>
                            </span>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition
                             class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="truncate text-sm font-semibold text-slate-900"><?= htmlspecialchars($user['name'] ?? '') ?></p>
                                <p class="truncate text-xs text-slate-500"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                            </div>
                            <a href="/settings?tab=general" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Clinic settings</a>
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

            <main class="flex-1 p-5 lg:p-8">
                <?= $content ?? '' ?>
            </main>
            <?php
            $hidePoweredBy = \App\Services\WhiteLabelService::hidePoweredBy($clinic ?? []);
            if (!$hidePoweredBy):
            ?>
            <footer class="border-t px-4 py-3 text-center text-xs text-slate-400">
                Powered by <a href="https://manageclinic.com" class="hover:text-slate-600">ManageClinic</a>
            </footer>
            <?php endif; ?>
        </div>
    </div>

    <?php require dirname(__DIR__) . '/components/toast.php'; ?>
    <?php require dirname(__DIR__) . '/components/modal.php'; ?>

    <script>
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
