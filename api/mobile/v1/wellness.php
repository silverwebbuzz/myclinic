<?php
// =====================================================================
// api/mobile/v1/wellness.php — Wellness Hub for the patient app
// (camps, events, creators, search, saved, comments & Q/A, camp alerts).
//
// Thin adapter over the SAME partials the website uses
// (wellness_events.php, health_tips.php, wellness_comments.php,
// wellness_alerts.php), so the app always matches /wellness.
// Recipes / exercises / tips / videos stay in health_tips.php.
// Reading is public; "Bearer" = Authorization: Bearer <patient session token>.
//
//   GET  ?action=home[&city=]                        hub: camps strip, latest per section, events, creators
//   GET  ?action=events&kind=free_camp|event[&city=&category=&when=today|week|month&free=1&online=1&page=]
//   GET  ?action=event&kind=free_camp|event&slug=    detail (contact only when signed in: contact_locked)
//   GET  ?action=cities&kind=                        cities with upcoming listings
//   GET  ?action=categories&kind=
//   GET  ?action=creator&slug=                       creator profile + posts + upcoming listings
//   GET  ?action=search&q=                           posts + upcoming camps / events
//   GET  ?action=saved                               Bearer — saved posts + camps / events + alerts
//   POST ?action=toggle_save_event { id }            Bearer
//   POST ?action=report_event { id, reason, details? }
//   GET  ?action=comments&item_type=tip|event&item_id=
//   POST ?action=comment { item_type, item_id, kind:comment|question, body, parent_id? }   Bearer
//   POST ?action=comment_report { id, reason }       Bearer
//   POST ?action=comment_delete { id }               Bearer (own)
//   GET  ?action=alerts                              Bearer
//   POST ?action=alert_subscribe { kind, city, email? }   Bearer (email only)
//   POST ?action=alert_unsubscribe { id }            Bearer
//
// Doc: ~/appmycliflut/document/14-wellness-api-reference.md
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../partials/helpers.php';
require_once __DIR__ . '/../../../partials/health_tips.php';
require_once __DIR__ . '/../../../partials/wellness_events.php';
require_once __DIR__ . '/../../../partials/wellness_comments.php';
require_once __DIR__ . '/../../../partials/wellness_alerts.php';

const ECP_MW_DISCLAIMER_EVENT = 'Camps and events are listed by independent organisers. eClinicPro only lists them and is not responsible for the services, fees or treatment provided there. Information here is general and not medical advice.';
const ECP_MW_DISCLAIMER_QA = 'Answers here are general information, not medical advice. For your own symptoms or medicines, please talk to a doctor.';

$action = (string) ($_GET['action'] ?? 'home');
$kindOf = static fn (): string => ($_GET['kind'] ?? 'free_camp') === 'event' ? 'event' : 'free_camp';
$db = ecp_db();
if (!$db) {
    ecp_m_err('db_unavailable', 503);
}

switch ($action) {

    case 'home': {
        ecp_m_require_method('GET');
        $cities = ecp_we_cities('free_camp');
        $city = (string) ($_GET['city'] ?? '');
        $city = in_array($city, array_column($cities, 'slug'), true) ? $city : '';
        $sections = [];
        foreach (ECP_HT_TYPES as $t => $m) {
            $rows = ecp_ht_public_list(['type' => $t, 'limit' => 6])['rows'];
            if ($rows) {
                $sections[] = ['type' => $t, 'title' => $m['plural'], 'items' => array_map('ecp_mw_tip_card', $rows)];
            }
        }
        ecp_m_ok([
            'camp_cities' => $cities,
            'city' => $city ?: null,
            'camps' => array_map('ecp_mw_event_card', ecp_we_list(['kind' => 'free_camp', 'city' => $city, 'limit' => 6])['rows']),
            'events' => array_map('ecp_mw_event_card', ecp_we_list(['kind' => 'event', 'limit' => 6])['rows']),
            'sections' => $sections,
            'creators' => ecp_mw_creators(10),
            'become_creator_url' => ecp_portal_url('/creator/login'),
        ]);
        break;
    }

    case 'events': {
        ecp_m_require_method('GET');
        $kind = $kindOf();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $cat = (string) ($_GET['category'] ?? '');
        $res = ecp_we_list([
            'kind' => $kind,
            'city' => preg_match('/^[a-z0-9\-]{1,80}$/', (string) ($_GET['city'] ?? '')) ? (string) $_GET['city'] : '',
            'category' => isset(ecp_we_categories($kind)[$cat]) ? $cat : '',
            'when' => in_array($_GET['when'] ?? '', ['today', 'week', 'month'], true) ? (string) $_GET['when'] : '',
            'free' => !empty($_GET['free']),
            'online' => !empty($_GET['online']),
            'page' => $page,
        ]);
        ecp_m_ok([
            'items' => array_map('ecp_mw_event_card', $res['rows']),
            'total' => $res['total'], 'page' => $page, 'per_page' => ECP_WE_PER_PAGE,
            'has_more' => $page * ECP_WE_PER_PAGE < $res['total'],
        ]);
        break;
    }

    case 'event': {
        ecp_m_require_method('GET');
        $kind = $kindOf();
        $e = ecp_we_find($kind, strtolower(trim((string) ($_GET['slug'] ?? ''))));
        if ($e === null) {
            ecp_m_err('not_found', 404);
        }
        $me = ecp_m_current();
        $ended = strtotime((string) $e['end_at']) < time();
        $cancelled = $e['status'] === 'cancelled';
        $open = $me !== null && !$ended && !$cancelled;
        ecp_we_count_view((int) $e['id'], $open);
        $alert = $me && $e['city_slug'] ? ecp_wa_find((int) $me['id'], $kind, (string) $e['city_slug']) : null;
        ecp_m_ok(['event' => ecp_mw_event_card($e) + [
            'summary' => $e['summary'],
            'description' => $e['description'],
            'status' => $cancelled ? 'cancelled' : ($ended ? 'ended' : 'upcoming'),
            'cancel_reason' => $cancelled ? $e['cancel_reason'] : null,
            'end_at' => date('c', strtotime((string) $e['end_at'])),
            'when_text' => ecp_we_when((string) $e['start_at'], (string) $e['end_at']),
            'address' => $e['mode'] !== 'online' ? array_filter([
                'venue' => $e['venue_name'], 'address' => $e['address'], 'city' => $e['city_name'],
                'state' => $e['state'], 'pincode' => $e['pincode'], 'map_url' => $e['map_url'],
            ]) : null,
            'fee_note' => $e['fee_note'],
            'audience' => $e['audience'],
            'audience_label' => ECP_WE_AUDIENCE[$e['audience']] ?? 'Everyone',
            'languages' => $e['languages'],
            'join_mode' => $e['join_mode'],
            // Signed-in patients only (same rule as the website).
            'contact_locked' => !$open,
            'contact' => $open ? [
                'name' => $e['contact_name'], 'phone' => $e['contact_phone'],
                'register_url' => $e['register_url'], 'online_url' => $e['online_url'],
            ] : null,
            'organiser' => [
                'name' => ecp_we_organiser($e),
                'creator_slug' => $e['creator_type'] !== 'platform' ? $e['creator_slug'] : null,
                'badge' => ecp_we_badge($e),
                'headline' => $e['creator_headline'],
                'qualification' => ecp_we_badge($e) === 'Verified Doctor' ? $e['creator_qualification'] : null,
                'avatar_url' => !empty($e['creator_avatar']) && $e['creator_type'] !== 'platform' ? ecp_site_url(ecp_we_avatar_url((string) $e['creator_slug'])) : null,
                'listed_by_eclinicpro' => $e['creator_type'] === 'platform',
            ],
            'saved' => $me !== null && ecp_we_is_saved((int) $me['id'], (int) $e['id']),
            'alert' => $e['city_slug'] ? ['kind' => $kind, 'city' => $e['city_slug'], 'city_name' => $e['city_name'], 'subscribed' => $alert !== null, 'id' => $alert ? (int) $alert['id'] : null] : null,
            'comments' => ['item_type' => 'event', 'item_id' => (int) $e['id'], 'count' => wh_c_count($db, 'event', (int) $e['id'])],
            'report_reasons' => ECP_WE_REPORT_REASONS,
            'disclaimer' => ECP_MW_DISCLAIMER_EVENT,
        ]]);
        break;
    }

    case 'cities': {
        ecp_m_require_method('GET');
        ecp_m_ok(['cities' => ecp_we_cities($kindOf())]);
        break;
    }

    case 'categories': {
        ecp_m_require_method('GET');
        $out = [];
        foreach (ecp_we_categories($kindOf()) as $slug => $name) {
            $out[] = ['slug' => $slug, 'name' => $name];
        }
        ecp_m_ok(['categories' => $out]);
        break;
    }

    case 'creator': {
        ecp_m_require_method('GET');
        $slug = strtolower((string) ($_GET['slug'] ?? ''));
        $st = $db->prepare("SELECT * FROM wh_creators WHERE slug = :s AND status = 'active' LIMIT 1");
        $st->execute(['s' => $slug]);
        $c = preg_match('/^[a-z0-9\-]{1,80}$/', $slug) ? ($st->fetch(PDO::FETCH_ASSOC) ?: null) : null;
        if ($c === null) {
            ecp_m_err('not_found', 404);
        }
        $badge = ecp_mw_badge($c);
        $tips = !empty($c['legacy_contributor_id']) ? ecp_ht_public_list(['contributor_id' => (int) $c['legacy_contributor_id'], 'limit' => 48])['rows'] : [];
        ecp_m_ok(['creator' => [
            'slug' => (string) $c['slug'],
            'name' => (string) $c['display_name'],
            'type' => (string) $c['creator_type'],
            'badge' => $badge,
            'qualification' => $badge === 'Verified Doctor' ? $c['med_qualification'] : null,
            'specialty' => $badge === 'Verified Doctor' ? $c['specialty'] : null,
            'org_name' => $c['org_name'],
            'city' => $c['city'],
            'headline' => $c['headline'],
            'bio' => $c['bio'],
            'avatar_url' => !empty($c['avatar_path']) ? ecp_site_url(ecp_we_avatar_url((string) $c['slug'])) : null,
            'links' => array_filter(['instagram' => $c['instagram_handle'] ? 'https://www.instagram.com/' . $c['instagram_handle'] . '/' : null, 'youtube' => $c['youtube_url'], 'website' => $c['website_url']]),
            'web_url' => ecp_site_url('/wellness/creators/' . $c['slug']),
            'posts' => array_map('ecp_mw_tip_card', $tips),
            'events' => array_map('ecp_mw_event_card', ecp_we_list(['creator_id' => (int) $c['id'], 'limit' => 24])['rows']),
        ]]);
        break;
    }

    case 'search': {
        ecp_m_require_method('GET');
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        if ($q === '') {
            ecp_m_ok(['q' => '', 'posts' => [], 'events' => []]);
        }
        ecp_m_ok([
            'q' => $q,
            'posts' => array_map('ecp_mw_tip_card', ecp_ht_public_list(['q' => $q, 'limit' => 24])['rows']),
            'events' => array_map('ecp_mw_event_card', ecp_we_search($q, 12)),
        ]);
        break;
    }

    case 'saved': {
        ecp_m_require_method('GET');
        $me = ecp_m_require_patient();
        ecp_m_ok([
            'posts' => array_map('ecp_mw_tip_card', ecp_ht_public_list(['saved_by' => (int) $me['id'], 'limit' => 60])['rows']),
            'events' => array_map('ecp_mw_event_card', ecp_we_saved((int) $me['id'])),
            'alerts' => array_map('ecp_mw_alert', ecp_wa_for_identity((int) $me['id'])),
        ]);
        break;
    }

    case 'toggle_save_event': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        $res = ecp_we_toggle_save((int) $me['id'], (int) (ecp_m_input()['id'] ?? 0));
        if (!$res['ok']) {
            ecp_m_err((string) $res['error'], $res['error'] === 'not_found' ? 404 : 503);
        }
        ecp_m_ok(['saved' => $res['saved']]);
        break;
    }

    case 'report_event': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        $me = ecp_m_current();
        $res = ecp_we_report((int) ($in['id'] ?? 0), (string) ($in['reason'] ?? ''), (string) ($in['details'] ?? ''), $me ? (int) $me['id'] : null, substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
        if (!$res['ok']) {
            ecp_m_err((string) $res['error'], match ($res['error']) { 'not_found' => 404, 'too_many' => 429, 'bad_reason' => 400, default => 500 });
        }
        ecp_m_ok();
        break;
    }

    case 'comments': {
        ecp_m_require_method('GET');
        $type = (string) ($_GET['item_type'] ?? '');
        if (!in_array($type, ['tip', 'event'], true)) {
            ecp_m_err('bad_request', 400);
        }
        $me = ecp_m_current();
        $id = (int) ($_GET['item_id'] ?? 0);
        $thread = wh_c_thread($db, $type, $id, $me ? (int) $me['id'] : null);
        ecp_m_ok([
            'enabled' => wh_c_enabled($db),
            'count' => wh_c_count($db, $type, $id),
            'items' => array_map(static fn ($c) => ecp_mw_comment($c, $me) + ['replies' => array_map(static fn ($r) => ecp_mw_comment($r, $me), $c['replies'])], $thread),
            'report_reasons' => WH_C_REPORT_REASONS,
            'disclaimer' => ECP_MW_DISCLAIMER_QA,
            'find_doctor_url' => ecp_site_url('/find-a-doctor'),
        ]);
        break;
    }

    case 'comment': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        $in = ecp_m_input();
        $type = (string) ($in['item_type'] ?? '');
        if (!in_array($type, ['tip', 'event'], true)) {
            ecp_m_err('bad_request', 400);
        }
        $res = wh_c_post($db, (int) $me['id'], $type, (int) ($in['item_id'] ?? 0), (string) ($in['kind'] ?? 'comment'),
            (string) ($in['body'] ?? ''), !empty($in['parent_id']) ? (int) $in['parent_id'] : null);
        if (!$res['ok']) {
            ecp_m_err('invalid', 422, ['message' => $res['error']]);
        }
        ecp_mw_comment_mail($res['notify'] ?? []);
        ecp_m_ok(['id' => $res['id'], 'held' => $res['status'] === 'held']);
        break;
    }

    case 'comment_report': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        $in = ecp_m_input();
        $res = wh_c_report($db, (int) ($in['id'] ?? 0), (int) $me['id'], (string) ($in['reason'] ?? ''));
        $res['ok'] ? ecp_m_ok() : ecp_m_err('invalid', 422, ['message' => $res['error']]);
        break;
    }

    case 'comment_delete': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        wh_c_delete_own($db, (int) (ecp_m_input()['id'] ?? 0), (int) $me['id']) ? ecp_m_ok() : ecp_m_err('not_found', 404);
        break;
    }

    case 'alerts': {
        ecp_m_require_method('GET');
        $me = ecp_m_require_patient();
        ecp_m_ok(['alerts' => array_map('ecp_mw_alert', ecp_wa_for_identity((int) $me['id'])), 'enabled' => ecp_wa_enabled()]);
        break;
    }

    case 'alert_subscribe': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        $in = ecp_m_input();
        $res = ecp_wa_subscribe($me, ($in['kind'] ?? '') === 'event' ? 'event' : 'free_camp', strtolower((string) ($in['city'] ?? '')), (string) ($in['email'] ?? ''));
        $res['ok'] ? ecp_m_ok(['email' => $res['email']]) : ecp_m_err((string) $res['error'], $res['error'] === 'not_available' ? 503 : 422);
        break;
    }

    case 'alert_unsubscribe': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        ecp_wa_unsubscribe((int) $me['id'], (int) (ecp_m_input()['id'] ?? 0)) ? ecp_m_ok() : ecp_m_err('not_found', 404);
        break;
    }

    default:
        ecp_m_err('unknown_action', 400);
}

// ---------------------------------------------------------------------
// Shapes
// ---------------------------------------------------------------------

function ecp_mw_event_card(array $r): array
{
    $cats = ecp_we_categories((string) $r['kind']);
    $s = strtotime((string) $r['start_at']);
    return [
        'id' => (int) $r['id'],
        'kind' => (string) $r['kind'],
        'slug' => (string) $r['slug'],
        'title' => (string) $r['title'],
        'category' => ['slug' => (string) $r['category_slug'], 'name' => $cats[$r['category_slug']] ?? ''],
        'start_at' => date('c', $s),
        'date_label' => date('D, d M Y', $s),
        'time_label' => date('h:i A', $s),
        'mode' => (string) $r['mode'],
        'venue' => $r['venue_name'] ?? null,
        'city' => $r['city_name'] ?? null,
        'city_slug' => $r['city_slug'] ?? null,
        'fee_amount' => (float) $r['fee_amount'],
        'fee_label' => ecp_we_fee_label($r),
        'poster_url' => !empty($r['poster_path']) ? ecp_site_url(ecp_we_poster_url((int) $r['id'])) : null,
        'organiser' => ecp_we_organiser($r),
        'organiser_badge' => ecp_we_badge($r),
        'web_url' => ecp_site_url(ecp_we_url($r)),
    ];
}

function ecp_mw_tip_card(array $r): array
{
    $video = ecp_ht_video_embed($r['video_url'] ?? null);
    return [
        'id' => (int) $r['id'],
        'slug' => (string) $r['slug'],
        'type' => (string) $r['type'],
        'type_label' => ECP_HT_TYPES[$r['type']]['label'] ?? '',
        'title' => (string) $r['title'],
        'summary' => $r['summary'] !== null ? (string) $r['summary'] : null,
        'cover_url' => $r['cover_id'] ? ecp_site_url(ecp_ht_image_url((int) $r['cover_id'])) : ($video['thumb'] ?? null),
        'is_video' => $r['type'] === 'video',
        'author' => ['name' => (string) $r['author_name'], 'creator_slug' => basename(ecp_ht_author_url($r)), 'badge' => ecp_ht_author_badge($r)],
        'save_count' => (int) ($r['save_count'] ?? 0),
        // Detail: health_tips.php?action=detail&slug= (send include_video=1 for videos)
        'web_url' => ecp_site_url(ecp_ht_post_url($r)),
    ];
}

/** @return list<array<string,mixed>> */
function ecp_mw_creators(int $limit): array
{
    $db = ecp_db();
    try {
        $rows = $db->query(
            "SELECT w.slug, w.display_name, w.headline, w.creator_type, w.verify_status, w.avatar_path, w.city,
                    (SELECT COUNT(*) FROM health_tips t WHERE t.contributor_id = w.legacy_contributor_id AND t.status = 'published' AND t.is_deleted = 0)
                  + (SELECT COUNT(*) FROM wh_events e WHERE e.creator_id = w.id AND e.status = 'published' AND e.is_deleted = 0 AND e.end_at >= NOW()) AS n
             FROM wh_creators w WHERE w.status = 'active' AND w.creator_type <> 'platform'
             HAVING n > 0 ORDER BY (w.verify_status = 'verified') DESC, n DESC LIMIT " . max(1, min(30, $limit))
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
    return array_map(static fn ($c) => [
        'slug' => (string) $c['slug'], 'name' => (string) $c['display_name'], 'headline' => $c['headline'],
        'type' => (string) $c['creator_type'], 'badge' => ecp_mw_badge($c), 'city' => $c['city'],
        'avatar_url' => !empty($c['avatar_path']) ? ecp_site_url(ecp_we_avatar_url((string) $c['slug'])) : null,
    ], $rows);
}

function ecp_mw_badge(array $c): ?string
{
    if (($c['verify_status'] ?? '') !== 'verified' || ($c['creator_type'] ?? '') === 'platform') {
        return null;
    }
    return ($c['creator_type'] ?? '') === 'doctor' ? 'Verified Doctor' : 'Verified Organisation';
}

function ecp_mw_comment(array $c, ?array $me): array
{
    $mine = $me !== null && (int) $c['identity_id'] === (int) $me['id'];
    return [
        'id' => (int) $c['id'],
        'kind' => (string) $c['kind'],
        'body' => (string) $c['body'],
        'author' => ['name' => $c['author']['name'], 'badge' => $c['author']['badge'], 'creator_slug' => $c['author']['creator_slug']],
        'is_creator_answer' => (int) $c['is_creator_answer'] === 1,
        'is_mine' => $mine,
        'pending_review' => $c['status'] === 'held',   // only ever true for the viewer's own
        'created_at' => date('c', strtotime((string) $c['created_at'])),
        'date_label' => date('d M Y', strtotime((string) $c['created_at'])),
    ];
}

function ecp_mw_alert(array $a): array
{
    return ['id' => (int) $a['id'], 'kind' => (string) $a['kind'], 'city' => (string) $a['city_slug'], 'city_name' => (string) $a['city_name'], 'email' => (string) $a['email']];
}

/** Same emails the website sends (partials/mailer.php). */
function ecp_mw_comment_mail(array $list): void
{
    if (!$list) {
        return;
    }
    try {
        require_once __DIR__ . '/../../../partials/mailer.php';
        foreach ($list as $n) {
            $url = ecp_site_url($n['path']);
            $quote = '<blockquote style="border-left:3px solid #0F9B6E;margin:12px 0;padding:4px 12px;color:#333;">' . nl2br(e($n['excerpt'])) . '</blockquote>';
            if ($n['type'] === 'new_question') {
                ecp_send_mail($n['email'], 'New question on “' . $n['title'] . '”', ecp_email_template('You have a new question',
                    '<p>Hi ' . e($n['name']) . ',</p><p>Someone asked a question on <strong>' . e($n['title']) . '</strong>:</p>' . $quote
                    . '<p><a href="' . e(ecp_portal_url('/creator/questions')) . '">Answer it in Creator Studio</a></p>'));
            } else {
                ecp_send_mail($n['email'], 'Your question was answered — ' . $n['title'], ecp_email_template('Your question was answered',
                    '<p>Hi ' . e($n['name']) . ',</p><p>There’s a reply to your question on <strong>' . e($n['title']) . '</strong>:</p>' . $quote
                    . '<p><a href="' . e($url) . '">See the answer</a></p><p style="color:#6E6E73;font-size:13px;">' . e(ECP_MW_DISCLAIMER_QA) . '</p>'));
            }
        }
    } catch (Throwable $e) {
        error_log('[mobile wellness mail] ' . $e->getMessage());
    }
}
