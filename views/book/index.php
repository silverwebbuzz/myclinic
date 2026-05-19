<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book — <?= htmlspecialchars($clinic['name'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 p-6">
    <div class="mx-auto max-w-lg">
        <h1 class="text-xl font-semibold"><?= htmlspecialchars($clinic['name'] ?? '') ?></h1>
        <p class="text-sm text-slate-500 mt-1">Book an appointment online</p>
        <?php if (!empty($booked)): ?>
        <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Appointment booked successfully.</p>
        <?php else: ?>
        <form method="post" action="/book/<?= htmlspecialchars($slug) ?>" class="mt-6 space-y-4 rounded-xl border bg-white p-6 text-sm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label class="block">Your name<input name="name" required class="mt-1 w-full rounded-lg border px-3 py-2"></label>
            <label class="block">Phone<input name="phone" required class="mt-1 w-full rounded-lg border px-3 py-2"></label>
            <label class="block">Doctor
                <select name="doctor_id" class="mt-1 w-full rounded-lg border px-3 py-2">
                    <?php foreach ($doctors as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $doctorId===(int)$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block">Date<input name="date" type="date" value="<?= htmlspecialchars($date) ?>" min="<?= date('Y-m-d') ?>" class="mt-1 w-full rounded-lg border px-3 py-2" id="book-date"></label>
            <label class="block">Time slot
                <select name="scheduled_at" required class="mt-1 w-full rounded-lg border px-3 py-2">
                    <?php foreach ($slots as $slot): ?>
                    <?php if (!empty($slot['available'])): ?>
                    <option value="<?= htmlspecialchars($slot['datetime']) ?>"><?= htmlspecialchars($slot['time']) ?></option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2 text-white font-medium">Confirm booking</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
