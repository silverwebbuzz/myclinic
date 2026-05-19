<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class QrCardService
{
    /** @param array<string, mixed> $patient @param array<string, mixed> $clinic */
    public static function generateForPatient(array $patient, array $clinic): ?string
    {
        $clinicId = (int) $clinic['id'];
        $patientId = (int) $patient['id'];
        $token = (string) $patient['qr_token'];
        $base = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
        $qrUrl = $base . '/qr/' . $token;

        $dir = dirname(__DIR__, 2) . '/public/uploads/qr/' . $clinicId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pngPath = $dir . '/' . $patientId . '.png';
        self::writeQrPng($qrUrl, $pngPath);

        $pdfRel = '/uploads/qr/' . $clinicId . '/' . $patientId . '.pdf';
        $pdfPath = dirname(__DIR__, 2) . '/public' . $pdfRel;
        self::writeA6Card($pdfPath, $pngPath, $patient, $clinic);

        QueryBuilder::table('patients')
            ->where('id', '=', $patientId)
            ->update(['qr_card_path' => $pdfRel]);

        return $pdfRel;
    }

    public static function writeQrPng(string $content, string $path): void
    {
        if (!class_exists(Builder::class)) {
            return;
        }

        Builder::create()
            ->writer(new PngWriter())
            ->data($content)
            ->size(200)
            ->margin(10)
            ->build()
            ->saveToFile($path);
    }

    /** @param array<string, mixed> $patient @param array<string, mixed> $clinic */
    private static function writeA6Card(string $pdfPath, string $pngPath, array $patient, array $clinic): void
    {
        if (!class_exists(Mpdf::class)) {
            return;
        }

        $mpdf = new Mpdf([
            'format' => 'A6',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 8,
            'margin_bottom' => 8,
        ]);

        $name = htmlspecialchars((string) ($patient['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $uhid = htmlspecialchars((string) ($patient['uhid'] ?? ''), ENT_QUOTES, 'UTF-8');
        $clinicName = htmlspecialchars((string) ($clinic['name'] ?? 'Clinic'), ENT_QUOTES, 'UTF-8');
        $img = is_file($pngPath)
            ? '<img src="file://' . str_replace(' ', '%20', $pngPath) . '" width="120" />'
            : '';

        $mpdf->WriteHTML(
            '<div style="text-align:center;font-family:sans-serif;">'
            . '<h2 style="margin:0;font-size:14pt;">' . $clinicName . '</h2>'
            . '<p style="font-size:11pt;margin:8px 0;">' . $name . '</p>'
            . '<p style="font-size:10pt;color:#555;">UHID: ' . $uhid . '</p>'
            . '<p style="margin-top:12px;">' . $img . '</p></div>',
        );
        $mpdf->Output($pdfPath, Destination::FILE);
    }
}
