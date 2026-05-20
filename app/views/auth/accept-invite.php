<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept invitation — ManageClinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md rounded-xl border bg-white p-8 shadow-sm">
        <h1 class="text-xl font-semibold">Join <?= htmlspecialchars($invite['clinic']['name'] ?? 'clinic') ?></h1>
        <p class="mt-1 text-sm text-slate-500">Hi <?= htmlspecialchars($invite['name']) ?>, set a password for <?= htmlspecialchars($invite['email']) ?>.</p>
        <p class="mt-1 text-xs text-slate-400">Role: <?= htmlspecialchars($invite['role']) ?></p>

        <?php if (!empty($error)): ?>
        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post" action="/accept-invite/<?= htmlspecialchars($token) ?>" class="mt-6 space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label class="block text-sm">
                Password
                <input type="password" name="password" required minlength="8" class="mt-1 w-full rounded-lg border px-3 py-2">
            </label>
            <label class="block text-sm">
                Confirm password
                <input type="password" name="password_confirmation" required class="mt-1 w-full rounded-lg border px-3 py-2">
            </label>
            <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2 text-sm font-medium text-white">Create account</button>
        </form>
    </div>
</body>
</html>
