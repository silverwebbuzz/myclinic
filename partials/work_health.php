<?php
// =====================================================================
// partials/work_health.php — the employee's side of Workforce Health.
//
// A company adds someone (corp_employments) by mobile number; that row
// points at the person's own patient_identities row with link_status
// 'pending'. Here the PERSON sees it in their "Work health" tab and says
// "Yes, that's me" (confirmed) or "This isn't me" (declined). Until they
// confirm, nothing about them flows to the company or anywhere else.
//
// Design: document/workforce-health-phase1-design.md §2.4 (P3).
// Used by: patient.php (tab), api/work_health.php, api/mobile/v1/work_health.php.
//
// Only companies eClinicPro has approved (status 'active') are shown.
// corp_* DATETIMEs are UTC (gmdate), as everywhere in Workforce Health.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * The person's current workplaces: pending and confirmed links, people who
 * haven't left. Declined ones are not shown again.
 *
 * @return list<array<string, mixed>>
 */
function ecp_wh_list(int $identityId): array
{
    $db = ecp_db();
    if (!$db || $identityId <= 0) {
        return [];
    }
    try {
        $st = $db->prepare(
            "SELECT e.id, e.employee_no, e.full_name, e.link_status, e.link_confirmed_at, e.employment_type, e.contractor_company,
                    e.joined_on, e.status, e.created_at,
                    COALESCE(NULLIF(c.trade_name, ''), c.legal_name) AS company_name, c.country_code AS company_country,
                    b.name AS branch_name, b.city AS branch_city, j.title AS job_title, d.name AS department_name
               FROM corp_employments e
               JOIN corp_companies c ON c.id = e.company_id AND c.status = 'active'
               LEFT JOIN corp_branches b ON b.id = e.branch_id AND b.company_id = e.company_id
               LEFT JOIN corp_job_roles j ON j.id = e.job_role_id AND j.company_id = e.company_id
               LEFT JOIN corp_departments d ON d.id = e.department_id AND d.company_id = e.company_id
              WHERE e.identity_id = :iid AND e.link_status IN ('pending', 'confirmed') AND e.status <> 'terminated'
              ORDER BY e.link_status = 'pending' DESC, e.id DESC
              LIMIT 20"
        );
        $st->execute(['iid' => $identityId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Workforce tables not created yet on this server: the tab just stays hidden.
        return [];
    }
    $bookings = ecp_wh_bookings($identityId);
    foreach ($rows as &$r) {
        $r['bookings'] = $r['link_status'] === 'confirmed' ? ($bookings[(int) $r['id']] ?? []) : [];
    }
    unset($r);

    return $rows;
}

/**
 * Upcoming health-check appointments the employer booked (Phase 1C), per
 * employment id, for CONFIRMED links only. Clinic name/address/time and the
 * names of the checks; nothing clinical.
 *
 * @return array<int, list<array<string, mixed>>>
 */
function ecp_wh_bookings(int $identityId): array
{
    $db = ecp_db();
    if (!$db || $identityId <= 0) {
        return [];
    }
    try {
        $st = $db->prepare(
            "SELECT b.id, b.employment_id, b.scheduled_at, t.name AS clinic_name, t.address AS clinic_address, t.phone AS clinic_phone,
                    (SELECT GROUP_CONCAT(et.name ORDER BY et.sort_order SEPARATOR ', ') FROM corp_screening_cases c JOIN oh_exam_types et ON et.id = c.exam_type_id
                      WHERE c.company_id = b.company_id AND c.booking_id = b.id) AS checks
               FROM corp_bookings b
               JOIN corp_employments e ON e.id = b.employment_id AND e.company_id = b.company_id
               JOIN tenants t ON t.id = b.clinic_id
              WHERE e.identity_id = :iid AND e.link_status = 'confirmed' AND b.status = 'booked'
                AND b.scheduled_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
              ORDER BY b.scheduled_at
              LIMIT 20"
        );
        $st->execute(['iid' => $identityId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $out[(int) $b['employment_id']][] = $b;
        }

        return $out;
    } catch (Throwable $e) {
        return [];   // 1C patch not run yet
    }
}

/** Should the "Work health" tab show? One cheap query per page view. */
function ecp_wh_has_any(int $identityId): bool
{
    return ecp_wh_list($identityId) !== [];
}

/**
 * The employee's answer.
 *
 * @param 'confirm'|'decline' $answer
 * @return array{ok: bool, error?: string, status?: string}
 */
function ecp_wh_respond(int $identityId, int $employmentId, string $answer): array
{
    $db = ecp_db();
    if (!$db) {
        return ['ok' => false, 'error' => 'unavailable'];
    }
    if (!in_array($answer, ['confirm', 'decline'], true)) {
        return ['ok' => false, 'error' => 'bad_action'];
    }
    $st = $db->prepare(
        "SELECT e.id, e.company_id, e.employee_no, e.link_status
           FROM corp_employments e
           JOIN corp_companies c ON c.id = e.company_id AND c.status = 'active'
          WHERE e.id = :id AND e.identity_id = :iid AND e.status <> 'terminated' LIMIT 1"
    );
    $st->execute(['id' => $employmentId, 'iid' => $identityId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    if ($row['link_status'] !== 'pending') {
        // Already answered: report the current state (double taps are harmless).
        return ['ok' => true, 'status' => (string) $row['link_status']];
    }

    $now = gmdate('Y-m-d H:i:s');
    $new = $answer === 'confirm' ? 'confirmed' : 'declined';
    $cid = (int) $row['company_id'];
    try {
        $db->beginTransaction();
        $up = $db->prepare(
            "UPDATE corp_employments SET link_status = :ls, link_confirmed_at = :lca, updated_at = :upd
              WHERE id = :id AND company_id = :cid AND identity_id = :iid AND link_status = 'pending'"
        );
        $up->execute([
            'ls' => $new, 'lca' => $new === 'confirmed' ? $now : null, 'upd' => $now,
            'id' => $employmentId, 'cid' => $cid, 'iid' => $identityId,
        ]);
        if ($up->rowCount() === 1) {
            $db->prepare(
                'INSERT INTO corp_employment_events (company_id, employment_id, event, from_json, to_json, effective_on, actor_type, actor_id, created_at)
                 VALUES (:cid, :eid, :ev, :fj, :tj, NULL, \'employee\', :aid, :t)'
            )->execute([
                'cid' => $cid, 'eid' => $employmentId, 'ev' => $new === 'confirmed' ? 'linked' : 'link_declined',
                'fj' => json_encode(['link_status' => 'pending']), 'tj' => json_encode(['link_status' => $new]),
                'aid' => $identityId, 't' => $now,
            ]);
            $db->prepare(
                'INSERT INTO corp_audit_log (company_id, actor_type, actor_id, action, entity_type, entity_id, details, ip, user_agent, created_at)
                 VALUES (:cid, \'employee\', :aid, :act, \'corp_employment\', :eid, :det, :ip, :ua, :t)'
            )->execute([
                'cid' => $cid, 'aid' => $identityId, 'act' => $new === 'confirmed' ? 'employee_link_confirmed' : 'employee_link_declined',
                'eid' => $employmentId, 'det' => json_encode(['employee_no' => $row['employee_no']]),
                'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                'ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null, 't' => $now,
            ]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[work_health] respond: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'server_error'];
    }

    return ['ok' => true, 'status' => $new];
}

/**
 * The same rows shaped for JSON (web + mobile). No internal ids except the
 * employment id the answer endpoint needs.
 *
 * @param array<string, mixed> $r
 * @return array<string, mixed>
 */
function ecp_wh_shape(array $r): array
{
    return [
        'id' => (int) $r['id'],
        'company' => (string) $r['company_name'],
        'employee_no' => (string) $r['employee_no'],
        'name_on_record' => (string) $r['full_name'],
        'job_title' => $r['job_title'] !== null ? (string) $r['job_title'] : null,
        'department' => $r['department_name'] !== null ? (string) $r['department_name'] : null,
        'branch' => $r['branch_name'] !== null ? (string) $r['branch_name'] : null,
        'branch_city' => $r['branch_city'] !== null ? (string) $r['branch_city'] : null,
        'employment_type' => (string) $r['employment_type'],
        'contractor_company' => $r['contractor_company'] !== null ? (string) $r['contractor_company'] : null,
        'status' => (string) $r['link_status'],            // pending | confirmed
        'confirmed_at_utc' => $r['link_confirmed_at'] !== null ? (string) $r['link_confirmed_at'] : null,
        'on_leave' => $r['status'] === 'on_leave',
        'upcoming_bookings' => array_map(static fn (array $b): array => [
            'clinic' => (string) $b['clinic_name'],
            'address' => $b['clinic_address'] !== null ? (string) $b['clinic_address'] : null,
            'phone' => $b['clinic_phone'] !== null ? (string) $b['clinic_phone'] : null,
            'at_local' => (string) $b['scheduled_at'],          // clinic's local time, Y-m-d H:i:s
            'checks' => $b['checks'] !== null ? (string) $b['checks'] : '',
        ], $r['bookings'] ?? []),
    ];
}
