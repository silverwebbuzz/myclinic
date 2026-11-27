<?php
// =====================================================================
// partials/wellness_alerts.php — "Email me new free camps / events in my
// city" (Wellness Hub Phase 5). EMAIL ONLY — no WhatsApp / SMS promises.
// The daily sender is app/workers/wellness_camp_alerts.php.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ecp_wa_enabled(): bool
{
    static $on = null;
    if ($on === null) {
        $db = ecp_db();
        try {
            $on = $db && $db->query("SHOW TABLES LIKE 'wh_camp_alerts'")->fetchColumn() !== false;
        } catch (Throwable $e) {
            $on = false;
        }
    }
    return $on;
}

/** @return array<string,mixed>|null this person's active alert for a kind + city */
function ecp_wa_find(int $identityId, string $kind, string $citySlug): ?array
{
    $db = ecp_db();
    if (!$db || !ecp_wa_enabled()) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM wh_camp_alerts WHERE identity_id = :i AND kind = :k AND city_slug = :c AND is_active = 1 LIMIT 1');
    $st->execute(['i' => $identityId, 'k' => $kind, 'c' => $citySlug]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** @return list<array<string,mixed>> */
function ecp_wa_for_identity(int $identityId): array
{
    $db = ecp_db();
    if (!$db || !ecp_wa_enabled()) {
        return [];
    }
    $st = $db->prepare('SELECT * FROM wh_camp_alerts WHERE identity_id = :i AND is_active = 1 ORDER BY city_name, kind');
    $st->execute(['i' => $identityId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Subscribe. Uses the patient's email; if they have none, the given one is
 * saved to their profile.
 *
 * @param array<string,mixed> $identity patient_identities row
 * @return array{ok:bool,error?:string,email?:string}
 */
function ecp_wa_subscribe(array $identity, string $kind, string $citySlug, string $email): array
{
    $db = ecp_db();
    if (!$db || !ecp_wa_enabled()) {
        return ['ok' => false, 'error' => 'not_available'];
    }
    if (!in_array($kind, ['free_camp', 'event'], true) || !preg_match('/^[a-z0-9\-]{1,80}$/', $citySlug)) {
        return ['ok' => false, 'error' => 'bad_request'];
    }
    $cityName = null;
    foreach (['SELECT name FROM directory_cities WHERE slug = :s LIMIT 1', 'SELECT city_name FROM wh_events WHERE city_slug = :s AND city_name IS NOT NULL LIMIT 1'] as $sql) {
        $st = $db->prepare($sql);
        $st->execute(['s' => $citySlug]);
        $n = $st->fetchColumn();
        if ($n !== false) {
            $cityName = (string) $n;
            break;
        }
    }
    if ($cityName === null) {
        return ['ok' => false, 'error' => 'bad_city'];
    }
    $to = trim((string) ($identity['email'] ?? ''));
    if ($to === '') {
        $to = strtolower(trim($email));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'email_required'];
        }
        $db->prepare("UPDATE patient_identities SET email = :e WHERE id = :id AND (email IS NULL OR email = '')")
           ->execute(['e' => $to, 'id' => (int) $identity['id']]);
    }
    $db->prepare(
        'INSERT INTO wh_camp_alerts (identity_id, kind, city_slug, city_name, email, token)
         VALUES (:i, :k, :c, :n, :e, :t)
         ON DUPLICATE KEY UPDATE is_active = 1, email = VALUES(email), city_name = VALUES(city_name)'
    )->execute(['i' => (int) $identity['id'], 'k' => $kind, 'c' => $citySlug, 'n' => $cityName, 'e' => mb_substr($to, 0, 160), 't' => bin2hex(random_bytes(16))]);
    return ['ok' => true, 'email' => $to];
}

function ecp_wa_unsubscribe(int $identityId, int $alertId): bool
{
    $db = ecp_db();
    if (!$db || !ecp_wa_enabled()) {
        return false;
    }
    $st = $db->prepare('UPDATE wh_camp_alerts SET is_active = 0 WHERE id = :a AND identity_id = :i');
    $st->execute(['a' => $alertId, 'i' => $identityId]);
    return $st->rowCount() === 1;
}

/** One-click unsubscribe from the email link. @return array<string,mixed>|null the alert */
function ecp_wa_unsubscribe_token(string $token): ?array
{
    $db = ecp_db();
    if (!$db || !ecp_wa_enabled() || !preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM wh_camp_alerts WHERE token = :t LIMIT 1');
    $st->execute(['t' => $token]);
    $a = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($a) {
        $db->prepare('UPDATE wh_camp_alerts SET is_active = 0 WHERE id = :id')->execute(['id' => (int) $a['id']]);
    }
    return $a;
}
