<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Application;
use App\Core\Database;
use App\Http\Response;
use App\Support\Locale\LocalDate;

/**
 * Company registration documents (trade licence etc.), uploaded by the company
 * and reviewed by eClinicPro before approval. Files live OUTSIDE the web root
 * in repo-root storage/corporate_docs/{company_id}/ and are only ever streamed
 * through a controller that has checked access.
 */
final class CompanyDocumentService
{
    public const TYPES = [
        'trade_licence' => 'Trade / business licence',
        'registration_cert' => 'Certificate of registration / incorporation',
        'tax_cert' => 'Tax registration certificate',
        'other' => 'Other supporting document',
    ];
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const MAX_PER_COMPANY = 30;
    private const EXT = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    private const MIME_TO_EXT = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function root(): string
    {
        return dirname(Application::basePath()) . '/storage/corporate_docs';
    }

    /** @return list<array<string, mixed>> newest first */
    public static function list(int $companyId): array
    {
        $st = Database::connection()->prepare('SELECT * FROM corp_company_documents WHERE company_id = :cid ORDER BY id DESC');
        $st->execute(['cid' => $companyId]);

        return $st->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $companyId, int $docId): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM corp_company_documents WHERE id = :id AND company_id = :cid LIMIT 1');
        $st->execute(['id' => $docId, 'cid' => $companyId]);

        return $st->fetch() ?: null;
    }

    /**
     * @param array<string, mixed> $file one $_FILES entry
     * @return array{ok: bool, error?: string}
     */
    public static function store(int $companyId, int $userId, string $type, array $file): array
    {
        if (!array_key_exists($type, self::TYPES)) {
            return ['ok' => false, 'error' => 'Choose what kind of document this is.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Please choose a file to upload.'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'File too large (max 10 MB).'];
        }
        if (count(self::list($companyId)) >= self::MAX_PER_COMPANY) {
            return ['ok' => false, 'error' => 'Too many documents. Delete old ones first.'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, (string) $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $ext = self::MIME_TO_EXT[$mime] ?? null;
        if ($ext === null) {
            return ['ok' => false, 'error' => 'Allowed formats: PDF, JPG, PNG, WEBP.'];
        }

        $dir = self::root() . '/' . $companyId;
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not save the file. Please try again.'];
        }
        // Defence in depth: storage/ is outside the web root, but keep a deny file anyway.
        $guard = self::root() . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\n");
        }
        $name = $type . '-' . bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        }

        Database::connection()->prepare(
            'INSERT INTO corp_company_documents (company_id, doc_type, file_path, original_name, mime_type, size_bytes, status, uploaded_by, created_at)
             VALUES (:cid, :type, :path, :orig, :mime, :size, \'pending\', :by, :at)'
        )->execute([
            'cid' => $companyId,
            'type' => $type,
            'path' => $companyId . '/' . $name,
            'orig' => mb_substr((string) ($file['name'] ?? $name), 0, 190),
            'mime' => $mime,
            'size' => (int) ($file['size'] ?? 0),
            'by' => $userId,
            'at' => LocalDate::nowUtc(),
        ]);
        $docId = (int) Database::connection()->lastInsertId();
        CorpAudit::log($companyId, 'corp_user', $userId, 'document_uploaded', 'corp_document', $docId, ['type' => self::TYPES[$type]]);

        return ['ok' => true];
    }

    /** Companies may delete a document until eClinicPro has approved it. */
    public static function delete(int $companyId, int $userId, int $docId): array
    {
        $doc = self::find($companyId, $docId);
        if ($doc === null) {
            return ['ok' => false, 'error' => 'Document not found.'];
        }
        if ($doc['status'] === 'approved') {
            return ['ok' => false, 'error' => 'Approved documents can\'t be deleted. Contact eClinicPro support if it needs replacing.'];
        }
        Database::connection()->prepare('DELETE FROM corp_company_documents WHERE id = :id AND company_id = :cid')
            ->execute(['id' => $docId, 'cid' => $companyId]);
        $abs = self::absolutePath($doc);
        if ($abs !== null) {
            @unlink($abs);
        }
        CorpAudit::log($companyId, 'corp_user', $userId, 'document_deleted', 'corp_document', $docId, ['type' => self::TYPES[$doc['doc_type']] ?? $doc['doc_type']]);

        return ['ok' => true];
    }

    /** eClinicPro review (super admin). */
    public static function review(int $companyId, int $docId, int $adminId, bool $approve, string $note): array
    {
        $note = mb_substr(trim($note), 0, 255);
        if (!$approve && $note === '') {
            return ['ok' => false, 'error' => 'Say why the document was rejected; the company sees this note.'];
        }
        $st = Database::connection()->prepare(
            'UPDATE corp_company_documents SET status = :s, review_note = :n, reviewed_by = :by, reviewed_at = :at WHERE id = :id AND company_id = :cid'
        );
        $st->execute([
            's' => $approve ? 'approved' : 'rejected',
            'n' => $note !== '' ? $note : null,
            'by' => $adminId,
            'at' => LocalDate::nowUtc(),
            'id' => $docId,
            'cid' => $companyId,
        ]);
        if ($st->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'Document not found.'];
        }
        CorpAudit::log($companyId, 'platform_admin', $adminId, $approve ? 'document_approved' : 'document_rejected', 'corp_document', $docId, $note !== '' ? ['note' => $note] : null);

        return ['ok' => true];
    }

    /** Stream inline. Caller must already have authorised access to this company. */
    public static function response(array $doc): Response
    {
        $abs = self::absolutePath($doc);
        if ($abs === null) {
            return Response::html('File not found', 404);
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

        return new Response((string) file_get_contents($abs), 200, [
            'Content-Type' => self::EXT[$ext] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^\w.\-]/', '_', (string) ($doc['original_name'] ?? basename($abs))) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private static function absolutePath(array $doc): ?string
    {
        $root = realpath(self::root());
        $abs = $root !== false ? realpath($root . '/' . (string) $doc['file_path']) : false;

        return ($abs !== false && str_starts_with($abs, $root . DIRECTORY_SEPARATOR) && is_file($abs)) ? $abs : null;
    }
}
