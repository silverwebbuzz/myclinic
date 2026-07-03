<?php
/** /admin/wordpress-settings — WordPress API connection (super-admin). */
$val = static function (array $settings, string $key): string {
    $row = $settings[$key] ?? null;
    if (!$row) {
        return '';
    }
    $v = (string) ($row['setting_value'] ?? '');
    if ($v === '') {
        return '';
    }
    if ((int) ($row['is_secret'] ?? 0) === 1) {
        return \App\Support\WordPressSettings::MASK;
    }

    return $v;
};
$labels = [
    'wordpress_api_url' => 'WordPress API URL',
    'wordpress_site_url' => 'WordPress site URL',
    'wordpress_api_user' => 'API username',
    'wordpress_api_app_password' => 'Application password',
    'wordpress_bridge_secret' => 'Bridge secret (HMAC)',
];
$hints = [
    'wordpress_api_url' => 'e.g. https://eclinicpro.com/blog/wp-json',
    'wordpress_site_url' => 'e.g. https://eclinicpro.com/blog (public blog URL, no /wp-json)',
    'wordpress_api_user' => 'WordPress admin username (Administrator role)',
    'wordpress_api_app_password' => 'From WP Admin → Users → Profile → Application Passwords',
    'wordpress_bridge_secret' => 'Same value as in wp-content/mu-plugins/ecp-bridge.php',
];
$statusLabels = [
    'settings_saved' => 'WordPress settings saved.',
    'wordpress_connection_ok' => 'WordPress API connection is working.',
];
$msg = isset($message) ? ($statusLabels[$message] ?? str_replace('_', ' ', (string) $message)) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WordPress Settings · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-3xl px-6 py-6 space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">WordPress connection</h1>
                <p class="text-sm text-slate-500 mt-1">
                    API credentials for the doctor blog bridge. Stored securely in the database — no server file edit needed.
                </p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $wpConfigured ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                <?= $wpConfigured ? 'CONFIGURED' : 'NOT CONFIGURED' ?>
            </span>
        </div>

        <?php if ($msg): ?>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= htmlspecialchars((string) $error) ?></div>
        <?php endif; ?>

        <section class="rounded-xl border bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-800">API credentials</h2>
            <p class="mt-1 text-xs text-slate-500">
                Leave secret fields as <code><?= htmlspecialchars(\App\Support\WordPressSettings::MASK) ?></code> to keep the current value unchanged.
            </p>

            <form method="post" action="/admin/wordpress-settings/save" class="mt-4 space-y-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

                <?php foreach ($labels as $key => $label): ?>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700"><?= htmlspecialchars($label) ?></span>
                    <?php if (!empty($hints[$key])): ?>
                    <span class="block text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($hints[$key]) ?></span>
                    <?php endif; ?>
                    <input type="<?= str_contains($key, 'password') || str_contains($key, 'secret') ? 'password' : 'text' ?>"
                           name="<?= htmlspecialchars($key) ?>"
                           value="<?= htmlspecialchars($val($settings, $key)) ?>"
                           class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-violet-400 focus:outline-none focus:ring-1 focus:ring-violet-400"
                           autocomplete="off"
                           <?= $key === 'wordpress_api_url' ? 'required' : '' ?>>
                </label>
                <?php endforeach; ?>

                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">
                        Save settings
                    </button>
                    <?php if ($wpConfigured): ?>
                    <button type="submit" formaction="/admin/wordpress-settings/test" formmethod="post"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Test connection
                    </button>
                    <?php endif; ?>
                    <a href="/admin/wordpress-doctors" class="rounded-lg px-4 py-2 text-sm text-slate-500 hover:text-slate-700">
                        Doctor blog access →
                    </a>
                </div>
            </form>
        </section>

    </main>
</body>
</html>
