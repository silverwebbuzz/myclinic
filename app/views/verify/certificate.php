<?php
/**
 * Public certificate check page.
 *
 * @var array<string,mixed>|null $cert
 * @var string $state valid|expired|revoked|replaced
 * @var bool $intact
 * @var array<string,mixed> $snap
 */
use App\Services\OccHealth\OccExamService;

$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$d = static fn (?string $ymd): string => $ymd ? date('d M Y', strtotime($ymd)) : '';
$banner = [
    'valid' => ['Genuine and valid', 'bg-emerald-50 text-emerald-900 ring-emerald-200', 'This certificate was issued on eClinicPro and has not been withdrawn.'],
    'expired' => ['Genuine but expired', 'bg-amber-50 text-amber-900 ring-amber-200', 'This certificate was issued on eClinicPro, but its validity has ended.'],
    'revoked' => ['Revoked', 'bg-red-50 text-red-900 ring-red-200', 'The issuing doctor withdrew this certificate. Do not rely on it.'],
    'replaced' => ['Replaced', 'bg-red-50 text-red-900 ring-red-200', 'A corrected certificate was issued instead. Ask the person for the newer one.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Certificate check · eClinicPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<main class="mx-auto max-w-xl px-4 py-8">
    <div class="mb-4 text-sm font-semibold text-slate-500">eClinicPro · Certificate check</div>
    <?php if ($cert === null): ?>
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-red-200">
            <h1 class="text-xl font-semibold text-red-800">Certificate not found</h1>
            <p class="mt-2 text-sm text-slate-600">This link doesn't match any certificate issued on eClinicPro. Check that the QR code or link was copied completely. A certificate that can't be found here should not be relied on.</p>
        </div>
    <?php else: [$title, $cls, $text] = $banner[$state]; $person = $snap['person'] ?? []; $issuer = $snap['issuer'] ?? [];
        $restr = array_map(static fn ($k) => OccExamService::RESTRICTIONS[$k] ?? $k, json_decode((string) ($cert['restrictions_json'] ?? '[]'), true) ?: []);
        if (!empty($cert['restrictions_note'])) { $restr[] = (string) $cert['restrictions_note']; } ?>
        <div class="rounded-xl p-5 ring-1 <?= $cls ?>">
            <h1 class="text-xl font-semibold"><?= $h($title) ?></h1>
            <p class="mt-1 text-sm"><?= $h($text) ?></p>
            <?php if (!$intact): ?><p class="mt-2 text-sm font-semibold text-red-800">Warning: the stored record no longer matches its fingerprint. Contact eClinicPro before relying on it.</p><?php endif; ?>
        </div>
        <div class="mt-4 rounded-xl bg-white p-5 shadow-sm">
            <dl class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-x-4 gap-y-2 text-sm">
                <dt class="text-slate-500">Certificate no.</dt><dd class="font-medium"><?= $h($cert['cert_no']) ?></dd>
                <dt class="text-slate-500">Name</dt><dd class="font-medium"><?= $h($person['name'] ?? '') ?></dd>
                <dt class="text-slate-500">Employee no.</dt><dd><?= $h($person['employee_no'] ?? '') ?></dd>
                <dt class="text-slate-500">Employer</dt><dd><?= $h($snap['company'] ?? '') ?></dd>
                <dt class="text-slate-500">Examined</dt><dd><?= $h($d((string) $cert['exam_date'])) ?></dd>
                <?php if ($state !== 'replaced' && $state !== 'revoked'): ?>
                    <dt class="text-slate-500">Fitness for work</dt><dd class="font-semibold"><?= $h(OccExamService::OUTCOMES[$cert['outcome']] ?? $cert['outcome']) ?></dd>
                    <?php if ($restr !== []): ?><dt class="text-slate-500">Restrictions</dt><dd><?= $h(implode('; ', $restr)) ?></dd><?php endif; ?>
                    <?php if (!empty($cert['valid_to'])): ?><dt class="text-slate-500">Valid</dt><dd><?= $h($d((string) $cert['valid_from'])) ?> to <?= $h($d((string) $cert['valid_to'])) ?></dd><?php endif; ?>
                    <?php if (!empty($cert['review_by'])): ?><dt class="text-slate-500">Review by</dt><dd><?= $h($d((string) $cert['review_by'])) ?></dd><?php endif; ?>
                <?php endif; ?>
                <dt class="text-slate-500">Issued by</dt><dd><?= $h($issuer['doctor'] ?? '') ?><br><span class="text-slate-500"><?= $h($cert['doctor_reg'] ?? '') ?></span><br><?= $h($issuer['clinic'] ?? '') ?></dd>
            </dl>
            <p class="mt-4 break-all text-xs text-slate-400">Fingerprint (SHA-256): <?= $h($cert['sha256']) ?></p>
        </div>
        <p class="mt-4 text-xs text-slate-500">This page shows only what is printed on the certificate. Medical details are confidential and never shown here.</p>
    <?php endif; ?>
</main>
</body>
</html>
