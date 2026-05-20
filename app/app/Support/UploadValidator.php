<?php

declare(strict_types=1);

namespace App\Support;

final class UploadValidator
{
    /** @param array<string, string> $allowedMimeToExt */
    public static function validate(array $file, array $allowedMimeToExt, int $maxBytes): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        if (($file['size'] ?? 0) > $maxBytes) {
            return null;
        }

        if (!is_uploaded_file($file['tmp_name'] ?? '') && !is_file($file['tmp_name'] ?? '')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset($allowedMimeToExt[$mime])) {
            return null;
        }

        return $allowedMimeToExt[$mime];
    }

    public static function uuidFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . strtolower($extension);
    }
}
