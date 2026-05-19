<!DOCTYPE html>
<html lang="en" x-data="appShell()">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'ManageClinic') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root { --brand: <?= htmlspecialchars($brandColor ?? '#0F9B6E') ?>; }
        .bg-brand { background-color: var(--brand); }
        .text-brand { color: var(--brand); }
        .border-brand { border-color: var(--brand); }
        .ring-brand:focus { --tw-ring-color: var(--brand); }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <?php require dirname(__DIR__) . '/components/impersonation-banner.php'; ?>
    <div class="flex min-h-screen" @keydown.escape.window="sidebarOpen = false">
        <!-- Sidebar overlay (tablet/mobile) -->
        <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/40 lg:hidden" @click="sidebarOpen = false"></div>

        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="fixed inset-y-0 left-0 z-50 flex w-60 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:static lg:z-auto">
            <div class="flex h-14 items-center gap-2 border-b px-4">
                <?php if (!empty($logoUrl)): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="" class="h-8 w-8 rounded object-cover">
                <?php else: ?>
                    <span class="flex h-8 w-8 items-center justify-center rounded bg-brand text-sm font-bold text-white">
                        <?= htmlspecialchars(mb_substr($clinic['name'] ?? 'M', 0, 1)) ?>
                    </span>
                <?php endif; ?>
                <span class="truncate text-sm font-semibold"><?= htmlspecialchars($clinic['name'] ?? 'Clinic') ?></span>
            </div>
            <nav class="flex-1 overflow-y-auto p-3 text-sm">
                <a href="<?= htmlspecialchars($nav['dashboard']['href'] ?? '/dashboard') ?>"
                   class="mb-3 flex items-center gap-2 rounded-lg px-3 py-2 font-medium hover:bg-slate-100 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/dashboard') ? 'bg-emerald-50 text-emerald-800' : '' ?>">
                    <span><?= $nav['dashboard']['icon'] ?? '🏠' ?></span> Dashboard
                </a>
                <?php foreach ($nav['groups'] ?? [] as $group): ?>
                <p class="mb-1 mt-4 px-3 text-xs font-semibold uppercase tracking-wide text-slate-400"><?= htmlspecialchars($group['label']) ?></p>
                <?php foreach ($group['items'] as $item): ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"
                   class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">
                    <span><?= $item['icon'] ?></span> <?= htmlspecialchars($item['label']) ?>
                </a>
                <?php endforeach; ?>
                <?php endforeach; ?>
                <p class="mb-1 mt-4 px-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Account</p>
                <a href="/settings" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">⚙️ Settings</a>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b bg-white px-4">
                <div class="flex items-center gap-3">
                    <button type="button" @click="sidebarOpen = !sidebarOpen" class="rounded-lg p-2 hover:bg-slate-100 lg:hidden" aria-label="Menu">☰</button>
                    <h1 class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" class="relative rounded-lg p-2 hover:bg-slate-100" title="Notifications">
                        🔔
                    </button>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-slate-100">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">
                                <?= htmlspecialchars(mb_substr($user['name'] ?? 'U', 0, 1)) ?>
                            </span>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition
                             class="absolute right-0 mt-2 w-48 rounded-lg border bg-white py-1 shadow-lg">
                            <p class="border-b px-3 py-2 text-xs text-slate-500"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                            <a href="/settings?tab=general" class="block px-3 py-2 text-sm hover:bg-slate-50">Clinic settings</a>
                            <a href="/settings/password" class="block px-3 py-2 text-sm hover:bg-slate-50">Password</a>
                            <a href="/settings/sessions" class="block px-3 py-2 text-sm hover:bg-slate-50">Sessions</a>
                            <form method="post" action="/logout" class="border-t">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                <button type="submit" class="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-slate-50">Log out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-6">
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
