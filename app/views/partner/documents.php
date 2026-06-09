<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KYC Documents — eClinicPro Partners</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-3xl p-6">
    <?php if (!empty($welcome)): ?>
        <p class="mb-4 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-800">Welcome! Upload your KYC documents below so we can approve your partner account.</p>
    <?php endif; ?>
    <?php if (!empty($message)): ?>
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <h1 class="text-lg font-semibold text-slate-900">KYC documents</h1>
    <p class="mt-1 text-sm text-slate-500">Upload ID proof, PAN and bank proof. PDF/JPG/PNG, max 5 MB each.</p>

    <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5">
        <form method="post" action="/partner/documents/upload" enctype="multipart/form-data" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <div class="flex-1">
                <label class="block text-xs text-slate-500">Document type</label>
                <select name="doc_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="id_proof">ID proof (Aadhaar/Passport)</option>
                    <option value="pan">PAN card</option>
                    <option value="bank_proof">Bank proof / cancelled cheque</option>
                    <option value="agreement">Signed agreement</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-xs text-slate-500">File</label>
                <input name="document" type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
            </div>
            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Upload</button>
        </form>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Uploaded documents</h2>
        <?php if (empty($documents)): ?>
            <p class="mt-2 text-sm text-slate-400">No documents uploaded yet.</p>
        <?php else: ?>
        <div class="mt-3 divide-y divide-slate-100 text-sm">
            <?php foreach ($documents as $d): ?>
            <div class="flex items-center justify-between py-2">
                <div>
                    <div class="font-medium capitalize text-slate-800"><?= htmlspecialchars(str_replace('_', ' ', (string) $d['doc_type'])) ?></div>
                    <div class="text-xs text-slate-400"><?= htmlspecialchars((string) ($d['original_name'] ?? '')) ?> · <?= htmlspecialchars(substr((string) $d['uploaded_at'], 0, 10)) ?></div>
                </div>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase <?= $d['status'] === 'verified' ? 'bg-emerald-100 text-emerald-700' : ($d['status'] === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>">
                    <?= htmlspecialchars($d['status']) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
