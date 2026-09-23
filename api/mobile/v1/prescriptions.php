<?php
// =====================================================================
// api/mobile/v1/prescriptions.php — e-prescription vault facade.
//
// Thin adapter over partials/patient_prescriptions.php — the SAME data
// layer the website's patient panel uses (api/patient_prescriptions.php).
// Listing, ownership checks, upload validation, storage paths and soft
// delete all stay in ecp_rx_*; this file only adapts transport.
//
// The vault holds ONE table (patient_prescriptions) with two sources:
//   source = 'upload' → the patient added a photo/PDF of an outside Rx
//   source = 'clinic' → an eClinicPro doctor shared it during a visit
//
// NOTE FOR THE APP (design gap, deliberate): the DB stores a FILE plus a
// label snapshot — it does NOT store structured medicine lines (name /
// 1-0-1 / duration), a diagnosis or advice text. So the Rx detail screen
// renders the metadata below plus the file itself via ?action=file.
// `file_url` is the path the app should open in its PDF/image viewer.
//
//   GET  ?action=list                      (Bearer) → { items }
//   GET  ?action=detail&id=123             (Bearer) → { item }
//   GET  ?action=file&id=123               (Bearer) → raw PDF/image bytes
//   POST ?action=add                       (Bearer) multipart: file + fields
//        label* , doctor_name, clinic_name, notes, issued_on, family_member_id
//   POST ?action=remove  { id }            (Bearer) → soft delete
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../partials/patient_prescriptions.php';

$action = $_GET['action'] ?? 'list';

// The file stream sets its own headers and must not emit the JSON envelope.
// _bootstrap already sent Content-Type: application/json, so replace it.
if ($action === 'file') {
    $me = ecp_m_require_patient();
    ecp_m_stream_rx_file((int) $me['id'], (int) ($_GET['id'] ?? 0));
    exit;
}

switch ($action) {

    // ----- list the vault, newest first ---------------------------------
    case 'list': {
        ecp_m_require_method('GET');
        $me = ecp_m_require_patient();
        $items = ecp_rx_list((int) $me['id']);
        ecp_m_ok([
            'count' => count($items),
            'items' => array_map('ecp_m_shape_rx', $items),
        ]);
        break;
    }

    // ----- one prescription (metadata + file pointer) --------------------
    case 'detail': {
        ecp_m_require_method('GET');
        $me  = ecp_m_require_patient();
        $id  = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) ecp_m_err('id_required', 400);
        // ecp_rx_find is owner-scoped — a foreign id returns null, not a row.
        $row = ecp_rx_find((int) $me['id'], $id);
        if ($row === null) ecp_m_err('not_found', 404);
        ecp_m_ok(['item' => ecp_m_shape_rx($row)]);
        break;
    }

    // ----- patient self-upload (multipart) -------------------------------
    case 'add': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        // Text fields arrive as multipart form fields alongside the file, so
        // read $_POST here rather than ecp_m_input() (which prefers JSON).
        $fields = $_POST ?: ecp_m_input();
        $file   = $_FILES['file'] ?? null;
        $res    = ecp_rx_add_upload((int) $me['id'], $fields, $file);
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
        $row = ecp_rx_find((int) $me['id'], (int) $res['id']);
        ecp_m_ok([
            'id'   => (int) $res['id'],
            'item' => $row ? ecp_m_shape_rx($row) : null,
        ], 201);
        break;
    }

    // ----- soft delete ----------------------------------------------------
    case 'remove': {
        ecp_m_require_method('POST');
        $me  = ecp_m_require_patient();
        $in  = ecp_m_input();
        $id  = (int) ($in['id'] ?? 0);
        if ($id <= 0) ecp_m_err('id_required', 400);
        $res = ecp_rx_remove((int) $me['id'], $id);
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
 * Client shape for one prescription row. Adds the `file_url` the app opens
 * and drops internal columns (file_path, owner_identity_id) so the storage
 * layout is never exposed.
 *
 * @param array<string,mixed> $r
 * @return array<string,mixed>
 */
function ecp_m_shape_rx(array $r): array {
    $id       = (int) $r['id'];
    $hasFile  = !empty($r['file_mime']);
    return [
        'id'               => $id,
        'family_member_id' => isset($r['family_member_id']) && $r['family_member_id'] !== null
                                ? (int) $r['family_member_id'] : null,
        'label'            => $r['label']       ?? null,
        'doctor_name'      => $r['doctor_name'] ?? null,
        'clinic_name'      => $r['clinic_name'] ?? null,
        'notes'            => $r['notes']       ?? null,
        'issued_on'        => $r['issued_on']   ?? null,
        'source'           => $r['source']      ?? 'upload',
        'is_clinic'        => ($r['source'] ?? '') === 'clinic',
        'has_file'         => $hasFile,
        'file_mime'        => $r['file_mime'] ?? null,
        'is_pdf'           => ($r['file_mime'] ?? '') === 'application/pdf',
        // Relative on purpose: the app prefixes its configured API base URL
        // and sends the Bearer token, since the file is NOT public.
        'file_url'         => $hasFile ? 'api/mobile/v1/prescriptions.php?action=file&id=' . $id : null,
        'created_at'       => $r['created_at'] ?? null,
    ];
}

/**
 * Stream a stored Rx file after re-checking ownership.
 *
 * Mirrors ecp_rx_stream_file() in api/patient_prescriptions.php (that file
 * is a top-level script, not includable — including it would run the web
 * cookie-auth path). Ownership comes from ecp_rx_find(); the realpath guard
 * is defence-in-depth so only the patient_rx tree is ever served.
 */
function ecp_m_stream_rx_file(int $ownerId, int $id): void {
    if ($id <= 0) { http_response_code(404); exit; }
    $row = ecp_rx_find($ownerId, $id);
    if ($row === null || empty($row['file_path'])) { http_response_code(404); exit; }

    $real = realpath(__DIR__ . '/../../../' . ltrim((string) $row['file_path'], '/')) ?: '';
    $base = realpath(__DIR__ . '/../../../storage/patient_rx') ?: '';
    if ($real === '' || $base === '' || !str_starts_with($real, $base) || !is_file($real)) {
        http_response_code(404);
        exit;
    }

    // _bootstrap.php already sent a JSON Content-Type — overwrite it.
    header('Content-Type: ' . (string) ($row['file_mime'] ?? 'application/octet-stream'));
    header('Content-Disposition: inline; filename="prescription-' . $id . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, no-store');
    header('Content-Length: ' . (string) filesize($real));
    readfile($real);
}
