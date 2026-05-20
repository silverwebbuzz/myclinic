<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\UploadValidator;

final class StorageService
{
    private const IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    private const PATIENT_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private const MAX_LOGO_BYTES = 2 * 1024 * 1024;

    private const MAX_PATIENT_BYTES = 2 * 1024 * 1024;

    public static function storeLogo(int $clinicId, array $file): ?string
    {
        $ext = UploadValidator::validate($file, self::IMAGE_MIMES, self::MAX_LOGO_BYTES);
        if ($ext === null) {
            return null;
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/logos';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = UploadValidator::uuidFilename($ext);
        $path = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return null;
        }

        return '/uploads/logos/' . $filename;
    }

    public static function storePatientPhoto(int $clinicId, int $patientId, array $file): ?string
    {
        $ext = UploadValidator::validate($file, self::PATIENT_MIMES, self::MAX_PATIENT_BYTES);
        if ($ext === null) {
            return null;
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/patients/' . $clinicId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = UploadValidator::uuidFilename($ext);
        $path = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return null;
        }

        return '/uploads/patients/' . $clinicId . '/' . $filename;
    }
}
