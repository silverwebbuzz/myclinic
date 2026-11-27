<?php
// =====================================================================
// partials/wellness_events.php — public data layer for Wellness Hub
// camps & events (Phase 2). Writes go through the app (Creator Studio /
// admin); the only writes here are view counters and visitor reports.
//
// Public = status published|cancelled, creator active, not deleted.
// Lists show upcoming only (end_at >= now) and leave out cancelled ones.
// Contact phone, registration and online links are for signed-in patients.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

const ECP_WE_PER_PAGE = 24;
const ECP_WE_KINDS = [
    'free_camp' => ['label' => 'Free health camp', 'plural' => 'Free health camps', 'path' => 'camps', 'one' => 'camp'],
    'event'     => ['label' => 'Health event',     'plural' => 'Health events',     'path' => 'events', 'one' => 'event'],
];
const ECP_WE_AUDIENCE = ['all' => 'Everyone', 'women' => 'Women', 'children' => 'Children', 'seniors' => 'Senior citizens', 'doctors' => 'Doctors only'];
const ECP_WE_REPORT_REASONS = [
    'fake' => 'Looks fake or spam', 'hidden_fees' => 'It charges money', 'medical_claim' => 'Makes medical claims',
    'wrong_details' => 'Wrong date / place / details', 'copyright' => 'Uses my content without permission', 'other' => 'Something else',
];

/** kind from a URL path word (camps / camp / events / event). */
function ecp_we_kind_from_path(string $word): ?string
{
    return match ($word) {
        'camps', 'camp' => 'free_camp',
        'events', 'event' => 'event',
        default => null,
    };
}

/** @return array<string,string> slug => name */
function ecp_we_categories(string $kind): array
{
    static $cache = [];
    if (isset($cache[$kind])) {
        return $cache[$kind];
    }
    $db = ecp_db();
    if (!$db) {
        return [];
    }
    try {
        $st = $db->prepare('SELECT slug, name FROM wh_event_categories WHERE kind = :k AND is_active = 1 ORDER BY sort, name');
        $st->execute(['k' => $kind]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(string) $r['slug']] = (string) $r['name'];
        }
        return $cache[$kind] = $out;
    } catch (Throwable $e) {
        return $cache[$kind] = [];
    }
}

/** Display name for a city slug (directory city, else from a listing). */
function ecp_we_city_name(string $slug): ?string
{
    $db = ecp_db();
    if (!$db || !preg_match('/^[a-z0-9\-]{1,80}$/', $slug)) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT name FROM directory_cities WHERE slug = :s LIMIT 1');
        $st->execute(['s' => $slug]);
        $n = $st->fetchColumn();
        if ($n !== false) {
            return (string) $n;
        }
        $st = $db->prepare('SELECT city_name FROM wh_events WHERE city_slug = :s AND city_name IS NOT NULL LIMIT 1');
        $st->execute(['s' => $slug]);
        $n = $st->fetchColumn();
        return $n !== false ? (string) $n : null;
    } catch (Throwable $e) {
        return null;
    }
}

/** SQL fragment: publicly visible listing (alias e, creator alias w). */
function ecp_we_public_sql(): string
{
    return "e.is_deleted = 0 AND w.status = 'active'";
}

/**
 * Upcoming listings.
 *
 * @param array{kind?:string,city?:string,category?:string,when?:string,free?:bool,online?:bool,page?:int,limit?:int,exclude_id?:int,creator_id?:int} $f
 * @return array{rows:list<array<string,mixed>>,total:int}
 */
function ecp_we_list(array $f): array
{
    $db = ecp_db();
    if (!$db) {
        return ['rows' => [], 'total' => 0];
    }
    $where = [ecp_we_public_sql(), "e.status = 'published'", 'e.end_at >= NOW()'];
    $bind = [];
    if (!empty($f['kind']) && isset(ECP_WE_KINDS[$f['kind']])) {
        $where[] = 'e.kind = :kind';
        $bind['kind'] = $f['kind'];
    }
    if (!empty($f['city'])) {
        $where[] = 'e.city_slug = :city';
        $bind['city'] = $f['city'];
    }
    if (!empty($f['category'])) {
        $where[] = 'e.category_slug = :cat';
        $bind['cat'] = $f['category'];
    }
    if (!empty($f['free'])) {
        $where[] = 'e.fee_amount = 0';
    }
    if (!empty($f['online'])) {
        $where[] = "e.mode IN ('online','hybrid')";
    }
    if (!empty($f['exclude_id'])) {
        $where[] = 'e.id <> :ex';
        $bind['ex'] = (int) $f['exclude_id'];
    }
    if (!empty($f['creator_id'])) {
        $where[] = 'e.creator_id = :cr';
        $bind['cr'] = (int) $f['creator_id'];
    }
    switch ($f['when'] ?? '') {
        case 'today':
            $where[] = 'e.start_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
            break;
        case 'week':
            $where[] = 'e.start_at < DATE_ADD(CURDATE(), INTERVAL 8 DAY)';
            break;
        case 'month':
            $where[] = 'e.start_at < DATE_ADD(CURDATE(), INTERVAL 31 DAY)';
            break;
    }
    $limit = max(1, min(60, (int) ($f['limit'] ?? ECP_WE_PER_PAGE)));
    $page = max(1, (int) ($f['page'] ?? 1));
    $sqlWhere = implode(' AND ', $where);
    try {
        $c = $db->prepare("SELECT COUNT(*) FROM wh_events e JOIN wh_creators w ON w.id = e.creator_id WHERE $sqlWhere");
        $c->execute($bind);
        $total = (int) $c->fetchColumn();
        $st = $db->prepare(
            "SELECT e.id, e.kind, e.category_slug, e.title, e.slug, e.summary, e.poster_path, e.start_at, e.end_at, e.mode,
                    e.venue_name, e.city_name, e.city_slug, e.fee_amount, e.audience, e.organised_by,
                    w.display_name AS creator_name, w.creator_type, w.verify_status, w.slug AS creator_slug
             FROM wh_events e JOIN wh_creators w ON w.id = e.creator_id
             WHERE $sqlWhere
             ORDER BY e.start_at ASC, e.id ASC
             LIMIT $limit OFFSET " . (($page - 1) * $limit)
        );
        $st->execute($bind);
        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total];
    } catch (Throwable $e) {
        error_log('[wellness list] ' . $e->getMessage());
        return ['rows' => [], 'total' => 0];   // patch not run yet
    }
}

/** Cities that have upcoming listings of this kind. @return list<array{slug:string,name:string,n:int}> */
function ecp_we_cities(string $kind): array
{
    $db = ecp_db();
    if (!$db) {
        return [];
    }
    try {
        $st = $db->prepare(
            "SELECT e.city_slug AS slug, MAX(e.city_name) AS name, COUNT(*) AS n
             FROM wh_events e JOIN wh_creators w ON w.id = e.creator_id
             WHERE " . ecp_we_public_sql() . " AND e.status = 'published' AND e.end_at >= NOW()
               AND e.kind = :k AND e.city_slug IS NOT NULL
             GROUP BY e.city_slug ORDER BY n DESC, name ASC"
        );
        $st->execute(['k' => $kind]);
        return array_map(static fn ($r) => ['slug' => (string) $r['slug'], 'name' => (string) $r['name'], 'n' => (int) $r['n']], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

/** One public listing (any date; cancelled shown with a banner). @return array<string,mixed>|null */
function ecp_we_find(string $kind, string $slug): ?array
{
    $db = ecp_db();
    if (!$db || !preg_match('/^[a-z0-9\-]{1,200}$/', $slug)) {
        return null;
    }
    try {
        $st = $db->prepare(
            "SELECT e.*, w.display_name AS creator_name, w.creator_type, w.verify_status, w.slug AS creator_slug,
                    w.headline AS creator_headline, w.org_name AS creator_org, w.avatar_path AS creator_avatar,
                    w.instagram_handle AS creator_instagram, w.website_url AS creator_website, w.specialty AS creator_specialty,
                    w.med_qualification AS creator_qualification
             FROM wh_events e JOIN wh_creators w ON w.id = e.creator_id
             WHERE e.slug = :s AND e.kind = :k AND e.status IN ('published','cancelled') AND " . ecp_we_public_sql() . " LIMIT 1"
        );
        $st->execute(['s' => $slug, 'k' => $kind]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function ecp_we_count_view(int $id, bool $contact): void
{
    $db = ecp_db();
    if (!$db) {
        return;
    }
    try {
        $db->prepare('UPDATE wh_events SET view_count = view_count + 1' . ($contact ? ', contact_views = contact_views + 1' : '') . ' WHERE id = :id')
           ->execute(['id' => $id]);
    } catch (Throwable $e) {
    }
}

// ---------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------

function ecp_we_url(array $e): string
{
    return '/wellness/' . ECP_WE_KINDS[$e['kind']]['one'] . '/' . $e['slug'];
}

function ecp_we_poster_url(int $eventId): string
{
    return '/api/wellness_image?e=' . $eventId;
}

function ecp_we_avatar_url(string $creatorSlug): string
{
    return '/api/wellness_image?c=' . rawurlencode($creatorSlug);
}

/** "Sat, 14 Dec 2026 · 09:00 AM – 01:00 PM" */
function ecp_we_when(string $start, string $end): string
{
    $s = strtotime($start);
    $e = strtotime($end);
    if (date('Y-m-d', $s) === date('Y-m-d', $e)) {
        return date('D, d M Y', $s) . ' · ' . date('h:i A', $s) . ' – ' . date('h:i A', $e);
    }
    return date('d M Y, h:i A', $s) . ' – ' . date('d M Y, h:i A', $e);
}

function ecp_we_badge(array $e): ?string
{
    if (($e['verify_status'] ?? '') !== 'verified') {
        return null;
    }
    if (($e['creator_type'] ?? '') === 'platform') {
        return null;
    }
    return ($e['creator_type'] ?? '') === 'doctor' ? 'Verified Doctor' : 'Verified Organisation';
}

/** Who to show as organiser: "on behalf" name for eClinicPro Desk listings. */
function ecp_we_organiser(array $e): string
{
    return (string) (($e['creator_type'] ?? '') === 'platform' && !empty($e['organised_by']) ? $e['organised_by'] : $e['creator_name']);
}

function ecp_we_fee_label(array $e): string
{
    return (float) $e['fee_amount'] > 0 ? '₹' . number_format((float) $e['fee_amount'], (float) $e['fee_amount'] == floor((float) $e['fee_amount']) ? 0 : 2) : 'Free';
}

/** schema.org Event (no phone — that stays behind sign-in). */
function ecp_we_json_ld(array $e): array
{
    $url = ecp_site_url(ecp_we_url($e));
    $tz = '+05:30';
    $online = $e['mode'] !== 'in_person';
    $place = array_filter([
        '@type' => 'Place',
        'name' => $e['venue_name'] ?: ($e['city_name'] ?: null),
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $e['address'] ?: null,
            'addressLocality' => $e['city_name'] ?: null,
            'addressRegion' => $e['state'] ?: null,
            'postalCode' => $e['pincode'] ?: null,
            'addressCountry' => 'IN',
        ]),
    ]);
    $location = match ($e['mode']) {
        'online' => ['@type' => 'VirtualLocation', 'url' => $url],
        'hybrid' => [$place, ['@type' => 'VirtualLocation', 'url' => $url]],
        default => $place,
    };
    $organiser = ecp_we_organiser($e);

    return array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $e['title'],
        'description' => $e['summary'] ?: mb_substr((string) $e['description'], 0, 300),
        'startDate' => date('Y-m-d\TH:i:s', strtotime((string) $e['start_at'])) . $tz,
        'endDate' => date('Y-m-d\TH:i:s', strtotime((string) $e['end_at'])) . $tz,
        'eventStatus' => $e['status'] === 'cancelled' ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => $e['mode'] === 'online' ? 'https://schema.org/OnlineEventAttendanceMode'
            : ($online ? 'https://schema.org/MixedEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode'),
        'location' => $location,
        'image' => !empty($e['poster_path']) ? [ecp_site_url(ecp_we_poster_url((int) $e['id']))] : null,
        'isAccessibleForFree' => (float) $e['fee_amount'] <= 0,
        'offers' => [
            '@type' => 'Offer',
            'price' => number_format((float) $e['fee_amount'], 2, '.', ''),
            'priceCurrency' => 'INR',
            'availability' => 'https://schema.org/InStock',
            'url' => $url,
            'validFrom' => date('Y-m-d\TH:i:s', strtotime((string) ($e['published_at'] ?: $e['created_at']))) . $tz,
        ],
        'organizer' => ['@type' => ($e['creator_type'] ?? '') === 'individual' || ($e['creator_type'] ?? '') === 'doctor' ? 'Person' : 'Organization', 'name' => $organiser],
        'url' => $url,
    ], static fn ($v) => $v !== null && $v !== []);
}

// ---------------------------------------------------------------------
// Reports
// ---------------------------------------------------------------------

/** @return array{ok:bool,error?:string} */
function ecp_we_report(int $eventId, string $reason, string $details, ?int $identityId, string $ip): array
{
    $db = ecp_db();
    if (!$db) {
        return ['ok' => false, 'error' => 'server_error'];
    }
    if (!isset(ECP_WE_REPORT_REASONS[$reason])) {
        return ['ok' => false, 'error' => 'bad_reason'];
    }
    try {
        $st = $db->prepare("SELECT id FROM wh_events WHERE id = :id AND status IN ('published','cancelled') AND is_deleted = 0 LIMIT 1");
        $st->execute(['id' => $eventId]);
        if ($st->fetchColumn() === false) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        // At most 5 reports an hour from one address.
        $st = $db->prepare('SELECT COUNT(*) FROM wh_item_reports WHERE ip = :ip AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        $st->execute(['ip' => $ip]);
        if ((int) $st->fetchColumn() >= 5) {
            return ['ok' => false, 'error' => 'too_many'];
        }
        $details = trim(preg_replace('/[^\P{C}\n]+/u', '', $details) ?? '');
        $db->prepare(
            "INSERT INTO wh_item_reports (item_type, item_id, identity_id, reason, details, ip) VALUES ('event', :id, :iid, :r, :d, :ip)"
        )->execute([
            'id' => $eventId, 'iid' => $identityId, 'r' => $reason,
            'd' => $details !== '' ? mb_substr($details, 0, 1000) : null, 'ip' => $ip,
        ]);
        return ['ok' => true];
    } catch (Throwable $e) {
        error_log('[wellness report] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'server_error'];
    }
}
