<?php
// =====================================================================
// api/mobile/v1/family.php — family profiles facade.
//
// Thin adapter over partials/patient_family.php — the SAME data layer the
// website's patient-panel Family tab uses (api/family.php). Member limit,
// the auto-created "self" row, relation/gender/blood validation and the
// soft-remove rule all stay in ecp_fam_*; this file only adapts transport.
//
// The "self" row is created on demand by ecp_fam_ensure_self() inside
// ecp_fam_list(), so the first list call always returns at least one member.
// Self cannot be removed and its relation is pinned to 'self'.
//
// NOTE FOR THE APP: the design's relation chips (Mother/Father/Husband/
// Wife/Son/Daughter/Other) do NOT all exist in the DB enum — it stores
// spouse rather than husband/wife. `options.relations` below is the
// authoritative list; render labels from it instead of hardcoding, and map
// husband/wife → spouse. Anything unrecognised is stored as 'other'.
//
//   GET  ?action=list                      (Bearer) → { members, options, can_add }
//   GET  ?action=detail&id=12              (Bearer) → { member }
//   POST ?action=save                      (Bearer) → create or update
//        { member_id?, name*, relation, dob, gender, blood_group, abha_id }
//   POST ?action=remove  { member_id }     (Bearer) → soft delete
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../partials/patient_family.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    // ----- list members (self first) -------------------------------------
    case 'list': {
        ecp_m_require_method('GET');
        $me      = ecp_m_require_patient();
        $ownerId = (int) $me['id'];
        $members = ecp_fam_list($ownerId);            // ensures the self row
        ecp_m_ok([
            'count'       => count($members),
            'members'     => array_map('ecp_m_shape_member', $members),
            'can_add'     => ecp_fam_can_add($ownerId),
            'max_members' => ECP_FAM_MAX_MEMBERS,
            // Authoritative pick-lists so the app never hardcodes enums.
            'options'     => [
                'relations'    => array_values(array_filter(
                    ECP_FAM_RELATIONS,
                    static fn($r) => $r !== 'self'     // self is not selectable
                )),
                'genders'      => ECP_FAM_GENDERS,
                'blood_groups' => ECP_FAM_BLOOD,
            ],
        ]);
        break;
    }

    // ----- one member -----------------------------------------------------
    case 'detail': {
        ecp_m_require_method('GET');
        $me = ecp_m_require_patient();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) ecp_m_err('member_id_required', 400);
        // ecp_fam_member is owner-scoped — a foreign id returns null.
        $row = ecp_fam_member((int) $me['id'], $id);
        if ($row === null) ecp_m_err('not_found', 404);
        ecp_m_ok(['member' => ecp_m_shape_member($row)]);
        break;
    }

    // ----- create or update ----------------------------------------------
    // Same call for both: omit member_id to create, send it to update.
    case 'save': {
        ecp_m_require_method('POST');
        $me  = ecp_m_require_patient();
        $in  = ecp_m_input();
        $res = ecp_fam_save_member((int) $me['id'], $in);
        if (!$res['ok']) {
            $status = match ($res['error'] ?? '') {
                'name_required'        => 400,
                'not_authorized'       => 404,   // don't confirm a foreign id exists
                'member_limit_reached' => 409,
                'db_unavailable'       => 503,
                default                => 400,
            };
            // Only attach the cap when that is actually why we refused.
            $extra = ($res['error'] ?? '') === 'member_limit_reached'
                ? ['max_members' => ECP_FAM_MAX_MEMBERS] : [];
            ecp_m_err($res['error'] ?? 'save_failed', $status, $extra);
        }
        $row = ecp_fam_member((int) $me['id'], (int) $res['member_id']);
        ecp_m_ok([
            'member_id' => (int) $res['member_id'],
            'member'    => $row ? ecp_m_shape_member($row) : null,
        ]);
        break;
    }

    // ----- soft delete (never the self row) -------------------------------
    case 'remove': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        $in = ecp_m_input();
        $memberId = (int) ($in['member_id'] ?? 0);
        if ($memberId <= 0) ecp_m_err('member_id_required', 400);
        $res = ecp_fam_remove_member((int) $me['id'], $memberId);
        if (!$res['ok']) {
            $status = match ($res['error'] ?? '') {
                'not_authorized'    => 404,
                'cannot_remove_self' => 409,
                default              => 400,
            };
            ecp_m_err($res['error'] ?? 'remove_failed', $status);
        }
        ecp_m_ok();
        break;
    }

    default:
        ecp_m_err('unknown_action', 400);
}

// ---------------------------------------------------------------------

/**
 * Client shape for one family member. The design's member card shows an
 * age and initials, which the DB does not store — both are derived here so
 * every client renders them identically.
 *
 * @param array<string,mixed> $m
 * @return array<string,mixed>
 */
function ecp_m_shape_member(array $m): array {
    $name = (string) ($m['name'] ?? '');
    return [
        'id'          => (int) $m['id'],
        'name'        => $name !== '' ? $name : null,
        'initials'    => ecp_m_initials($name),
        'relation'    => $m['relation'] ?? null,
        'is_self'     => (int) ($m['is_self'] ?? 0) === 1,
        'dob'         => $m['dob'] ?? null,
        'age'         => ecp_m_age_from_dob($m['dob'] ?? null),
        'gender'      => $m['gender'] ?? null,
        'blood_group' => $m['blood_group'] ?? null,
        'abha_id'     => $m['abha_id'] ?? null,
    ];
}

/** Up to two initials for an avatar chip ("Ravi Sharma" → "RS"). */
function ecp_m_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));
    if ($parts === []) return '';
    $first = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) === 1) return $first;
    return $first . mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1));
}

/** Whole years from a YYYY-MM-DD dob, or null when dob is unset/invalid. */
function ecp_m_age_from_dob(?string $dob): ?int {
    if ($dob === null || trim($dob) === '' || str_starts_with($dob, '0000')) return null;
    try {
        $d = new DateTimeImmutable($dob);
    } catch (Exception) {
        return null;
    }
    $age = (int) $d->diff(new DateTimeImmutable('today'))->y;
    return ($age >= 0 && $age < 130) ? $age : null;
}
