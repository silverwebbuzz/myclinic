<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Misc — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-4xl p-6 space-y-6">
    <div>
        <h1 class="text-xl font-semibold">Misc</h1>
        <p class="text-sm text-slate-500">System-level misc controls.</p>
    </div>

    <?php if (!empty($message)): ?>
    <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
        Settings saved.
    </div>
    <?php endif; ?>

    <section class="rounded-xl border bg-white p-6">
        <h2 class="text-base font-semibold text-slate-900">For Patient</h2>
        <p class="mt-1 text-sm text-slate-500"></p>

        <form method="post" action="/admin/misc" class="mt-5 space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrf) ?>">
            <div class="grid items-start gap-4 sm:grid-cols-[1fr_auto]">
                <div>
                    <label for="patient_daily_booking_limit" class="text-sm font-medium text-slate-700">
                        How many appointments a patient can book in one day?
                    </label>
                    <span class="mt-1 block text-xs text-slate-500">
                        Set <strong>0</strong> for unlimited. If set to a number (e.g. 2), each patient can book only that many appointments per day.
                    </span>
                </div>
                <div class="justify-self-end">
                    <input id="patient_daily_booking_limit" type="number" min="0" name="patient_daily_booking_limit"
                           value="<?= htmlspecialchars((string) ($patientDailyBookingLimit ?? 0)) ?>"
                           class="w-24 rounded border px-3 py-2 text-base">
                </div>
            </div>
            <div>
                <button type="submit" class="rounded bg-slate-800 px-4 py-2 my-5 text-sm font-semibold text-white hover:bg-slate-900">
                    Save Misc settings
                </button>
            </div>
        </form>
    </section>
</main>
</body>
</html>
