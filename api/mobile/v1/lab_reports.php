<?php
// =====================================================================
// api/mobile/v1/lab_reports.php — patient lab-report vault facade.
//
// Thin adapter over partials/patient_lab_reports.php — the SAME data layer
// the website's patient panel uses (api/patient_lab_reports.php). Listing,
// ownership checks, upload validation, storage paths and soft delete all
// stay in ecp_lab_* (the report-vault namespace); this file only adapts
// transport.
//
// SCOPE — PATIENT UPLOADS ONLY. There is no doctor/clinic lab module and no
// partner-lab result feed, so every row here is something the patient added
// themselves (source = 'upload'; the 'clinic' enum value is reserved).
// Thyrocare-booked tests do NOT land here — partner bookings never create
// rows in this vault.
//
// NOTE FOR THE APP (design gap, deliberate): the DB stores a FILE plus a
// label snapshot — there are NO numeric result values, units or reference
// ranges. The design's trend chart (values: [7.2, 6.8, …]) therefore cannot
// be backed yet; render the metadata below plus the file via ?action=file.
//
//   GET  ?action=list                      (Bearer) → { items }
//   GET  ?action=detail&id=123             (Bearer) → { item }
//   GET  ?action=file&id=123               (Bearer) → raw PDF/image bytes
//   POST ?action=add                       (Bearer) multipart: file + fields
//        label* , test_type, lab_name, doctor_name, notes, reported_on,
//        family_member_id
//   POST ?action=remove  { id }            (Bearer) → soft delete
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../partials/patient_lab_reports.php';

$action = $_GET['action'] ?? 'list';

// The file stream sets its own headers and must not emit the JSON envelope.
if ($action === 'file') {
    $me = ecp_m_require_patient();
    ecp_m_stream_labrep_file((int) $me['id'], (int) ($_GET['id'] ?? 0));
    exit;
}

switch ($action) {

    // ----- list the vault, newest first ---------------------------------
    case 'list': {
        ecp_m_require_method('GET');
        $me = ecp_m_require_patient();
        $items = ecp_lab_list((int) $me['id']);
        ecp_m_ok([
            'count' => count($items),
            'items' => array_map('ecp_m_shape_labrep', $items),
        ]);
        break;
    }

    // ----- one report (metadata + file pointer) --------------------------
    case 'detail': {
        ecp_m_require_method('GET');
        $me = ecp_m_require_patient();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) ecp_m_err('id_required', 400);
        // ecp_lab_find is owner-scoped — a foreign id returns null, not a row.
        $row = ecp_lab_find((int) $me['id'], $id);
        if ($row === null) ecp_m_err('not_found', 404);
        ecp_m_ok(['item' => ecp_m_shape_labrep($row)]);
        break;
    }

    // ----- patient self-upload (multipart) -------------------------------
    case 'add': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        // Text fields arrive as multipart form fields alongside the file.
        $fields = $_POST ?: ecp_m_input();
        $file   = $_FILES['file'] ?? null;
        $res    = ecp_lab_add_upload((int) $me['id'], $fields, $file);
        if (!$res['ok']) {
            $status = match ($res['error'] ?? '') {
                'label_required', 'member_not_found' => 400,
                'file_too_large'                     => 413,
                'file_type_not_allowed'              => 415,
                'upload_failed'                      => 500,
                default                              => 400,
            };
            // Pass through only the hints the data layer actually set.
            $extra = array_filter([
                'max_mb'  => $res['max_mb']  ?? null,
                'allowed' => $res['allowed'] ?? null,
            ], static fn($v) => $v !== null);
            ecp_m_err($res['error'] ?? 'add_failed', $status, $extra);
        }
        $row = ecp_lab_find((int) $me['id'], (int) $res['id']);
        ecp_m_ok([
            'id'   => (int) $res['id'],
            'item' => $row ? ecp_m_shape_labrep($row) : null,
        ], 201);
        break;
    }

    // ----- soft delete ----------------------------------------------------
    case 'remove': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        $in = ecp_m_input();
        $id = (int) ($in['id'] ?? 0);
        if ($id <= 0) ecp_m_err('id_required', 400);
        $res = ecp_lab_remove((int) $me['id'], $id);
        if (!$res['ok']) {
            $err = $res['error'] ?? 'remove_failed';
            ecp_m_err($err, $err === 'not_found' ? 404 : 400);
        }
        ecp_m_ok();
        break;
    }

    default:
        ecp_m_err('unknown_action', 400);
}

// ---------------------------------------------------------------------
// Shaping + streaming
// ---------------------------------------------------------------------

/**
 * Client shape for one lab-report row. Adds the `file_url` the app opens and
 * drops internal columns (file_path, owner_identity_id).
 *
 * @param array<string,mixed> $r
 * @return array<string,mixed>
 */
function ecp_m_shape_labrep(array $r): array {
    $id      = (int) $r['id'];
    $hasFile = !empty($r['file_mime']);
    return [
        'id'               => $id,
        'family_member_id' => isset($r['family_member_id']) && $r['family_member_id'] !== null
                                ? (int) $r['family_member_id'] : null,
        'label'            => $r['label']       ?? null,
        'test_type'        => $r['test_type']   ?? null,
        'lab_name'         => $r['lab_name']    ?? null,
        'doctor_name'      => $r['doctor_name'] ?? null,
        'notes'            => $r['notes']       ?? null,
        'reported_on'      => $r['reported_on'] ?? null,
        'source'           => $r['source']      ?? 'upload',
        'has_file'         => $hasFile,
        'file_mime'        => $r['file_mime'] ?? null,
        'is_pdf'           => ($r['file_mime'] ?? '') === 'application/pdf',
        // Relative on purpose: the app prefixes its API base URL and sends
        // the Bearer token, since the file is NOT public.
        'file_url'         => $hasFile ? 'api/mobile/v1/lab_reports.php?action=file&id=' . $id : null,
        'created_at'       => $r['created_at'] ?? null,
    ];
}

/**
 * Stream a stored report file after re-checking ownership.
 *
 * Mirrors ecp_lab_stream_file() in api/patient_lab_reports.php (that file is
 * a top-level script, not includable). Ownership comes from ecp_lab_find();
 * the realpath guard is defence-in-depth so only the patient_labs tree is
 * ever served.
 */
function ecp_m_stream_labrep_file(int $ownerId, int $id): void {
    if ($id <= 0) { http_response_code(404); exit; }
    $row = ecp_lab_find($ownerId, $id);
    if ($row === null || empty($row['file_path'])) { http_response_code(404); exit; }

    $real = realpath(__DIR__ . '/../../../' . ltrim((string) $row['file_path'], '/')) ?: '';
    $base = realpath(__DIR__ . '/../../../storage/patient_labs') ?: '';
    if ($real === '' || $base === '' || !str_starts_with($real, $base) || !is_file($real)) {
        http_response_code(404);
        exit;
    }

    // _bootstrap.php already sent a JSON Content-Type — overwrite it.
    header('Content-Type: ' . (string) ($row['file_mime'] ?? 'application/octet-stream'));
    header('Content-Disposition: inline; filename="lab-report-' . $id . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, no-store');
    header('Content-Length: ' . (string) filesize($real));
    readfile($real);
}
