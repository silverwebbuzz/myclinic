<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The visit's prescription PDF for the patient portal (visits.rx_pdf_path).
 *
 * Written to PRIVATE storage (app/storage/prescriptions/...), never to the
 * public webroot: prescriptions are medical records, and the old
 * /uploads/rx/{clinic}/visit-{id}.pdf files were guessable and served to
 * anyone without login. The returned path is relative to the app root
 * ("storage/..."); PortalController streams it after checking the patient's
 * signed download token.
 */
final class RxPdfService
{
    /**
     * @param array<string, mixed> $visit
     * @param array<string, mixed> $patient
     * @param array<string, mixed> $clinic
     * @param list<array<string, mixed>> $prescriptions
     * @return string path relative to the app root, e.g. storage/prescriptions/12/rx-345-a5.pdf
     */
    public static function generate(array $visit, array $patient, array $clinic, array $prescriptions): string
    {
        $abs = PrescriptionPdfService::generate($visit, $patient, $clinic, $prescriptions, 'A5');
        $root = dirname(__DIR__, 2) . '/';

        return str_starts_with($abs, $root) ? substr($abs, strlen($root)) : $abs;
    }

    /**
     * Absolute file path for a stored rx_pdf_path: new private "storage/..."
     * paths, or legacy "/uploads/..." paths under public/.
     */
    public static function absolutePath(string $stored): string
    {
        return \App\Support\PrivateFile::abs($stored);
    }
}
