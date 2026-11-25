<?php

declare(strict_types=1);

namespace App\Services\OccHealth;

use App\Core\Application;
use App\Http\Response;
use App\Services\Corporate\CorpMailer;
use App\Services\SlotService;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Fitness certificate PDF (design §4, C11). Only the printed fields: who,
 * which checks (names), outcome, work restrictions, validity, doctor +
 * registration, clinic; plus a QR to the public verify page and the SHA-256.
 * No diagnosis, results or notes. Files live outside the web root in
 * repo-root storage/corporate_certs/{company_id}/ and are streamed only after
 * an access check.
 */
final class CertificatePdfService
{
    public static function root(): string
    {
        return dirname(Application::basePath()) . '/storage/corporate_certs';
    }

    public static function verifyUrl(string $token): string
    {
        return CorpMailer::appUrl() . '/verify/' . $token;
    }

    /** @param array<string, mixed> $c certificate row @return string|null relative path */
    public static function generate(array $c): ?string
    {
        if (!class_exists(Mpdf::class)) {
            error_log('[CertificatePdf] mPDF missing');

            return null;
        }
        $companyId = (int) $c['company_id'];
        $dir = self::root() . '/' . $companyId;
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            error_log('[CertificatePdf] cannot create ' . $dir);

            return null;
        }
        if (!is_file(self::root() . '/.htaccess')) {
            @file_put_contents(self::root() . '/.htaccess', "Require all denied\n");
        }
        $uid = function_exists('posix_getuid') ? posix_getuid() : getmyuid();
        $tmpDir = sys_get_temp_dir() . '/mpdf-' . $uid;
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            $tmpDir = sys_get_temp_dir();
        }
        $url = self::verifyUrl((string) $c['verify_token']);
        $qrFile = self::qrSvg($url, $tmpDir);
        $name = 'cert-' . (int) $c['id'] . '-' . bin2hex(random_bytes(8)) . '.pdf';
        try {
            $mpdf = new Mpdf(['format' => 'A4', 'tempDir' => $tmpDir, 'margin_top' => 14, 'margin_bottom' => 14, 'margin_left' => 16, 'margin_right' => 16]);
            $mpdf->SetTitle('Fitness certificate ' . $c['cert_no']);
            $mpdf->WriteHTML(self::html($c, $url, $qrFile));
            $mpdf->Output($dir . '/' . $name, Destination::FILE);
        } catch (\Throwable $e) {
            error_log('[CertificatePdf] ' . $e->getMessage());

            return null;
        } finally {
            if ($qrFile !== null) {
                @unlink($qrFile);
            }
        }

        return $companyId . '/' . $name;
    }

    /** Stream inline. Caller must have checked access. */
    public static function response(array $c): Response
    {
        $root = realpath(self::root());
        $abs = $root !== false && !empty($c['pdf_path']) ? realpath($root . '/' . $c['pdf_path']) : false;
        if ($abs === false || !str_starts_with($abs, $root . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return Response::html('Certificate file not found', 404);
        }

        return new Response((string) file_get_contents($abs), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^\w.\-]/', '_', (string) $c['cert_no']) . '.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // ------------------------------------------------------------------

    /** @param array<string, mixed> $c */
    private static function html(array $c, string $url, ?string $qrFile): string
    {
        $h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $d = static fn (?string $ymd): string => $ymd ? date('d M Y', strtotime($ymd)) : '';
        $snap = json_decode((string) $c['checks_json'], true) ?: [];
        $issuer = $snap['issuer'] ?? [];
        $person = $snap['person'] ?? [];
        $outcome = OccExamService::OUTCOMES[$c['outcome']] ?? $c['outcome'];
        $colour = match ($c['outcome']) { 'fit' => '#047857', 'fit_restricted' => '#b45309', default => '#b91c1c' };
        $restr = array_map(static fn ($k) => OccExamService::RESTRICTIONS[$k] ?? $k, json_decode((string) ($c['restrictions_json'] ?? '[]'), true) ?: []);
        if (!empty($c['restrictions_note'])) {
            $restr[] = (string) $c['restrictions_note'];
        }
        $tz = SlotService::clinicTimezone((int) $c['clinic_id']);
        $signed = (new \DateTimeImmutable((string) $c['signed_at'], new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($tz))->format('d M Y, h:i A');
        $validity = match (true) {
            !empty($c['valid_to']) => 'Valid from ' . $d((string) $c['valid_from']) . ' to <b>' . $d((string) $c['valid_to']) . '</b>',
            !empty($c['review_by']) => 'To be reviewed by <b>' . $d((string) $c['review_by']) . '</b>',
            default => '—',
        };
        $row = static fn (string $k, string $v): string => '<tr><td style="width:34%;padding:6px 8px;color:#475569;border-bottom:1px solid #e2e8f0;">' . $k . '</td><td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">' . $v . '</td></tr>';

        return '<div style="font-family:sans-serif;font-size:10.5pt;color:#0f172a;">'
            . '<table width="100%"><tr><td><div style="font-size:15pt;font-weight:bold;">' . $h($issuer['clinic'] ?? '') . '</div>'
            . '<div style="color:#475569;font-size:9pt;">' . $h($issuer['clinic_address'] ?? '') . '</div></td>'
            . '<td style="text-align:right;color:#475569;font-size:9pt;">Certificate no.<br><b style="font-size:11pt;color:#0f172a;">' . $h($c['cert_no']) . '</b></td></tr></table>'
            . '<div style="margin:14px 0 4px;font-size:17pt;font-weight:bold;text-align:center;letter-spacing:.5px;">Medical Fitness Certificate</div>'
            . '<div style="text-align:center;color:#475569;font-size:9.5pt;margin-bottom:14px;">Occupational health examination</div>'
            . '<table width="100%" style="border-collapse:collapse;">'
            . $row('Name', '<b>' . $h($person['name'] ?? '') . '</b>')
            . $row('Employee no.', $h($person['employee_no'] ?? ''))
            . $row('Employer', $h($snap['company'] ?? ''))
            . $row('Date of examination', $h($d((string) $c['exam_date'])))
            . $row('Checks done', $h(implode(', ', $snap['checks'] ?? [])))
            . '</table>'
            . '<div style="margin:16px 0;padding:12px 14px;border:2px solid ' . $colour . ';border-radius:6px;">'
            . '<div style="color:#475569;font-size:9pt;">Fitness for work</div><div style="font-size:16pt;font-weight:bold;color:' . $colour . ';">' . $h($outcome) . '</div>'
            . ($restr !== [] ? '<div style="margin-top:6px;"><b>Restrictions:</b><ul style="margin:4px 0 0 0;">' . implode('', array_map(static fn ($r) => '<li>' . $h($r) . '</li>', $restr)) . '</ul></div>' : '')
            . '<div style="margin-top:6px;">' . $validity . '</div></div>'
            . '<table width="100%"><tr><td style="vertical-align:bottom;">'
            . '<div style="font-weight:bold;">' . $h($issuer['doctor'] ?? '') . '</div>'
            . '<div style="color:#475569;font-size:9pt;">' . $h($issuer['qualification'] ?? '') . '</div>'
            . '<div style="font-size:9pt;">Registration: ' . $h($c['doctor_reg'] ?? '') . '</div>'
            . '<div style="color:#475569;font-size:9pt;margin-top:4px;">Electronically signed on ' . $h($signed) . ' via eClinicPro</div></td>'
            . '<td style="width:34mm;text-align:right;vertical-align:bottom;">' . ($qrFile !== null ? '<img src="' . $h($qrFile) . '" style="width:30mm;height:30mm;">' : '') . '</td></tr></table>'
            . '<div style="margin-top:14px;padding-top:8px;border-top:1px solid #e2e8f0;color:#64748b;font-size:8pt;">'
            . 'Check this certificate at ' . $h($url) . ' or scan the QR code.<br>'
            . 'Fingerprint (SHA-256): ' . $h($c['sha256']) . '<br>'
            . 'This certificate states fitness for work only. It contains no diagnosis or medical results, which stay confidential between the employee and the doctor.'
            . '</div></div>';
    }

    /** QR as an SVG temp file (no GD needed). Null if the library is missing: the URL is printed anyway. */
    private static function qrSvg(string $url, string $tmpDir): ?string
    {
        if (!class_exists(\Endroid\QrCode\QrCode::class) || !class_exists(\Endroid\QrCode\Writer\SvgWriter::class)) {
            return null;
        }
        try {
            $svg = (new \Endroid\QrCode\Writer\SvgWriter())->write(new \Endroid\QrCode\QrCode($url))->getString();
            $file = $tmpDir . '/qr-' . bin2hex(random_bytes(8)) . '.svg';

            return file_put_contents($file, $svg) !== false ? $file : null;
        } catch (\Throwable $e) {
            error_log('[CertificatePdf] QR: ' . $e->getMessage());

            return null;
        }
    }
}
