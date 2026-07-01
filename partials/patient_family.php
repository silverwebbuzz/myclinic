<?php
// =====================================================================
// patient_family.php — data layer for the patient-panel Family tab.
//
// Private family profiles live in family_member_identities (patient panel
// only). When the patient books an appointment, a snapshot is copied into
// patient_family_members for that clinic — doctors see the shared copy,
// which can differ from the private profile later.
//
// AUTHORIZATION RULE (enforced in every mutator): a member row may only be
// read/edited when its owner_identity_id == the logged-in identity. No IDOR.
//
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const ECP_FAM_TABLE       = 'family_member_identities';
const ECP_FAM_RELATIONS   = ['self','spouse','mother','father','son','daughter','guardian','other'];
const ECP_FAM_GENDERS     = ['M','F','Other'];
const ECP_FAM_BLOOD       = ['A+','A-','B+','B-','O+','O-','AB+','AB-'];
const ECP_FAM_MAX_MEMBERS = 6;

/**
 * Ensure the owner has a `self` family-member row, seeded from their identity,
 * so the UI renders the owner alongside the rest of the family uniformly.
 */
function ecp_fam_ensure_self(int $ownerId): void
{
    $db = ecp_db();
    if (!$db || $ownerId <= 0) return;

    $chk = $db->prepare(
        'SELECT id FROM ' . ECP_FAM_TABLE . '
         WHERE owner_identity_id = :o AND is_self = 1 LIMIT 1'
    );
    $chk->execute(['o' => $ownerId]);
    if ($chk->fetchColumn()) return;

    $idn = $db->prepare('SELECT name, dob, gender, blood_group, abha_id FROM patient_identities WHERE id = :id LIMIT 1');
    $idn->execute(['id' => $ownerId]);
    $me = $idn->fetch(PDO::FETCH_ASSOC) ?: [];

    $ins = $db->prepare(
        'INSERT INTO ' . ECP_FAM_TABLE . '
            (owner_identity_id, relation, is_self, sort_order, name, dob, gender, blood_group, abha_id)
         VALUES
            (:o, "self", 1, 0, :name, :dob, :gender, :blood, :abha)'
    );
    $ins->execute([
        'o'      => $ownerId,
        'name'   => $me['name'] ?: 'Me',
        'dob'    => $me['dob'] ?? null,
        'gender' => in_array($me['gender'] ?? '', ECP_FAM_GENDERS, true) ? $me['gender'] : null,
        'blood'  => in_array($me['blood_group'] ?? '', ECP_FAM_BLOOD, true) ? $me['blood_group'] : null,
        'abha'   => ecp_fam_clean_abha($me['abha_id'] ?? ''),
    ]);
}

/**
 * @return array<string,mixed>|null
 */
function ecp_fam_member(int $ownerId, int $memberId): ?array
{
    $db = ecp_db();
    if (!$db || $memberId <= 0) return null;
    $stmt = $db->prepare(
        'SELECT * FROM ' . ECP_FAM_TABLE . '
         WHERE id = :m AND owner_identity_id = :o AND is_active = 1 LIMIT 1'
    );
    $stmt->execute(['m' => $memberId, 'o' => $ownerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * @return list<array<string,mixed>>
 */
function ecp_fam_list(int $ownerId): array
{
    $db = ecp_db();
    if (!$db || $ownerId <= 0) return [];
    ecp_fam_ensure_self($ownerId);

    $stmt = $db->prepare(
        'SELECT id, relation, is_self, name, dob, gender, blood_group, abha_id
         FROM ' . ECP_FAM_TABLE . '
         WHERE owner_identity_id = :o AND is_active = 1
         ORDER BY is_self DESC, sort_order ASC, name ASC'
    );
    $stmt->execute(['o' => $ownerId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($members as &$m) {
        $m['is_self'] = (int) $m['is_self'] === 1;
    }
    unset($m);

    return $members;
}

function ecp_fam_active_count(int $ownerId): int
{
    $db = ecp_db();
    if (!$db || $ownerId <= 0) return 0;
    ecp_fam_ensure_self($ownerId);
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM ' . ECP_FAM_TABLE . '
         WHERE owner_identity_id = :o AND is_active = 1'
    );
    $stmt->execute(['o' => $ownerId]);
    return (int) $stmt->fetchColumn();
}

function ecp_fam_can_add(int $ownerId): bool
{
    return ecp_fam_active_count($ownerId) < ECP_FAM_MAX_MEMBERS;
}

/**
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

    $relation = $isSelf ? 'self'
        : (in_array($data['relation'] ?? '', ECP_FAM_RELATIONS, true) && $data['relation'] !== 'self'
            ? $data['relation'] : 'other');

    $fields = [
        'relation'    => $relation,
        'name'        => $name,
        'dob'         => ecp_fam_clean_date($data['dob'] ?? ''),
        'gender'      => in_array($data['gender'] ?? '', ECP_FAM_GENDERS, true) ? $data['gender'] : null,
        'blood_group' => in_array($data['blood_group'] ?? '', ECP_FAM_BLOOD, true) ? $data['blood_group'] : null,
        'abha_id'     => ecp_fam_clean_abha($data['abha_id'] ?? ''),
    ];

    if ($memberId > 0) {
        $set = [];
        $params = ['id' => $memberId, 'o' => $ownerId];
        foreach ($fields as $col => $val) { $set[] = "`$col` = :$col"; $params[$col] = $val; }
        $db->prepare(
            'UPDATE ' . ECP_FAM_TABLE . ' SET ' . implode(', ', $set) .
            ' WHERE id = :id AND owner_identity_id = :o'
        )->execute($params);
        return ['ok' => true, 'member_id' => $memberId];
    }

    if (!ecp_fam_can_add($ownerId)) {
        return ['ok' => false, 'error' => 'member_limit_reached'];
    }

    $cols = array_keys($fields);
    $place = array_map(static fn($c) => ":$c", $cols);
    $sql = 'INSERT INTO ' . ECP_FAM_TABLE . ' (owner_identity_id, sort_order, '
         . implode(', ', array_map(static fn($c) => "`$c`", $cols)) . ') VALUES (:o, 100, '
         . implode(', ', $place) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute(['o' => $ownerId] + $fields);
    return ['ok' => true, 'member_id' => (int) $db->lastInsertId()];
}

function ecp_fam_remove_member(int $ownerId, int $memberId): array
{
    $m = ecp_fam_member($ownerId, $memberId);
    if (!$m) return ['ok' => false, 'error' => 'not_authorized'];
    if ((int) $m['is_self'] === 1) return ['ok' => false, 'error' => 'cannot_remove_self'];
    ecp_db()->prepare(
        'UPDATE ' . ECP_FAM_TABLE . ' SET is_active = 0
         WHERE id = :m AND owner_identity_id = :o'
    )->execute(['m' => $memberId, 'o' => $ownerId]);
    return ['ok' => true];
}

function ecp_fam_clean_date($raw): ?string
{
    $s = trim((string) $raw);
    if ($s === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}

function ecp_fam_clean_abha($raw): ?string
{
    $s = preg_replace('/[^0-9]/', '', (string) $raw) ?: '';
    return $s !== '' ? $s : null;
}

