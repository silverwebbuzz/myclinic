<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin — ManageClinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-900 text-slate-100">
    <form method="post" action="/admin/login" class="w-full max-w-sm rounded-xl bg-slate-800 p-8 shadow-xl">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <h1 class="text-xl font-semibold">Platform admin</h1>
        <p class="mt-1 text-sm text-slate-400">admin.manageclinic.com</p>
        <?php if (!empty($error)): ?>
        <p class="mt-4 rounded-lg bg-red-900/50 px-3 py-2 text-sm text-red-200"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <label class="mt-6 block text-sm">
            <span class="text-slate-400">Email</span>
            <input name="email" type="email" required class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2">
        </label>
        <label class="mt-4 block text-sm">
            <span class="text-slate-400">Password</span>
            <input name="password" type="password" required class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2">
        </label>
        <button type="submit" class="mt-6 w-full rounded-lg bg-emerald-600 py-2 font-medium hover:bg-emerald-500">Sign in</button>
    </form>
</body>
</html>
