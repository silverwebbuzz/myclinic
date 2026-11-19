<?php

declare(strict_types=1);

namespace App\Services\OccHealth;

use App\Core\Application;
use App\Core\Database;
use App\Http\Response;
use App\Services\Corporate\CorpAudit;
use App\Services\Corporate\CorpMailer;
use App\Support\Locale\LocalDate;

/**
 * Clinic side of Workforce Health (design §4, C1/C2/C10): the clinic owner
 * accepts or declines companies that want the clinic in their network, and
 * records each doctor's registration / licence for eClinicPro to verify
 * (needed before a doctor can sign fitness certificates).
 *
 * Lives outside Services/Corporate on purpose: clinic code may touch clinical
 * tables, company code may not (C14).
 */
final class ClinicCorporateService
{
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const MIME_TO_EXT = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const EXT_MIME = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    public static function enabled(int $clinicId): bool
    {
        $st = Database::connection()->prepare('SELECT corporate_enabled FROM tenants WHERE id = :id');
        $st->execute(['id' => $clinicId]);

        return (int) $st->fetchColumn() === 1;
    }

    /** @return list<array<string, mixed>> companies linked to / asking this clinic */
    public static function links(int $clinicId): array
    {
        $st = Database::connection()->prepare(
            "SELECT l.id, l.company_id, l.status, l.branch_ids, l.note, l.ended_by, l.ended_reason, l.created_at, l.updated_at,
                    c.legal_name, c.trade_name, c.city, c.country_code, c.contact_name, c.contact_email, c.contact_phone,
                    (SELECT COUNT(*) FROM corp_employments e WHERE e.company_id = l.company_id AND e.status <> 'terminated') AS employees
               FROM corp_provider_links l JOIN corp_companies c ON c.id = l.company_id /* cross-company: clinic sees its own links */
              WHERE l.clinic_id = :clinic AND c.status = 'active'
              ORDER BY FIELD(l.status, 'requested', 'active', 'declined', 'ended'), l.updated_at DESC"
        );
        $st->execute(['clinic' => $clinicId]);

        return $st->fetchAll();
    }

    /** @return array{ok: bool, error?: string} */
    public static function respond(int $clinicId, int $userId, int $linkId, bool $accept): array
    {
        $link = self::link($clinicId, $linkId);
        if ($link === null || $link['status'] !== 'requested') {
            return ['ok' => false, 'error' => 'That request is no longer waiting.'];
        }
        $now = LocalDate::nowUtc();
        Database::connection()->prepare(
            'UPDATE corp_provider_links SET status = :st, responded_by = :by, responded_at = :at, updated_at = :up
              WHERE id = :id AND clinic_id = :clinic AND company_id = :cid AND status = \'requested\''
        )->execute(['st' => $accept ? 'active' : 'declined', 'by' => $userId, 'at' => $now, 'up' => $now, 'id' => $linkId, 'clinic' => $clinicId, 'cid' => (int) $link['company_id']]);
        CorpAudit::log((int) $link['company_id'], 'provider', $userId, $accept ? 'provider_accepted' : 'provider_declined', 'corp_provider_link', $linkId, ['clinic' => $link['clinic_name']]);
        self::notifyCompany($link, $accept
            ? [$link['clinic_name'] . ' accepted your request. You can now add the agreed prices and, from the next update, book employee checks there.']
            : [$link['clinic_name'] . ' declined your request to add them to your clinic network.'],
            $accept ? 'accepted your request' : 'declined your request');

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function end(int $clinicId, int $userId, int $linkId, string $reason): array
    {
        $link = self::link($clinicId, $linkId);
        if ($link === null || $link['status'] !== 'active') {
            return ['ok' => false, 'error' => 'That company is not active with your clinic.'];
        }
        $reason = mb_substr(trim($reason), 0, 500);
        if (mb_strlen($reason) < 5) {
            return ['ok' => false, 'error' => 'Write why you are ending this arrangement (the company will see it).'];
        }
        Database::connection()->prepare(
            "UPDATE corp_provider_links SET status = 'ended', ended_by = 'clinic', ended_reason = :r, updated_at = :up
              WHERE id = :id AND clinic_id = :clinic AND company_id = :cid"
        )->execute(['r' => $reason, 'up' => LocalDate::nowUtc(), 'id' => $linkId, 'clinic' => $clinicId, 'cid' => (int) $link['company_id']]);
        CorpAudit::log((int) $link['company_id'], 'provider', $userId, 'provider_ended_by_clinic', 'corp_provider_link', $linkId, ['clinic' => $link['clinic_name'], 'reason' => $reason]);
        self::notifyCompany($link, [$link['clinic_name'] . ' ended its arrangement with you on eClinicPro.', 'Reason: ' . $reason], 'ended the arrangement');

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Doctor credentials (C10)
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> active doctors (and doctor-owners) with their credential, if any */
    public static function doctors(int $clinicId): array
    {
        $st = Database::connection()->prepare(
            "SELECT u.id AS user_id, u.name, u.role, u.qualification, c.id, c.reg_number, c.authority, c.country_code, c.valid_to,
                    c.document_name, c.status, c.review_note, c.verified_at
               FROM users u LEFT JOIN occ_doctor_credentials c ON c.clinic_id = u.clinic_id AND c.user_id = u.id
              WHERE u.clinic_id = :clinic AND u.is_active = 1 AND u.role IN ('doctor', 'admin')
              ORDER BY u.role = 'doctor' DESC, u.name"
        );
        $st->execute(['clinic' => $clinicId]);

        return $st->fetchAll();
    }

    /**
     * Save a doctor's registration. Any change sends it back for verification.
     *
     * @param array<string, mixed> $post user_id, reg_number, authority, country_code, valid_to
     * @param array<string, mixed>|null $file one $_FILES entry (required the first time)
     * @return array{ok: bool, error?: string}
     */
    public static function saveCredential(int $clinicId, array $post, ?array $file): array
    {
        $doctorId = (int) ($post['user_id'] ?? 0);
        $doctor = array_values(array_filter(self::doctors($clinicId), static fn ($d) => (int) $d['user_id'] === $doctorId))[0] ?? null;
        if ($doctor === null) {
            return ['ok' => false, 'error' => 'Choose a doctor from your team.'];
        }
        $reg = mb_substr(trim((string) ($post['reg_number'] ?? '')), 0, 60);
        $authority = mb_substr(trim((string) ($post['authority'] ?? '')), 0, 150);
        $cc = strtoupper(trim((string) ($post['country_code'] ?? '')));
        $validTo = trim((string) ($post['valid_to'] ?? ''));
        if ($reg === '' || $authority === '') {
            return ['ok' => false, 'error' => 'Enter the registration number and the council / authority that issued it.'];
        }
        if (preg_match('/^[A-Z]{2}$/', $cc) !== 1) {
            return ['ok' => false, 'error' => 'Choose the country of registration.'];
        }
        if ($validTo !== '' && (preg_match('/^\d{4}-\d{2}-\d{2}$/', $validTo) !== 1 || $validTo < date('Y-m-d'))) {
            return ['ok' => false, 'error' => 'The registration has expired (or the date is wrong). A doctor with an expired registration can\'t sign certificates.'];
        }
        $hasFile = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$hasFile && empty($doctor['document_name'])) {
            return ['ok' => false, 'error' => 'Upload a copy of the registration certificate (PDF or photo).'];
        }
        $path = null;
        $docName = null;
        if ($hasFile) {
            $saved = self::storeFile($clinicId, $doctorId, $file);
            if (isset($saved['error'])) {
                return ['ok' => false, 'error' => $saved['error']];
            }
            [$path, $docName] = [$saved['path'], $saved['name']];
        }
        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        if ($doctor['id'] === null) {
            $pdo->prepare(
                "INSERT INTO occ_doctor_credentials (clinic_id, user_id, reg_number, authority, country_code, valid_to, document_path, document_name, status, created_at, updated_at)
                 VALUES (:clinic, :uid, :reg, :auth, :cc, :vt, :path, :name, 'pending', :cr, :up)"
            )->execute(['clinic' => $clinicId, 'uid' => $doctorId, 'reg' => $reg, 'auth' => $authority, 'cc' => $cc, 'vt' => $validTo ?: null,
                'path' => $path, 'name' => $docName, 'cr' => $now, 'up' => $now]);

            return ['ok' => true];
        }
        $old = self::credential($clinicId, (int) $doctor['id']);
        $same = $old !== null && $path === null && $old['reg_number'] === $reg && $old['authority'] === $authority
            && $old['country_code'] === $cc && (string) $old['valid_to'] === $validTo;
        if ($same) {
            return ['ok' => true];
        }
        $pdo->prepare(
            "UPDATE occ_doctor_credentials SET reg_number = :reg, authority = :auth, country_code = :cc, valid_to = :vt,
                    document_path = COALESCE(:path, document_path), document_name = COALESCE(:name, document_name),
                    status = 'pending', review_note = NULL, verified_by = 0, verified_at = NULL, updated_at = :up
              WHERE id = :id AND clinic_id = :clinic"
        )->execute(['reg' => $reg, 'auth' => $authority, 'cc' => $cc, 'vt' => $validTo ?: null, 'path' => $path, 'name' => $docName,
            'up' => $now, 'id' => (int) $doctor['id'], 'clinic' => $clinicId]);
        if ($path !== null && $old !== null) {
            self::deleteFile((string) ($old['document_path'] ?? ''));
        }

        return ['ok' => true];
    }

    /** @return array<string, mixed>|null */
    public static function credential(int $clinicId, int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM occ_doctor_credentials WHERE id = :id AND clinic_id = :clinic LIMIT 1');
        $st->execute(['id' => $id, 'clinic' => $clinicId]);

        return $st->fetch() ?: null;
    }

    /**
     * Can this doctor sign certificates today? (used from 1C-3)
     *
     * @return array{ok: bool, error?: string, credential?: array<string, mixed>}
     */
    public static function canSign(int $clinicId, int $doctorId, string $today): array
    {
        $st = Database::connection()->prepare('SELECT * FROM occ_doctor_credentials WHERE clinic_id = :clinic AND user_id = :uid LIMIT 1');
        $st->execute(['clinic' => $clinicId, 'uid' => $doctorId]);
        $c = $st->fetch();
        if (!$c) {
            return ['ok' => false, 'error' => 'Add your medical registration in Settings › Corporate work first. eClinicPro verifies it once.'];
        }
        if ($c['status'] !== 'verified') {
            return ['ok' => false, 'error' => $c['status'] === 'rejected'
                ? 'Your registration was not accepted: ' . ($c['review_note'] ?: 'please check it') . '. Update it in Settings › Corporate work.'
                : 'Your registration is waiting for eClinicPro to verify it (usually within one working day).'];
        }
        if (!empty($c['valid_to']) && (string) $c['valid_to'] < $today) {
            return ['ok' => false, 'error' => 'Your registration expired on ' . date('d M Y', strtotime((string) $c['valid_to'])) . '. Update it in Settings › Corporate work.'];
        }

        return ['ok' => true, 'credential' => $c];
    }

    public static function fileRoot(): string
    {
        return dirname(Application::basePath()) . '/storage/occ_credentials';
    }

    /** Stream the uploaded document. Caller must have checked access. */
    public static function fileResponse(array $credential): Response
    {
        $root = realpath(self::fileRoot());
        $abs = $root !== false && !empty($credential['document_path']) ? realpath($root . '/' . $credential['document_path']) : false;
        if ($abs === false || !str_starts_with($abs, $root . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return Response::html('File not found', 404);
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

        return new Response((string) file_get_contents($abs), 200, [
            'Content-Type' => self::EXT_MIME[$ext] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^\w.\-]/', '_', (string) ($credential['document_name'] ?: basename($abs))) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed>|null the link, if it belongs to this clinic, with names for messages */
    private static function link(int $clinicId, int $linkId): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT l.*, t.name AS clinic_name, c.legal_name, c.trade_name
               FROM corp_provider_links l JOIN tenants t ON t.id = l.clinic_id JOIN corp_companies c ON c.id = l.company_id
              WHERE l.id = :id AND l.clinic_id = :clinic LIMIT 1 /* cross-company: clinic sees its own links */'
        );
        $st->execute(['id' => $linkId, 'clinic' => $clinicId]);

        return $st->fetch() ?: null;
    }

    /** @param list<string> $paras */
    private static function notifyCompany(array $link, array $paras, string $what): void
    {
        $st = Database::connection()->prepare('SELECT email FROM corp_users WHERE id = :id AND company_id = :cid AND status = \'active\'');
        $st->execute(['id' => (int) $link['requested_by'], 'cid' => (int) $link['company_id']]);
        $email = (string) ($st->fetchColumn() ?: '');
        CorpMailer::send($email, $link['clinic_name'] . ' ' . $what, 'Clinic network update', $paras,
            CorpMailer::appUrl() . '/company/providers/' . (int) $link['id'], 'Open in eClinicPro');
    }

    /** @return array{path?: string, name?: string, error?: string} */
    private static function storeFile(int $clinicId, int $doctorId, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return ['error' => 'The upload failed. Please choose the file again.'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['error' => 'File too large (max 10 MB).'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, (string) $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $ext = self::MIME_TO_EXT[$mime] ?? null;
        if ($ext === null) {
            return ['error' => 'Allowed formats: PDF, JPG, PNG, WEBP.'];
        }
        $dir = self::fileRoot() . '/' . $clinicId;
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            return ['error' => 'Could not save the file. Please try again.'];
        }
        if (!is_file(self::fileRoot() . '/.htaccess')) {
            @file_put_contents(self::fileRoot() . '/.htaccess', "Require all denied\n");
        }
        $name = 'reg-' . $doctorId . '-' . bin2hex(random_bytes(10)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return ['error' => 'Upload failed. Please try again.'];
        }

        return ['path' => $clinicId . '/' . $name, 'name' => mb_substr((string) ($file['name'] ?? $name), 0, 190)];
    }

    private static function deleteFile(string $rel): void
    {
        $root = realpath(self::fileRoot());
        $abs = $root !== false && $rel !== '' ? realpath($root . '/' . $rel) : false;
        if ($abs !== false && str_starts_with($abs, $root . DIRECTORY_SEPARATOR) && is_file($abs)) {
            @unlink($abs);
        }
    }
}
