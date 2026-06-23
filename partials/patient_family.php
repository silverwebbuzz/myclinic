<?php
// =====================================================================
// patient_family.php — data layer for the patient-panel Family tab.
//
// Family members are ISOLATED, PRIVATE DATA owned by one account holder
// (a patient_identities row). They live entirely in patient_family_members
// — they are NOT login accounts and never touch patient_identities. So a
// member another account typed is invisible to everyone else, and a member's
// phone is plain contact text, not a link to any login.
//
// AUTHORIZATION RULE (enforced in every mutator): a member row may only be
// read/edited when its owner_identity_id == the logged-in identity. No IDOR.
//
// No Aadhaar is stored anywhere — ABHA + insurance numbers only.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const ECP_FAM_RELATIONS = ['self','spouse','mother','father','son','daughter','guardian','other'];
const ECP_FAM_BLOOD     = ['A+','A-','B+','B-','O+','O-','AB+','AB-'];
const ECP_FAM_GENDERS   = ['M','F','Other'];
const ECP_POLICY_TYPES  = ['health','topup','personal_accident','critical_illness','other'];
const ECP_DOC_TYPES     = ['abha','insurance_card','id_photo','prescription','lab_report','vaccine_cert','other'];

/**
 * Ensure the owner has a `self` family-member row, seeded from their identity,
 * so the UI renders the owner alongside the rest of the family uniformly.
 */
function ecp_fam_ensure_self(int $ownerId): void
{
    $db = ecp_db();
    if (!$db || $ownerId <= 0) return;

    $chk = $db->prepare(
        'SELECT id FROM patient_family_members
         WHERE owner_identity_id = :o AND is_self = 1 LIMIT 1'
    );
    $chk->execute(['o' => $ownerId]);
    if ($chk->fetchColumn()) return;

    // Seed from the owner's own identity (name/phone/health if present).
    $idn = $db->prepare('SELECT * FROM patient_identities WHERE id = :id LIMIT 1');
    $idn->execute(['id' => $ownerId]);
    $me = $idn->fetch(PDO::FETCH_ASSOC) ?: [];

    $ins = $db->prepare(
        'INSERT INTO patient_family_members
            (owner_identity_id, relation, is_self, sort_order, name, first_name, last_name,
             dob, gender, blood_group, phone, email, allergies, chronic_conditions,
             emergency_contact_name, emergency_contact_phone, emergency_contact_relation, abha_id)
         VALUES
            (:o, "self", 1, 0, :name, :first, :last, :dob, :gender, :blood, :phone, :email,
             :allergies, :chronic, :ecn, :ecp, :ecr, :abha)'
    );
    $ins->execute([
        'o'        => $ownerId,
        'name'     => $me['name'] ?: 'Me',
        'first'    => $me['first_name'] ?? null,
        'last'     => $me['last_name'] ?? null,
        'dob'      => $me['dob'] ?? null,
        'gender'   => in_array($me['gender'] ?? '', ECP_FAM_GENDERS, true) ? $me['gender'] : null,
        'blood'    => in_array($me['blood_group'] ?? '', ECP_FAM_BLOOD, true) ? $me['blood_group'] : null,
        'phone'    => $me['phone'] ?? null,
        'email'    => $me['email'] ?? null,
        'allergies' => $me['allergies'] ?? null,
        'chronic'  => $me['chronic_conditions'] ?? null,
        'ecn'      => $me['emergency_contact_name'] ?? null,
        'ecp'      => $me['emergency_contact_phone'] ?? null,
        'ecr'      => $me['emergency_contact_relation'] ?? null,
        'abha'     => $me['abha_id'] ?? null,
    ]);
}

/**
 * Fetch a member row IF it belongs to $ownerId, else null. The ownership gate
 * for every operation.
 *
 * @return array<string,mixed>|null
 */
function ecp_fam_member(int $ownerId, int $memberId): ?array
{
    $db = ecp_db();
    if (!$db || $memberId <= 0) return null;
    $stmt = $db->prepare(
        'SELECT * FROM patient_family_members
         WHERE id = :m AND owner_identity_id = :o AND is_active = 1 LIMIT 1'
    );
    $stmt->execute(['m' => $memberId, 'o' => $ownerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** True if $ownerId may manage $memberId (used by the doc/api layer). */
function ecp_fam_can_manage(int $ownerId, int $memberId): bool
{
    return ecp_fam_member($ownerId, $memberId) !== null;
}

/**
 * Full family payload for the owner: every active member with insurance
 * policies and document metadata nested in.
 *
 * @return list<array<string,mixed>>
 */
function ecp_fam_list(int $ownerId): array
{
    $db = ecp_db();
    if (!$db || $ownerId <= 0) return [];
    ecp_fam_ensure_self($ownerId);

    $stmt = $db->prepare(
        'SELECT id, relation, is_self, is_minor, name, first_name, last_name, phone, email,
                dob, gender, blood_group, allergies, chronic_conditions, photo_path, abha_id,
                emergency_contact_name, emergency_contact_phone, emergency_contact_relation
         FROM patient_family_members
         WHERE owner_identity_id = :o AND is_active = 1
         ORDER BY is_self DESC, sort_order ASC, name ASC'
    );
    $stmt->execute(['o' => $ownerId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$members) return [];

    $ids = array_map(static fn($m) => (int) $m['id'], $members);
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $pol = $db->prepare(
        "SELECT id, family_member_id, insurer_name, policy_type, policy_number,
                sum_insured_inr, valid_till, document_id
         FROM patient_insurance_policies WHERE family_member_id IN ($in) ORDER BY id ASC"
    );
    $pol->execute($ids);
    $policiesByMember = [];
    foreach ($pol->fetchAll(PDO::FETCH_ASSOC) ?: [] as $p) {
        $policiesByMember[(int) $p['family_member_id']][] = $p;
    }

    $doc = $db->prepare(
        "SELECT id, family_member_id, doc_type, title, mime_type, size_bytes, next_due_on, created_at
         FROM patient_owned_documents WHERE family_member_id IN ($in) ORDER BY created_at DESC"
    );
    $doc->execute($ids);
    $docsByMember = [];
    foreach ($doc->fetchAll(PDO::FETCH_ASSOC) ?: [] as $d) {
        $docsByMember[(int) $d['family_member_id']][] = $d;
    }

    foreach ($members as &$m) {
        $mid = (int) $m['id'];
        $m['is_self']   = (int) $m['is_self'] === 1;
        $m['policies']  = $policiesByMember[$mid] ?? [];
        $m['documents'] = $docsByMember[$mid] ?? [];
    }
    unset($m);

    return $members;
}

/**
 * Create or update a member under $ownerId. Pure data — never creates a
 * patient_identities row, never looks anyone up by phone.
 *
 * @param array<string,mixed> $data
 * @return array{ok:bool, member_id?:int, error?:string}
 */
function ecp_fam_save_member(int $ownerId, array $data): array
{
    $db = ecp_db();
    if (!$db || $ownerId <= 0) return ['ok' => false, 'error' => 'db_unavailable'];

    $memberId = (int) ($data['member_id'] ?? 0);
    $name     = trim((string) ($data['name'] ?? ''));
    if ($name === '') return ['ok' => false, 'error' => 'name_required'];

    $existing = $memberId > 0 ? ecp_fam_member($ownerId, $memberId) : null;
    if ($memberId > 0 && !$existing) return ['ok' => false, 'error' => 'not_authorized'];
    $isSelf = $existing && (int) $existing['is_self'] === 1;

    // The "self" row keeps relation 'self'; everyone else takes the given one.
    $relation = $isSelf ? 'self'
        : (in_array($data['relation'] ?? '', ECP_FAM_RELATIONS, true) && $data['relation'] !== 'self'
            ? $data['relation'] : 'other');

    $phoneNorm = '';
    $phone = trim((string) ($data['phone'] ?? ''));
    if ($phone !== '' && function_exists('ecp_normalize_phone')) {
        $phoneNorm = ecp_normalize_phone($phone);
    }
    $email = trim((string) ($data['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

    $fields = [
        'relation'                   => $relation,
        'name'                       => $name,
        'first_name'                 => trim((string) ($data['first_name'] ?? '')) ?: null,
        'last_name'                  => trim((string) ($data['last_name'] ?? '')) ?: null,
        'dob'                        => ecp_fam_clean_date($data['dob'] ?? ''),
        'gender'                     => in_array($data['gender'] ?? '', ECP_FAM_GENDERS, true) ? $data['gender'] : null,
        'blood_group'                => in_array($data['blood_group'] ?? '', ECP_FAM_BLOOD, true) ? $data['blood_group'] : null,
        'phone'                      => $phoneNorm !== '' ? $phoneNorm : ($phone !== '' ? $phone : null),
        'email'                      => $email !== '' ? $email : null,
        'allergies'                  => trim((string) ($data['allergies'] ?? '')) ?: null,
        'chronic_conditions'         => trim((string) ($data['chronic_conditions'] ?? '')) ?: null,
        'abha_id'                    => preg_replace('/[^0-9]/', '', (string) ($data['abha_id'] ?? '')) ?: null,
        'emergency_contact_name'     => trim((string) ($data['emergency_contact_name'] ?? '')) ?: null,
        'emergency_contact_phone'    => trim((string) ($data['emergency_contact_phone'] ?? '')) ?: null,
        'emergency_contact_relation' => trim((string) ($data['emergency_contact_relation'] ?? '')) ?: null,
        'is_minor'                   => !empty($data['is_minor']) ? 1 : 0,
    ];

    if ($memberId > 0) {
        $set = [];
        $params = ['id' => $memberId, 'o' => $ownerId];
        foreach ($fields as $col => $val) { $set[] = "`$col` = :$col"; $params[$col] = $val; }
        $db->prepare(
            'UPDATE patient_family_members SET ' . implode(', ', $set) .
            ' WHERE id = :id AND owner_identity_id = :o'
        )->execute($params);
        return ['ok' => true, 'member_id' => $memberId];
    }

    // INSERT new private member.
    $cols = array_keys($fields);
    $place = array_map(static fn($c) => ":$c", $cols);
    $sql = 'INSERT INTO patient_family_members (owner_identity_id, sort_order, '
         . implode(', ', array_map(static fn($c) => "`$c`", $cols)) . ') VALUES (:o, 100, '
         . implode(', ', $place) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute(['o' => $ownerId] + $fields);
    return ['ok' => true, 'member_id' => (int) $db->lastInsertId()];
}

/** Soft-remove a member (never the owner's own `self` row). */
function ecp_fam_remove_member(int $ownerId, int $memberId): array
{
    $m = ecp_fam_member($ownerId, $memberId);
    if (!$m) return ['ok' => false, 'error' => 'not_authorized'];
    if ((int) $m['is_self'] === 1) return ['ok' => false, 'error' => 'cannot_remove_self'];
    ecp_db()->prepare(
        'UPDATE patient_family_members SET is_active = 0
         WHERE id = :m AND owner_identity_id = :o'
    )->execute(['m' => $memberId, 'o' => $ownerId]);
    return ['ok' => true];
}

// ---------------------------------------------------------------------
// Insurance policies
// ---------------------------------------------------------------------

/** @param array<string,mixed> $data */
function ecp_fam_save_policy(int $ownerId, array $data): array
{
    $memberId = (int) ($data['member_id'] ?? 0);
    if (!ecp_fam_can_manage($ownerId, $memberId)) return ['ok' => false, 'error' => 'not_authorized'];
    $db = ecp_db();

    $type = in_array($data['policy_type'] ?? '', ECP_POLICY_TYPES, true) ? $data['policy_type'] : 'health';
    $row = [
        'insurer' => trim((string) ($data['insurer_name'] ?? '')) ?: null,
        'type'    => $type,
        'number'  => trim((string) ($data['policy_number'] ?? '')) ?: null,
        'sum'     => ($data['sum_insured_inr'] ?? '') !== '' ? (float) $data['sum_insured_inr'] : null,
        'valid'   => ecp_fam_clean_date($data['valid_till'] ?? ''),
    ];
    $policyId = (int) ($data['policy_id'] ?? 0);

    if ($policyId > 0) {
        $chk = $db->prepare('SELECT family_member_id FROM patient_insurance_policies WHERE id = :id');
        $chk->execute(['id' => $policyId]);
        if ((int) ($chk->fetchColumn() ?: 0) !== $memberId) return ['ok' => false, 'error' => 'not_authorized'];
        $db->prepare(
            'UPDATE patient_insurance_policies
             SET insurer_name=:insurer, policy_type=:type, policy_number=:number,
                 sum_insured_inr=:sum, valid_till=:valid WHERE id=:id'
        )->execute($row + ['id' => $policyId]);
        return ['ok' => true, 'policy_id' => $policyId];
    }

    $db->prepare(
        'INSERT INTO patient_insurance_policies
            (family_member_id, insurer_name, policy_type, policy_number, sum_insured_inr, valid_till)
         VALUES (:mid, :insurer, :type, :number, :sum, :valid)'
    )->execute($row + ['mid' => $memberId]);
    return ['ok' => true, 'policy_id' => (int) $db->lastInsertId()];
}

function ecp_fam_delete_policy(int $ownerId, int $policyId): array
{
    $db = ecp_db();
    $chk = $db->prepare('SELECT family_member_id FROM patient_insurance_policies WHERE id = :id');
    $chk->execute(['id' => $policyId]);
    $mid = (int) ($chk->fetchColumn() ?: 0);
    if ($mid === 0 || !ecp_fam_can_manage($ownerId, $mid)) return ['ok' => false, 'error' => 'not_authorized'];
    $db->prepare('DELETE FROM patient_insurance_policies WHERE id = :id')->execute(['id' => $policyId]);
    return ['ok' => true];
}

// ---------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------

/** Accept YYYY-MM-DD only; return null otherwise. */
function ecp_fam_clean_date($raw): ?string
{
    $s = trim((string) $raw);
    if ($s === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}
