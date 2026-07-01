<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use PDO;

/**
 * Family profiles: private rows in family_member_identities (patient panel),
 * clinic-facing snapshots in patient_family_members (created on booking).
 */
final class FamilyMemberService
{
    private const GENDERS = ['M', 'F', 'Other'];
    private const BLOOD   = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

    /**
     * Private family rows for the patient panel / booking picker.
     *
     * @return list<array<string, mixed>>
     */
    public static function listIdentitiesForOwner(int $ownerId): array
    {
        if ($ownerId <= 0) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, relation, is_self, name, dob, gender, blood_group, abha_id
             FROM family_member_identities
             WHERE owner_identity_id = :o AND is_active = 1
             ORDER BY is_self DESC, sort_order ASC, name ASC'
        );
        $stmt->execute(['o' => $ownerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['is_self'] = (int) ($row['is_self'] ?? 0) === 1;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function identityForOwner(int $ownerId, int $identityMemberId): ?array
    {
        if ($ownerId <= 0 || $identityMemberId <= 0) {
            return null;
        }

        $row = QueryBuilder::table('family_member_identities')
            ->where('id', '=', $identityMemberId)
            ->where('owner_identity_id', '=', $ownerId)
            ->where('is_active', '=', 1)
            ->first();

        if (!$row) {
            return null;
        }

        $row['is_self'] = (int) ($row['is_self'] ?? 0) === 1;

        return $row;
    }

    /** Back-compat alias for booking flow. */
    public static function memberForOwner(int $ownerId, int $memberId): ?array
    {
        return self::identityForOwner($ownerId, $memberId);
    }

    /** Back-compat alias. */
    public static function listForOwner(int $ownerId): array
    {
        return self::listIdentitiesForOwner($ownerId);
    }

    /**
     * Copy the private identity into patient_family_members for a clinic.
     * Doctors and clinic charts reference this shared snapshot, not the panel row.
     *
     * @return array<string, mixed>|null Shared row (patient_family_members)
     */
    public static function shareIdentityToClinic(int $clinicId, int $ownerId, int $identityMemberId): ?array
    {
        if ($clinicId <= 0 || $ownerId <= 0 || $identityMemberId <= 0) {
            return null;
        }

        $identity = self::identityForOwner($ownerId, $identityMemberId);
        if ($identity === null) {
            return null;
        }

        $snapshot = [
            'relation'    => (string) ($identity['relation'] ?? 'other'),
            'name'        => trim((string) ($identity['name'] ?? '')),
            'dob'         => !empty($identity['dob']) ? (string) $identity['dob'] : null,
            'gender'      => in_array($identity['gender'] ?? '', self::GENDERS, true) ? $identity['gender'] : null,
            'blood_group' => in_array($identity['blood_group'] ?? '', self::BLOOD, true) ? $identity['blood_group'] : null,
            'abha_id'     => !empty($identity['abha_id']) ? (string) $identity['abha_id'] : null,
        ];

        if ($snapshot['name'] === '') {
            return null;
        }

        $existing = QueryBuilder::table('patient_family_members')
            ->where('clinic_id', '=', $clinicId)
            ->where('family_member_identity_id', '=', $identityMemberId)
            ->where('owner_identity_id', '=', $ownerId)
            ->first();

        if ($existing !== null) {
            QueryBuilder::table('patient_family_members')
                ->where('id', '=', (int) $existing['id'])
                ->update($snapshot);

            return QueryBuilder::table('patient_family_members')
                ->where('id', '=', (int) $existing['id'])
                ->first();
        }

        $id = QueryBuilder::table('patient_family_members')->insert(array_merge($snapshot, [
            'clinic_id'                  => $clinicId,
            'family_member_identity_id'  => $identityMemberId,
            'owner_identity_id'          => $ownerId,
            'is_self'                    => !empty($identity['is_self']) ? 1 : 0,
            'sort_order'                 => (int) ($identity['sort_order'] ?? 100),
            'is_active'                  => 1,
        ]));

        return QueryBuilder::table('patient_family_members')->where('id', '=', $id)->first();
    }

    /** @param array<string, mixed> $member */
    public static function sanitizeMemberDemographics(array $member): array
    {
        return [
            'name'        => trim((string) ($member['name'] ?? '')),
            'dob'         => !empty($member['dob']) ? (string) $member['dob'] : null,
            'gender'      => in_array($member['gender'] ?? '', self::GENDERS, true) ? $member['gender'] : null,
            'blood_group' => in_array($member['blood_group'] ?? '', self::BLOOD, true) ? $member['blood_group'] : null,
        ];
    }
}
