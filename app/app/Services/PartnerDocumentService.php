<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

/**
 * Partner KYC document uploads. Files land under public/uploads/partner-docs/
 * with randomised names so the path isn't guessable. Admin verifies/rejects.
 */
final class PartnerDocumentService
{
    private const ALLOWED_TYPES = ['id_proof', 'pan', 'bank_proof', 'agreement', 'other'];
    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    /**
     * Store one uploaded file (from $_FILES entry) for a partner.
     *
     * @param array{name?: string, tmp_name?: string, size?: int, error?: int} $file
     *
     * @return array{ok: bool, error?: string}
     */
    public static function store(int $partnerId, string $docType, array $file): array
    {
        if (!in_array($docType, self::ALLOWED_TYPES, true)) {
            return ['ok' => false, 'error' => 'Invalid document type.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'No file uploaded.'];
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'File too large (max 5 MB).'];
        }

        $original = (string) ($file['name'] ?? 'document');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return ['ok' => false, 'error' => 'Allowed formats: PDF, JPG, PNG, WEBP.'];
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/partner-docs/' . $partnerId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not create upload directory.'];
        }

        $filename = $docType . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $abs = $dir . '/' . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $abs)) {
            return ['ok' => false, 'error' => 'Upload failed.'];
        }

        $rel = '/uploads/partner-docs/' . $partnerId . '/' . $filename;

        QueryBuilder::table('partner_documents')->insert([
            'partner_id' => $partnerId,
            'doc_type' => $docType,
            'file_path' => $rel,
            'original_name' => substr($original, 0, 255),
            'status' => 'pending',
        ]);

        return ['ok' => true];
    }

    /** @return list<array<string, mixed>> */
    public static function forPartner(int $partnerId): array
    {
        return QueryBuilder::table('partner_documents')
            ->where('partner_id', '=', $partnerId)
            ->orderBy('uploaded_at', 'DESC')
            ->get();
    }

    public static function review(int $docId, string $status, int $adminId): bool
    {
        if (!in_array($status, ['verified', 'rejected', 'pending'], true)) {
            return false;
        }

        QueryBuilder::table('partner_documents')->where('id', '=', $docId)->update([
            'status' => $status,
            'reviewed_by' => $adminId,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }
}
