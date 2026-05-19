<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class PatientPhotoService
{
    /** @return list<array<string, mixed>> */
    public static function forPatient(int $clinicId, int $patientId): array
    {
        return QueryBuilder::table('patient_photos')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->orderBy('uploaded_at', 'DESC')
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public static function forVisit(int $clinicId, int $visitId): array
    {
        return QueryBuilder::table('patient_photos')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->orderBy('type', 'ASC')
            ->get();
    }

    public static function upload(int $clinicId, int $patientId, ?int $visitId, array $file, string $type, ?string $label = null, bool $isPublic = false): int
    {
        $path = self::storeFile($clinicId, $patientId, $file);
        if ($path === null) {
            throw new \RuntimeException('Invalid image upload');
        }

        $id = QueryBuilder::table('patient_photos')->insert([
            'clinic_id' => $clinicId,
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'type' => in_array($type, ['before', 'after', 'progress'], true) ? $type : 'progress',
            'photo_path' => $path,
            'condition_label' => $label,
            'is_public' => $isPublic ? 1 : 0,
        ]);

        if ($isPublic) {
            self::firePublicWebhook($clinicId, $id);
        }

        return $id;
    }

    public static function storeFile(int $clinicId, int $patientId, array $file): ?string
    {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $ext = \App\Support\UploadValidator::validate($file, $allowed, 5 * 1024 * 1024);
        if ($ext === null) {
            return null;
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/photos/' . $clinicId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = \App\Support\UploadValidator::uuidFilename($ext);
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return null;
        }

        return '/uploads/photos/' . $clinicId . '/' . $name;
    }

    private static function firePublicWebhook(int $clinicId, int $photoId): void
    {
        $url = $_ENV['PHOTO_PUBLIC_WEBHOOK_URL'] ?? '';
        if ($url === '') {
            $dir = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($dir . '/photo_webhook.log', date('c') . " clinic={$clinicId} photo={$photoId} is_public=1\n", FILE_APPEND);

            return;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['clinic_id' => $clinicId, 'photo_id' => $photoId, 'event' => 'photo.published']),
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
