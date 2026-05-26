<?php
// =====================================================================
// directory_leads.php — server-side lead-capture pipeline.
//
// Public API:
//   ecp_lead_create($doctorId, $patientIdentity, $type, $extra)
//      → inserts a row, dispatches SMS if appropriate, returns row id + token
//
//   ecp_lead_dispatch_sms(int $leadId)
//      → idempotent SMS dispatcher (called inline by create() but also
//        safe to retry from a cron)
//
//   ecp_lead_for_token(string $token)
//      → loads the lead joined with doctor + patient identity, for the
//        public landing page
//
//   ecp_lead_settings()
//      → returns the row from directory_sms_settings (cached per request)
//
// Throttling layers:
//   1. Master toggle (settings.enabled)
//   2. Per-doctor pause (quotas.is_paused)
//   3. Quiet hours (settings.quiet_hours_*)
//   4. Per-doctor day/week/month caps
// Each suppression maps to a distinct sms_status so analytics can see
// where leads got blocked.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sms.php';

// ---------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------

function ecp_lead_settings(): array {
    static $cached = null;
    if ($cached !== null) return $cached;

    $db = ecp_db();
    if (!$db) return $cached = _lead_settings_fallback();
    try {
        $row = $db->query('SELECT * FROM directory_sms_settings WHERE id = 1')
                  ->fetch(PDO::FETCH_ASSOC);
        if (!$row) return $cached = _lead_settings_fallback();
        return $cached = $row;
    } catch (Throwable $e) {
        error_log('[lead settings] ' . $e->getMessage());
        return $cached = _lead_settings_fallback();
    }
}

function _lead_settings_fallback(): array {
    return [
        'id' => 1, 'enabled' => 1,
        'default_per_day' => 2, 'default_per_week' => 5, 'default_per_month' => 20,
        'provider_template_id' => null,
        'template_body' => 'eClinicPro: {patient_name} wants to book you {date} at {time}. View: {url} Reply STOP to opt out.',
        'quiet_hours_start' => '21:00:00', 'quiet_hours_end' => '08:00:00',
        'lead_landing_base' => 'https://eclinicpro.com/L/',
    ];
}

// ---------------------------------------------------------------------
// Create
// ---------------------------------------------------------------------

/**
 * Create a lead. The patient is assumed pre-authenticated; pass the
 * `patient_identities` row (or null for anonymous 'view' tracking).
 *
 * @param int        $doctorId         directory_doctors.id
 * @param array|null $patientIdentity  the row from patient_identities, or null
 * @param string     $type             'view' | 'book_intent' | 'book_submitted' | 'call'
 * @param array      $extra            ['preferred_date'=>'2026-05-20','preferred_time'=>'17:30','reason'=>'..']
 *
 * @return array{ok:bool, lead_id?:int, view_token?:string, sms_status?:string, error?:string}
 */
function ecp_lead_create(int $doctorId, ?array $patientIdentity, string $type, array $extra = []): array {
    if ($doctorId <= 0) return ['ok' => false, 'error' => 'invalid_doctor'];
    if (!in_array($type, ['view','book_intent','book_submitted','call'], true)) {
        return ['ok' => false, 'error' => 'invalid_type'];
    }
    if ($type === 'book_submitted' && !$patientIdentity) {
        return ['ok' => false, 'error' => 'login_required'];
    }

    $db = ecp_db();
    if (!$db) return ['ok' => false, 'error' => 'db_unavailable'];

    // Confirm doctor exists and is unclaimed (or admin override later).
    $stmt = $db->prepare('SELECT id, is_claimed, phone FROM directory_doctors WHERE id = :id AND is_active = 1');
    $stmt->execute(['id' => $doctorId]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doctor) return ['ok' => false, 'error' => 'doctor_not_found'];

    // Generate a short opaque token (used in the SMS link).
    $token = ($type === 'book_submitted') ? bin2hex(random_bytes(8)) : null;

    $identityId = $patientIdentity['id'] ?? null;
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    $ip = $ip ? substr(explode(',', (string) $ip)[0], 0, 45) : null;
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    // Whether SMS is even applicable for this lead.
    $smsStatus = ($type === 'book_submitted' && !$doctor['is_claimed'])
                  ? 'pending' : 'not_applicable';

    $ins = $db->prepare(
        'INSERT INTO directory_leads
            (directory_doctor_id, type, patient_identity_id, preferred_date,
             preferred_time, reason, view_token, source, referrer, ip, user_agent,
             sms_status)
         VALUES
            (:did, :type, :iid, :pd, :pt, :reason, :tok, :src, :ref, :ip, :ua, :ss)'
    );
    $ins->execute([
        'did'    => $doctorId,
        'type'   => $type,
        'iid'    => $identityId,
        'pd'     => $extra['preferred_date'] ?? null,
        'pt'     => $extra['preferred_time'] ?? null,
        'reason' => isset($extra['reason']) ? mb_substr((string) $extra['reason'], 0, 2000) : null,
        'tok'    => $token,
        'src'    => $extra['source'] ?? 'find-a-doctor',
        'ref'    => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 255) ?: null,
        'ip'     => $ip,
        'ua'     => $ua ?: null,
        'ss'     => $smsStatus,
    ]);
    $leadId = (int) $db->lastInsertId();

    // Dispatch SMS inline. If suppressed, the row's sms_status reflects why.
    $smsResult = ['status' => $smsStatus];
    if ($smsStatus === 'pending') {
        $smsResult = ecp_lead_dispatch_sms($leadId);
    }

    return [
        'ok'         => true,
        'lead_id'    => $leadId,
        'view_token' => $token,
        'sms_status' => $smsResult['status'] ?? $smsStatus,
    ];
}

// ---------------------------------------------------------------------
// SMS dispatch (idempotent — safe to retry)
// ---------------------------------------------------------------------

/**
 * @return array{status:string, sent?:bool, suppressed_by?:string, error?:string}
 */
function ecp_lead_dispatch_sms(int $leadId): array {
    $db = ecp_db();
    if (!$db) return ['status' => 'failed', 'error' => 'db_unavailable'];

    // Load lead + doctor + patient identity in one go.
    $stmt = $db->prepare(
        'SELECT dl.*, dd.id AS d_id, dd.phone AS d_phone, dd.name AS d_clinic,
                dd.doctor_name AS d_name, dd.is_claimed AS d_claimed,
                pi.name AS p_name, pi.first_name AS p_first, pi.phone AS p_phone
         FROM directory_leads dl
         JOIN directory_doctors dd ON dd.id = dl.directory_doctor_id
         LEFT JOIN patient_identities pi ON pi.id = dl.patient_identity_id
         WHERE dl.id = :id'
    );
    $stmt->execute(['id' => $leadId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lead)                            return ['status' => 'failed', 'error' => 'lead_not_found'];
    if ($lead['sms_sent_at'] !== null)     return ['status' => 'sent']; // idempotent
    if ((int) $lead['d_claimed'] === 1)    return _lead_mark_status($leadId, 'not_applicable');

    $settings = ecp_lead_settings();
    if ((int) $settings['enabled'] === 0)  return _lead_mark_status($leadId, 'suppressed_paused', 'master_disabled');

    // ----- Throttle check 1: per-doctor pause -----
    $quota = $db->prepare('SELECT * FROM directory_sms_quotas WHERE directory_doctor_id = :id');
    $quota->execute(['id' => $lead['d_id']]);
    $q = $quota->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!empty($q['is_paused'])) {
        return _lead_mark_status($leadId, 'suppressed_paused', 'doctor_paused');
    }

    $perDay   = $q['per_day']   ?? $settings['default_per_day'];
    $perWeek  = $q['per_week']  ?? $settings['default_per_week'];
    $perMonth = $q['per_month'] ?? $settings['default_per_month'];

    // ----- Throttle check 2: quiet hours -----
    if (_lead_in_quiet_hours((string) $settings['quiet_hours_start'], (string) $settings['quiet_hours_end'])) {
        return _lead_mark_status($leadId, 'suppressed_quiet', 'quiet_hours');
    }

    // ----- Throttle check 3: per-doctor day/week/month -----
    $cnt = $db->prepare(
        'SELECT
            SUM(sms_sent_at >= DATE_SUB(NOW(), INTERVAL 1 DAY))    AS d,
            SUM(sms_sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))    AS w,
            SUM(sms_sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))   AS m
         FROM directory_leads
         WHERE directory_doctor_id = :id AND sms_status = "sent"'
    );
    $cnt->execute(['id' => $lead['d_id']]);
    $c = $cnt->fetch(PDO::FETCH_ASSOC) ?: ['d' => 0, 'w' => 0, 'm' => 0];

    if ((int) $c['d'] >= (int) $perDay)   return _lead_mark_status($leadId, 'suppressed_quota', 'per_day');
    if ((int) $c['w'] >= (int) $perWeek)  return _lead_mark_status($leadId, 'suppressed_quota', 'per_week');
    if ((int) $c['m'] >= (int) $perMonth) return _lead_mark_status($leadId, 'suppressed_quota', 'per_month');

    // ----- All clear. Compose and send. -----
    if (!$lead['d_phone']) {
        return _lead_mark_status($leadId, 'failed', 'doctor_no_phone');
    }

    $patientFirst = $lead['p_first'] ?: explode(' ', (string) ($lead['p_name'] ?? 'A patient'))[0];
    $datePretty = $lead['preferred_date']
        ? date('D, j M', strtotime((string) $lead['preferred_date']))
        : 'soon';
    $timePretty = $lead['preferred_time']
        ? _lead_pretty_time((string) $lead['preferred_time'])
        : '';

    $url = rtrim((string) $settings['lead_landing_base'], '/') . '/' . $lead['view_token'];

    $body = str_replace(
        ['{patient_name}', '{date}', '{time}', '{url}', '{clinic}'],
        [$patientFirst, $datePretty, $timePretty, $url, (string) $lead['d_clinic']],
        (string) $settings['template_body']
    );

    $sent = _lead_send_freeform_sms($lead['d_phone'], $body);

    if (!$sent['ok']) {
        return _lead_mark_status($leadId, 'failed', $sent['error'] ?? 'sms_failed');
    }

    $upd = $db->prepare(
        'UPDATE directory_leads
         SET sms_sent_at = NOW(), sms_provider_id = :pid, sms_status = "sent"
         WHERE id = :id'
    );
    $upd->execute(['pid' => $sent['message_id'] ?? null, 'id' => $leadId]);

    return ['status' => 'sent', 'sent' => true];
}

function _lead_mark_status(int $leadId, string $status, ?string $note = null): array {
    $db = ecp_db();
    if (!$db) return ['status' => $status];
    $db->prepare('UPDATE directory_leads SET sms_status = :s WHERE id = :id')
       ->execute(['s' => $status, 'id' => $leadId]);
    return ['status' => $status, 'suppressed_by' => $note];
}

function _lead_in_quiet_hours(string $start, string $end): bool {
    $now = date('H:i:s');
    // Quiet window can wrap midnight (e.g. 21:00 → 08:00)
    if ($start <= $end) return ($now >= $start && $now < $end);
    return ($now >= $start || $now < $end);
}

function _lead_pretty_time(string $hhmm): string {
    if (!preg_match('/^(\d{1,2}):(\d{2})/', $hhmm, $m)) return $hhmm;
    $h = (int) $m[1]; $min = $m[2];
    $period = $h >= 12 ? 'PM' : 'AM';
    $h12    = $h === 0 ? 12 : ($h > 12 ? $h - 12 : $h);
    return $h12 . ':' . $min . ' ' . $period;
}

/**
 * Freeform SMS send — bypasses ecp_sms_send_otp's OTP-shaped payload.
 * Live mode: MSG91 flow API. Dev: log to storage/logs/lead-sms.log.
 */
function _lead_send_freeform_sms(string $phone, string $body): array {
    $mode = strtolower((string) ecp_env('ECP_SMS_MODE', 'dev'));
    $phone = ecp_normalize_phone($phone);
    if ($phone === '') return ['ok' => false, 'error' => 'invalid_phone'];

    if ($mode === 'dev') {
        $dir = __DIR__ . '/../storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents(
            $dir . '/lead-sms.log',
            sprintf("[%s] %s | %s\n", date('Y-m-d H:i:s'), $phone, $body),
            FILE_APPEND | LOCK_EX
        );
        return ['ok' => true, 'message_id' => 'dev_' . bin2hex(random_bytes(4))];
    }

    // Live: MSG91 transactional flow.
    $key = ecp_env('MSG91_AUTH_KEY');
    $flow = ecp_env('MSG91_LEAD_FLOW_ID');     // a DLT flow distinct from OTP
    if (!$key || !$flow) {
        error_log('[lead sms] MSG91_LEAD_FLOW_ID not set');
        return ['ok' => false, 'error' => 'sms_not_configured'];
    }
    $mobile = preg_replace('/^\+?/', '', $phone);

    $ch = curl_init('https://control.msg91.com/api/v5/flow/');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'accept: application/json',
            'authkey: ' . $key,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'template_id' => $flow,
            'short_url'   => '1',
            'recipients'  => [['mobiles' => $mobile, 'BODY' => $body]],
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $http >= 400) {
        error_log('[lead sms] msg91 http=' . $http . ' body=' . substr((string) $resp, 0, 300));
        return ['ok' => false, 'error' => 'sms_send_failed'];
    }
    $j = json_decode((string) $resp, true);
    $ok = ($j['type'] ?? '') === 'success';
    return [
        'ok'         => $ok,
        'message_id' => $j['request_id'] ?? null,
        'error'      => $ok ? null : ($j['message'] ?? 'sms_unknown_error'),
    ];
}

// ---------------------------------------------------------------------
// Landing page lookup
// ---------------------------------------------------------------------

function ecp_lead_for_token(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{16}$/', $token)) return null;
    $db = ecp_db();
    if (!$db) return null;

    $stmt = $db->prepare(
        'SELECT dl.*, dd.id AS doctor_id, dd.name AS clinic_name,
                dd.doctor_name, dd.area, dd.city, dd.state, dd.phone AS clinic_phone,
                dd.is_claimed,
                pi.name AS patient_name, pi.first_name AS patient_first_name,
                pi.phone AS patient_phone, pi.email AS patient_email
         FROM directory_leads dl
         JOIN directory_doctors dd ON dd.id = dl.directory_doctor_id
         LEFT JOIN patient_identities pi ON pi.id = dl.patient_identity_id
         WHERE dl.view_token = :t
         LIMIT 1'
    );
    $stmt->execute(['t' => $token]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Stamp doctor_viewed_at + bump doctor_view_count for landing-page hits.
 * Idempotent — only sets viewed_at on the first call.
 */
function ecp_lead_mark_doctor_viewed(int $leadId): void {
    $db = ecp_db();
    if (!$db) return;
    $db->prepare(
        'UPDATE directory_leads
         SET doctor_viewed_at = COALESCE(doctor_viewed_at, NOW()),
             doctor_view_count = doctor_view_count + 1
         WHERE id = :id'
    )->execute(['id' => $leadId]);
}

/**
 * Doctor self-paused — texted STOP to the SMS, or clicked Pause on the
 * landing page. Inserts a quota row with is_paused = 1.
 */
function ecp_lead_pause_doctor(int $doctorId, string $reason = 'doctor_request'): void {
    $db = ecp_db();
    if (!$db) return;
    $db->prepare(
        'INSERT INTO directory_sms_quotas (directory_doctor_id, is_paused, paused_at, pause_reason)
         VALUES (:id, 1, NOW(), :r)
         ON DUPLICATE KEY UPDATE is_paused = 1, paused_at = NOW(), pause_reason = :r'
    )->execute(['id' => $doctorId, 'r' => $reason]);
}
