<?php
// =====================================================================
// patient_prescriptions.php — data layer for the patient-panel
// E-prescriptions tab.
//
// ONE table (patient_prescriptions) holds BOTH sources so the record always
// stays with the patient:
//   source = 'upload' → the patient added it (photo/PDF of an outside Rx)
//   source = 'clinic' → an eClinicPro doctor shared it during a visit
//
// doctor_name / clinic_name are text snapshots, so a record stays readable
// even if that doctor or clinic later leaves the platform.
//
// AUTHORIZATION RULE (enforced in every mutator): a row may only be
// read/edited/removed when owner_identity_id == the logged-in identity. No IDOR.
//
// Files live under storage/patient_rx/{owner_id}/ (NOT the public webroot) and
// are streamed through api/patient_prescriptions.php?action=file&id=… so every
// download re-checks ownership.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const ECP_RX_TABLE      = 'patient_prescriptions';
const ECP_RX_MAX_BYTES  = 10 * 1024 * 1024; // 10 MB per file
const ECP_RX_MIMES      = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
];

/** Absolute path to a patient's Rx storage folder (created on demand). */
function ecp_rx_dir(int $ownerId): string
{
    $dir = __DIR__ . '/../storage/patient_rx/' . $ownerId;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * List a patient's prescriptions (both sources), newest first.
 *
 * @return list<array<string,mixed>>
 */
function ecp_rx_list(int $ownerId): array
{
    $db = ecp_db();
    if (!$db || $ownerId <= 0) {
        return [];
    }
    $stmt = $db->prepare(
        'SELECT id, family_member_id, label, doctor_name, clinic_name, notes,
                issued_on, file_mime, source, created_at
         FROM ' . ECP_RX_TABLE . '
         WHERE owner_identity_id = :o AND is_active = 1
         ORDER BY COALESCE(issued_on, DATE(created_at)) DESC, id DESC'
    );
    $stmt->execute(['o' => $ownerId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $row['id']               = (int) $row['id'];
        $row['family_member_id'] = $row['family_member_id'] !== null ? (int) $row['family_member_id'] : null;
        $row['has_file']         = !empty($row['file_mime']);
        $row['is_clinic']        = ($row['source'] ?? '') === 'clinic';
    }
    unset($row);

    return $rows;
}

/**
 * Fetch a single row scoped to the owner (for file streaming / delete).
 *
 * @return array<string,mixed>|null
 */
function ecp_rx_find(int $ownerId, int $id): ?array
{
    $db = ecp_db();
    if (!$db || $ownerId <= 0 || $id <= 0) {
        return null;
    }
    $stmt = $db->prepare(
        'SELECT * FROM ' . ECP_RX_TABLE . '
         WHERE id = :id AND owner_identity_id = :o AND is_active = 1 LIMIT 1'
    );
    $stmt->execute(['id' => $id, 'o' => $ownerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Patient self-upload (source = 'upload').
 *
 * @param array<string,mixed>      $data  label, doctor_name, notes, issued_on, family_member_id
 * @param array<string,mixed>|null $file  a single $_FILES entry (optional)
 * @return array{ok:bool,error?:string,id?:int}
 */
function ecp_rx_add_upload(int $ownerId, array $data, ?array $file): array
{
    $db = ecp_db();
    if (!$db || $ownerId <= 0) {
        return ['ok' => false, 'error' => 'server_error'];
    }

    $label = trim((string) ($data['label'] ?? ''));
    if ($label === '') {
        return ['ok' => false, 'error' => 'label_required'];
    }

    // Optional family member must belong to this owner (no IDOR).
    $familyMemberId = null;
    if (!empty($data['family_member_id'])) {
        $fmId = (int) $data['family_member_id'];
        $chk = $db->prepare(
            'SELECT id FROM family_member_identities
             WHERE id = :id AND owner_identity_id = :o AND is_active = 1 LIMIT 1'
        );
        $chk->execute(['id' => $fmId, 'o' => $ownerId]);
        if ($chk->fetchColumn() === false) {
            return ['ok' => false, 'error' => 'member_not_found'];
        }
        $familyMemberId = $fmId;
    }

    // Optional file.
    $storedPath = null;
    $mime = null;
    if ($file && !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
        $saved = ecp_rx_store_file($ownerId, $file);
        if (!$saved['ok']) {
            return $saved;
        }
        $storedPath = $saved['path'];
        $mime = $saved['mime'];
    }

    $issuedOn = ecp_rx_normalize_date((string) ($data['issued_on'] ?? ''));

    $ins = $db->prepare(
        'INSERT INTO ' . ECP_RX_TABLE . '
            (owner_identity_id, family_member_id, label, doctor_name, notes,
             issued_on, file_path, file_mime, source)
         VALUES (:o, :fm, :label, :doc, :notes, :issued, :fp, :mime, "upload")'
    );
    $ins->execute([
        'o'      => $ownerId,
        'fm'     => $familyMemberId,
        'label'  => mb_substr($label, 0, 160),
        'doc'    => ecp_rx_clean((string) ($data['doctor_name'] ?? ''), 160),
        'notes'  => ecp_rx_clean((string) ($data['notes'] ?? ''), 255),
        'issued' => $issuedOn,
        'fp'     => $storedPath,
        'mime'   => $mime,
    ]);

    return ['ok' => true, 'id' => (int) $db->lastInsertId()];
}

/**
 * Soft-delete a patient's prescription and remove its file from disk.
 *
 * @return array{ok:bool,error?:string}
 */
function ecp_rx_remove(int $ownerId, int $id): array
{
    $row = ecp_rx_find($ownerId, $id);
    if ($row === null) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    $db = ecp_db();
    $db->prepare('UPDATE ' . ECP_RX_TABLE . ' SET is_active = 0 WHERE id = :id AND owner_identity_id = :o')
       ->execute(['id' => $id, 'o' => $ownerId]);

    if (!empty($row['file_path'])) {
        $abs = __DIR__ . '/../' . ltrim((string) $row['file_path'], '/');
        if (is_file($abs) && str_contains($abs, '/storage/patient_rx/')) {
            @unlink($abs);
        }
    }
    return ['ok' => true];
}

// ---------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------

/**
 * Validate + store an uploaded file into the owner's Rx folder.
 *
 * @param array<string,mixed> $file  a $_FILES entry
 * @return array{ok:bool,error?:string,max_mb?:int,allowed?:array,path?:string,mime?:string}
 */
function ecp_rx_store_file(int $ownerId, array $file): array
{
    if ((int) ($file['size'] ?? 0) > ECP_RX_MAX_BYTES) {
        return ['ok' => false, 'error' => 'file_too_large', 'max_mb' => (int) (ECP_RX_MAX_BYTES / 1024 / 1024)];
    }
    $mime = mime_content_type($file['tmp_name']) ?: '';
    if (!isset(ECP_RX_MIMES[$mime])) {
        return ['ok' => false, 'error' => 'file_type_not_allowed', 'allowed' => array_keys(ECP_RX_MIMES)];
    }
    $ext  = ECP_RX_MIMES[$mime];
    $dir  = ecp_rx_dir($ownerId);
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'upload_failed'];
    }
    return ['ok' => true, 'path' => 'storage/patient_rx/' . $ownerId . '/' . $name, 'mime' => $mime];
}

/** Trim + length-cap free text, returning NULL for empties. */
function ecp_rx_clean(string $value, int $max): ?string
{
    $value = trim($value);
    return $value === '' ? null : mb_substr($value, 0, $max);
}

/** Accept YYYY-MM-DD only; anything else → NULL. */
function ecp_rx_normalize_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : null;
}
