<?php
/** Admin — doctors with WordPress blog access. */
$statusLabels = [
    'wordpress_access_granted' => 'WordPress access granted successfully.',
    'wordpress_access_linked' => 'Linked to existing WordPress author account.',
    'wordpress_access_revoked' => 'WordPress access removed. The WordPress author account was deleted.',
    'wordpress_access_revoked_unlinked' => 'WordPress access removed in eClinicPro (WordPress account could not be deleted — check API permissions).',
    'wordpress_sync_revoked' => 'Some links were auto-updated because the WordPress author no longer exists.',
    'wordpress_connection_ok' => 'WordPress API connection is working.',
];
$msg = isset($message) ? ($statusLabels[$message] ?? str_replace('_', ' ', (string) $message)) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WordPress Doctors · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-7xl px-6 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Doctor blog access</h1>
                <p class="text-sm text-slate-500 mt-1">
                    Grant WordPress author accounts to doctors. Posts appear on their public eClinicPro profile and in the doctor panel Blogs module.
                    <strong><?= (int) $total ?></strong> doctors.
                </p>
            </div>
            <?php if ($wpConfigured): ?>
            <div class="flex gap-2">
                <a href="/admin/wordpress-settings" class="rounded border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    WordPress settings
                </a>
                <form method="post" action="/admin/wordpress-settings/test">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-900">
                        Test connection
                    </button>
                </form>
            </div>
            <?php else: ?>
            <a href="/admin/wordpress-settings" class="rounded bg-violet-600 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-700">
                Configure WordPress
            </a>
            <?php endif; ?>
        </div>

        <?php if (!$wpConfigured): ?>
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            WordPress API is not configured.
            <a href="/admin/wordpress-settings" class="font-semibold text-amber-950 underline">Open WordPress settings</a> to add your API credentials.
        </div>
        <?php endif; ?>

        <?php if ($msg): ?>
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= htmlspecialchars((string) $error) ?></div>
        <?php endif; ?>

        <form method="get" action="/admin/wordpress-doctors" class="mt-4 flex gap-2">
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search doctor, email, clinic…"
                   class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Search</button>
        </form>

        <div class="mt-4 overflow-hidden rounded-xl border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Doctor</th>
                        <th class="px-4 py-3">Clinic</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">WordPress</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($doctors)): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No doctors found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($doctors as $doc): ?>
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900"><?= htmlspecialchars((string) $doc['name']) ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars((string) $doc['email']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars((string) $doc['clinic_name']) ?></td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                                <?= !empty($doc['is_owner']) ? 'Owner' : htmlspecialchars((string) $doc['role']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <?php if (!empty($doc['wp_user_id'])): ?>
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                Linked · <?= htmlspecialchars((string) $doc['wp_username']) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-xs text-slate-400">Not linked</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <?php if (empty($doc['wp_user_id']) && $wpConfigured): ?>
                            <form method="post" action="/admin/wordpress-doctors/grant" class="inline"
                                  onsubmit="return confirm('Create a WordPress author account for <?= htmlspecialchars(addslashes((string) $doc['name'])) ?>?');">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $doc['id'] ?>">
                                <button type="submit" class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700">
                                    WordPress access
                                </button>
                            </form>
                            <?php elseif (!empty($doc['wp_user_id'])): ?>
                            <form method="post" action="/admin/wordpress-doctors/revoke" class="inline"
                                  onsubmit="return confirm('Remove WordPress blog access for <?= htmlspecialchars(addslashes((string) $doc['name'])) ?>?\n\nThis will unlink them in eClinicPro and delete their WordPress author account.');">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $doc['id'] ?>">
                                <input type="hidden" name="delete_wp_user" value="1">
                                <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                    Remove access
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="text-xs text-slate-300">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (($pages ?? 1) > 1): ?>
        <div class="mt-4 flex justify-center gap-2 text-sm">
            <?php for ($i = 1; $i <= (int) $pages; $i++): ?>
            <a href="/admin/wordpress-doctors?page=<?= $i ?><?= $search ? '&q=' . rawurlencode($search) : '' ?>"
               class="rounded px-3 py-1 <?= $i === (int) $page ? 'bg-slate-800 text-white' : 'bg-white border text-slate-600 hover:bg-slate-50' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
