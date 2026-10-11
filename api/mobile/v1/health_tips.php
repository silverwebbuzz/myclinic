<?php
// =====================================================================
// api/mobile/v1/health_tips.php — Health Tips feed for the patient app.
//
// Thin adapter over partials/health_tips.php (the same ecp_ht_* functions
// /health-tips uses), so the app shows exactly what the website shows:
// published posts by active contributors only. Reading is public; saving
// needs the Bearer token.
//
//   GET  ?action=list&type=&tag=&q=&page=  → { items:[card…], total, page, per_page, has_more }
//   GET  ?action=detail&slug=…             → { tip:{…} }      (saved = false when signed out)
//   GET  ?action=tags                      → { tags:[{slug,name,group}] }
//   GET  ?action=saved&page=               → { items:[card…], total, page, per_page, has_more }   Bearer
//   POST ?action=toggle_save { id }        → { saved, count }                                    Bearer
//
// Image URLs are absolute and public (api/health_tip_image.php serves only
// published posts), so the app can load them with a plain image widget.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../partials/helpers.php';
require_once __DIR__ . '/../../../partials/health_tips.php';

$action = (string) ($_GET['action'] ?? 'list');

switch ($action) {

    case 'list': {
        ecp_m_require_method('GET');
        $type = (string) ($_GET['type'] ?? '');
        $tag  = (string) ($_GET['tag'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $res = ecp_ht_public_list([
            'type' => isset(ECP_HT_TYPES[$type]) ? $type : '',
            'tag'  => preg_match('/^[a-z0-9\-]{1,60}$/', $tag) ? $tag : '',
            'q'    => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80),
            'page' => $page,
        ]);
        ecp_m_ok(ecp_mht_page($res, $page));
        break;
    }

    case 'saved': {
        ecp_m_require_method('GET');
        $me = ecp_m_require_patient();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $res = ecp_ht_public_list(['saved_by' => (int) $me['id'], 'page' => $page]);
        ecp_m_ok(ecp_mht_page($res, $page));
        break;
    }

    case 'detail': {
        ecp_m_require_method('GET');
        $slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
        $tip = preg_match('/^[a-z0-9\-]{1,200}$/', $slug) ? ecp_ht_find_by_slug($slug) : null;
        if ($tip === null) {
            ecp_m_err('not_found', 404);
        }
        ecp_ht_count_view((int) $tip['id']);
        $me = ecp_m_current();
        ecp_m_ok(['tip' => ecp_mht_detail($tip, $me ? (int) $me['id'] : 0)]);
        break;
    }

    case 'tags': {
        ecp_m_require_method('GET');
        $tags = array_map(static fn ($t) => [
            'slug'  => (string) $t['slug'],
            'name'  => (string) $t['name'],
            'group' => (string) $t['tag_group'],
        ], ecp_ht_tags());
        ecp_m_ok(['tags' => $tags]);
        break;
    }

    case 'toggle_save': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        if (!ecp_ht_saves_enabled()) {
            ecp_m_err('not_available', 503);
        }
        $in = ecp_m_input();
        $res = ecp_ht_toggle_save((int) $me['id'], (int) ($in['id'] ?? 0));
        if (!$res['ok']) {
            ecp_m_err((string) $res['error'], $res['error'] === 'not_found' ? 404 : 500);
        }
        ecp_m_ok(['saved' => $res['saved'], 'count' => $res['count']]);
        break;
    }

    default:
        ecp_m_err('unknown_action', 400);
}

// ---------------------------------------------------------------------
// Shapes
// ---------------------------------------------------------------------

/** @param array{rows:list<array<string,mixed>>,total:int} $res */
function ecp_mht_page(array $res, int $page): array
{
    return [
        'items'    => array_map('ecp_mht_card', $res['rows']),
        'total'    => $res['total'],
        'page'     => $page,
        'per_page' => ECP_HT_PER_PAGE,
        'has_more' => $page * ECP_HT_PER_PAGE < $res['total'],
    ];
}

/** Card for lists: enough for a tile, no body. */
function ecp_mht_card(array $r): array
{
    $prep = $r['prep_minutes'] !== null ? (int) $r['prep_minutes'] : null;
    $cook = $r['cook_minutes'] !== null ? (int) $r['cook_minutes'] : null;
    return [
        'id'               => (int) $r['id'],
        'slug'             => (string) $r['slug'],
        'type'             => (string) $r['type'],
        'type_label'       => ECP_HT_TYPES[$r['type']]['label'] ?? '',
        'title'            => (string) $r['title'],
        'summary'          => $r['summary'] !== null ? (string) $r['summary'] : null,
        'cover_url'        => $r['cover_id'] ? ecp_site_url(ecp_ht_image_url((int) $r['cover_id'])) : null,
        'author'           => ['name' => (string) $r['author_name'], 'slug' => (string) $r['author_slug']],
        'total_minutes'    => ($prep !== null || $cook !== null) ? (int) $prep + (int) $cook : null,
        'protein_g'        => $r['protein_g'] !== null ? (float) $r['protein_g'] : null,
        'duration_minutes' => $r['duration_minutes'] !== null ? (int) $r['duration_minutes'] : null,
        'difficulty'       => $r['difficulty'] !== null ? (string) $r['difficulty'] : null,
        'save_count'       => (int) ($r['save_count'] ?? 0),
        'published_at'     => $r['published_at'] ? date('c', strtotime((string) $r['published_at'])) : null,
    ];
}

/** Full post for the detail screen. */
function ecp_mht_detail(array $t, int $identityId): array
{
    $video = ecp_ht_video_embed($t['video_url']);
    $int = static fn ($v) => $v !== null ? (int) $v : null;
    return [
        'id'           => (int) $t['id'],
        'slug'         => (string) $t['slug'],
        'type'         => (string) $t['type'],
        'type_label'   => ECP_HT_TYPES[$t['type']]['label'] ?? '',
        'title'        => (string) $t['title'],
        'summary'      => $t['summary'] !== null ? (string) $t['summary'] : null,
        'body'         => $t['body'] !== null ? (string) $t['body'] : null,
        'images'       => array_map(static fn ($m) => ecp_site_url(ecp_ht_image_url((int) $m['id'])), $t['media']),
        // Open in the Instagram / YouTube app; the app does not embed players.
        'video'        => $video ? ['kind' => $video['kind'], 'url' => (string) $t['video_url']] : null,
        'recipe'       => $t['type'] === 'recipe' ? [
            'servings'      => $int($t['servings']),
            'prep_minutes'  => $int($t['prep_minutes']),
            'cook_minutes'  => $int($t['cook_minutes']),
            'protein_g'     => $t['protein_g'] !== null ? (float) $t['protein_g'] : null,
            'calories_kcal' => $int($t['calories_kcal']),
        ] : null,
        'exercise'     => $t['type'] === 'exercise' ? [
            'duration_minutes' => $int($t['duration_minutes']),
            'difficulty'       => $t['difficulty'],
            'reps_sets'        => $t['reps_sets'],
            'equipment'        => $t['equipment'],
            'body_area'        => $t['body_area'],
            'precautions'      => $t['precautions'],
        ] : null,
        'ingredients'  => array_map(static fn ($i) => [
            'quantity' => $i['quantity'] !== null ? (string) $i['quantity'] : null,
            'item'     => (string) $i['item'],
        ], $t['ingredients']),
        'steps'        => array_values(array_map('strval', $t['steps'])),
        'tags'         => array_map(static fn ($g) => ['slug' => (string) $g['slug'], 'name' => (string) $g['name']], $t['tags']),
        'author'       => [
            'name'       => (string) $t['author_name'],
            'slug'       => (string) $t['author_slug'],
            'headline'   => $t['author_headline'] !== null ? (string) $t['author_headline'] : null,
            'avatar_url' => $t['author_has_avatar'] ? ecp_site_url(ecp_ht_avatar_url((string) $t['author_slug'])) : null,
            'instagram'  => $t['author_instagram'] !== null ? (string) $t['author_instagram'] : null,
        ],
        'save_count'   => (int) ($t['save_count'] ?? 0),
        'saved'        => $identityId > 0 && ecp_ht_is_saved($identityId, (int) $t['id']),
        'can_save'     => ecp_ht_saves_enabled(),
        'published_at' => $t['published_at'] ? date('c', strtotime((string) $t['published_at'])) : null,
        'web_url'      => ecp_site_url('/health-tips/' . $t['slug']),
        'disclaimer'   => 'General wellness information shared by our contributors — not medical advice. '
            . 'If you have a health condition, allergy, are pregnant, or take medicines, check with your doctor '
            . 'before changing your diet or exercise.',
    ];
}
