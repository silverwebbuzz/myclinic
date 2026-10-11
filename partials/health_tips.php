<?php
// =====================================================================
// health_tips.php — data layer for Health Tips (recipes, exercises, tips).
//
// Invite-only: contributors are patient identities the admin has granted
// access to (health_tip_contributors). They write in /my-health-tips; every
// post is reviewed by the admin (/admin/health-tips) before it is public at
// /health-tips/{slug}.
//
// AUTHORIZATION RULES
//   - Every contributor mutator is scoped to contributor_id (no IDOR).
//   - Public reads only ever return status = 'published', is_deleted = 0 rows
//     whose contributor is 'active'. Suspending a contributor hides all of
//     their posts at once.
//
// Photos live under storage/health_tips/{contributor_id}/ (NOT the webroot)
// and are streamed by api/health_tip_image.php, which re-checks visibility.
//
// NB: PDO runs with EMULATE_PREPARES = false — never reuse a named
// placeholder inside one statement (HY093).
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/seo_slugs.php';

const ECP_HT_TERMS_VERSION  = '2026-10-09';
const ECP_HT_PHOTO_MAX      = 5 * 1024 * 1024;   // 5 MB per photo
const ECP_HT_PHOTOS_PER_TIP = 6;
const ECP_HT_PER_PAGE       = 24;
const ECP_HT_PHOTO_MIMES    = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
const ECP_HT_TYPES = [
    'recipe'   => ['label' => 'Recipe',    'plural' => 'Recipes',   'icon' => '🥗'],
    'exercise' => ['label' => 'Exercise',  'plural' => 'Exercises', 'icon' => '🧘'],
    'tip'      => ['label' => 'Quick tip', 'plural' => 'Quick tips', 'icon' => '💡'],
];
const ECP_HT_STATUS_LABELS = [
    'draft'             => 'Draft',
    'submitted'         => 'In review',
    'changes_requested' => 'Changes requested',
    'rejected'          => 'Not accepted',
    'published'         => 'Live',
    'unpublished'       => 'Unpublished',
];

/** Statuses a contributor can edit in place. */
const ECP_HT_EDITABLE = ['draft', 'changes_requested', 'unpublished'];

// ---------------------------------------------------------------------
// Contributors
// ---------------------------------------------------------------------

/** @return array<string,mixed>|null contributor row for an identity (any status) */
function ecp_ht_contributor_for_identity(int $identityId): ?array
{
    $db = ecp_db();
    if (!$db || $identityId <= 0) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT * FROM health_tip_contributors WHERE identity_id = :i LIMIT 1');
        $st->execute(['i' => $identityId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;   // migration not run yet
    }
}

/** @return array<string,mixed>|null active contributor by public slug */
function ecp_ht_contributor_by_slug(string $slug): ?array
{
    $db = ecp_db();
    if (!$db || $slug === '') {
        return null;
    }
    $st = $db->prepare("SELECT * FROM health_tip_contributors WHERE slug = :s AND status = 'active' LIMIT 1");
    $st->execute(['s' => $slug]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Profile complete + terms accepted + not suspended → may submit for review. */
function ecp_ht_can_submit(array $c): bool
{
    return ($c['status'] ?? '') === 'active'
        && !empty($c['terms_accepted_at'])
        && trim((string) ($c['display_name'] ?? '')) !== '';
}

/**
 * Save the contributor's public profile (and accept the terms).
 *
 * @return array{ok:bool,error?:string}
 */
function ecp_ht_save_profile(array $c, array $data, ?array $avatar): array
{
    $db = ecp_db();
    if (!$db) {
        return ['ok' => false, 'error' => 'server_error'];
    }

    $name = ecp_ht_clean((string) ($data['display_name'] ?? ''), 120);
    if ($name === null) {
        return ['ok' => false, 'error' => 'Please enter the name readers will see.'];
    }

    $insta = trim((string) ($data['instagram_handle'] ?? ''));
    $insta = ltrim(preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $insta) ?? $insta, '@');
    $insta = rtrim($insta, '/');
    if ($insta !== '' && !preg_match('/^[A-Za-z0-9._]{1,30}$/', $insta)) {
        return ['ok' => false, 'error' => 'Instagram handle can only have letters, numbers, dots and underscores.'];
    }

    $youtube = trim((string) ($data['youtube_url'] ?? ''));
    if ($youtube !== '' && !preg_match('#^https://(www\.|m\.)?(youtube\.com|youtu\.be)/#i', $youtube)) {
        return ['ok' => false, 'error' => 'YouTube link must start with https://youtube.com/ or https://youtu.be/'];
    }

    $website = trim((string) ($data['website_url'] ?? ''));
    if ($website !== '' && (!filter_var($website, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $website))) {
        return ['ok' => false, 'error' => 'Website must be a full link starting with https://'];
    }

    $acceptTerms = !empty($data['accept_terms']);
    if (empty($c['terms_accepted_at']) && !$acceptTerms) {
        return ['ok' => false, 'error' => 'Please read and accept the contributor terms.'];
    }

    $avatarPath = null;
    if ($avatar && !empty($avatar['tmp_name']) && is_uploaded_file($avatar['tmp_name'])) {
        $saved = ecp_ht_store_photo((int) $c['id'], $avatar);
        if (!$saved['ok']) {
            return $saved;
        }
        $avatarPath = $saved['path'];
    }

    $sql = 'UPDATE health_tip_contributors
            SET display_name = :name, headline = :headline, bio = :bio,
                instagram_handle = :insta, youtube_url = :yt, website_url = :web';
    $bind = [
        'name'     => $name,
        'headline' => ecp_ht_clean((string) ($data['headline'] ?? ''), 120),
        'bio'      => ecp_ht_clean((string) ($data['bio'] ?? ''), 600),
        'insta'    => $insta !== '' ? $insta : null,
        'yt'       => $youtube !== '' ? mb_substr($youtube, 0, 255) : null,
        'web'      => $website !== '' ? mb_substr($website, 0, 255) : null,
        'id'       => (int) $c['id'],
    ];
    if ($avatarPath !== null) {
        $sql .= ', avatar_path = :avatar';
        $bind['avatar'] = $avatarPath;
    }
    if (empty($c['terms_accepted_at']) && $acceptTerms) {
        $sql .= ', terms_accepted_at = NOW(), terms_version = :tv';
        $bind['tv'] = ECP_HT_TERMS_VERSION;
    }
    $db->prepare($sql . ' WHERE id = :id')->execute($bind);

    if ($avatarPath !== null && !empty($c['avatar_path'])) {
        ecp_ht_unlink((string) $c['avatar_path']);
    }

    return ['ok' => true];
}

// ---------------------------------------------------------------------
// Contributor's own posts
// ---------------------------------------------------------------------

/** @return list<array<string,mixed>> */
function ecp_ht_my_tips(int $contributorId): array
{
    $db = ecp_db();
    if (!$db || $contributorId <= 0) {
        return [];
    }
    $st = $db->prepare(
        'SELECT t.id, t.type, t.title, t.slug, t.status, t.review_note, t.submitted_at,
                t.published_at, t.view_count, t.updated_at,
                ' . (ecp_ht_saves_enabled() ? 't.save_count' : '0') . ' AS save_count,
                (SELECT m.id FROM health_tip_media m WHERE m.tip_id = t.id ORDER BY m.sort, m.id LIMIT 1) AS cover_id
         FROM health_tips t
         WHERE t.contributor_id = :c AND t.is_deleted = 0
         ORDER BY t.updated_at DESC, t.id DESC'
    );
    $st->execute(['c' => $contributorId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string,mixed>|null a post owned by this contributor */
function ecp_ht_find_owned(int $contributorId, int $id): ?array
{
    $db = ecp_db();
    if (!$db || $contributorId <= 0 || $id <= 0) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM health_tips WHERE id = :id AND contributor_id = :c AND is_deleted = 0 LIMIT 1');
    $st->execute(['id' => $id, 'c' => $contributorId]);
    $tip = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    return $tip ? ecp_ht_hydrate($tip) : null;
}

function ecp_ht_is_editable(array $tip): bool
{
    return in_array($tip['status'] ?? '', ECP_HT_EDITABLE, true);
}

/**
 * Create or update a post. $submit = true also sends it for review.
 *
 * @param array<string,mixed> $data   $_POST
 * @param array<string,mixed> $files  $_FILES['photos'] (multi) or []
 * @return array{ok:bool,error?:string,id?:int}
 */
function ecp_ht_save(array $c, ?int $id, array $data, array $files, bool $submit): array
{
    $db = ecp_db();
    if (!$db) {
        return ['ok' => false, 'error' => 'server_error'];
    }
    $cid = (int) $c['id'];
    if (($c['status'] ?? '') !== 'active') {
        return ['ok' => false, 'error' => 'Your contributor access is paused. Please contact us.'];
    }

    $existing = null;
    if ($id) {
        $existing = ecp_ht_find_owned($cid, $id);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'Post not found.'];
        }
        if (!ecp_ht_is_editable($existing)) {
            return ['ok' => false, 'error' => 'This post can’t be edited right now.'];
        }
        $type = (string) $existing['type'];
    } else {
        $type = (string) ($data['type'] ?? '');
        if (!isset(ECP_HT_TYPES[$type])) {
            return ['ok' => false, 'error' => 'Choose recipe, exercise or quick tip.'];
        }
    }

    $title = ecp_ht_clean((string) ($data['title'] ?? ''), 160);
    if ($title === null) {
        return ['ok' => false, 'error' => 'Please add a title.'];
    }

    $video = trim((string) ($data['video_url'] ?? ''));
    if ($video !== '' && ecp_ht_video_embed($video) === null) {
        return ['ok' => false, 'error' => 'Video link must be an Instagram post/reel or a YouTube video.'];
    }

    // Child rows from the repeating inputs.
    $ingredients = [];
    if ($type === 'recipe') {
        $qtys  = (array) ($data['ing_qty'] ?? []);
        $items = (array) ($data['ing_item'] ?? []);
        foreach ($items as $i => $item) {
            $item = ecp_ht_clean((string) $item, 160);
            if ($item === null) {
                continue;
            }
            $ingredients[] = ['qty' => ecp_ht_clean((string) ($qtys[$i] ?? ''), 40), 'item' => $item];
            if (count($ingredients) >= 40) {
                break;
            }
        }
    }
    $steps = [];
    if ($type !== 'tip') {
        foreach ((array) ($data['step'] ?? []) as $s) {
            $s = ecp_ht_clean((string) $s, 1000);
            if ($s !== null) {
                $steps[] = $s;
            }
            if (count($steps) >= 30) {
                break;
            }
        }
    }

    $fields = [
        'title'            => $title,
        'summary'          => ecp_ht_clean((string) ($data['summary'] ?? ''), 300),
        'body'             => ecp_ht_clean((string) ($data['body'] ?? ''), 8000),
        'video_url'        => $video !== '' ? mb_substr($video, 0, 255) : null,
        'servings'         => null, 'prep_minutes' => null, 'cook_minutes' => null,
        'protein_g'        => null, 'calories_kcal' => null,
        'duration_minutes' => null, 'difficulty' => null, 'reps_sets' => null,
        'equipment'        => null, 'body_area' => null, 'precautions' => null,
    ];
    if ($type === 'recipe') {
        $fields['servings']      = ecp_ht_int($data['servings'] ?? null, 1, 50);
        $fields['prep_minutes']  = ecp_ht_int($data['prep_minutes'] ?? null, 0, 1440);
        $fields['cook_minutes']  = ecp_ht_int($data['cook_minutes'] ?? null, 0, 1440);
        $fields['calories_kcal'] = ecp_ht_int($data['calories_kcal'] ?? null, 0, 5000);
        $protein = trim((string) ($data['protein_g'] ?? ''));
        $fields['protein_g'] = ($protein !== '' && is_numeric($protein) && (float) $protein >= 0 && (float) $protein < 1000)
            ? round((float) $protein, 1) : null;
    } elseif ($type === 'exercise') {
        $fields['duration_minutes'] = ecp_ht_int($data['duration_minutes'] ?? null, 1, 600);
        $diff = (string) ($data['difficulty'] ?? '');
        $fields['difficulty']  = in_array($diff, ['easy', 'moderate', 'hard'], true) ? $diff : null;
        $fields['reps_sets']   = ecp_ht_clean((string) ($data['reps_sets'] ?? ''), 80);
        $fields['equipment']   = ecp_ht_clean((string) ($data['equipment'] ?? ''), 160);
        $fields['body_area']   = ecp_ht_clean((string) ($data['body_area'] ?? ''), 80);
        $fields['precautions'] = ecp_ht_clean((string) ($data['precautions'] ?? ''), 600);
    }

    // Tags: only ids that exist, in a group that suits this type.
    $tagIds = [];
    $allowedGroups = $type === 'exercise' ? ['focus'] : ($type === 'recipe' ? ['diet', 'goal', 'meal'] : ['diet', 'goal', 'meal', 'focus']);
    $validTags = [];
    foreach (ecp_ht_tags() as $t) {
        if (in_array($t['tag_group'], $allowedGroups, true)) {
            $validTags[(int) $t['id']] = true;
        }
    }
    foreach ((array) ($data['tags'] ?? []) as $tid) {
        $tid = (int) $tid;
        if (isset($validTags[$tid])) {
            $tagIds[$tid] = $tid;
        }
    }

    // Photos to drop (only this post's own media).
    $removeIds = array_map('intval', (array) ($data['remove_media'] ?? []));
    $currentMedia = $existing ? $existing['media'] : [];
    $keptCount = 0;
    foreach ($currentMedia as $m) {
        if (!in_array((int) $m['id'], $removeIds, true)) {
            $keptCount++;
        }
    }
    $uploads = ecp_ht_normalize_files($files);
    if ($keptCount + count($uploads) > ECP_HT_PHOTOS_PER_TIP) {
        return ['ok' => false, 'error' => 'Up to ' . ECP_HT_PHOTOS_PER_TIP . ' photos per post.'];
    }

    if ($submit) {
        if (!ecp_ht_can_submit($c)) {
            return ['ok' => false, 'error' => 'Complete your contributor profile and accept the terms before sending for review.'];
        }
        $err = ecp_ht_submit_check($type, $fields, $ingredients, $steps, $keptCount + count($uploads));
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
    }

    // Store the new photos first so a bad file fails before any DB write.
    $stored = [];
    foreach ($uploads as $f) {
        $saved = ecp_ht_store_photo($cid, $f);
        if (!$saved['ok']) {
            foreach ($stored as $s) {
                ecp_ht_unlink($s['path']);
            }
            return $saved;
        }
        $stored[] = $saved;
    }

    $db->beginTransaction();
    try {
        if ($existing === null) {
            $ins = $db->prepare(
                'INSERT INTO health_tips (contributor_id, type, title, slug, status)
                 VALUES (:c, :type, :title, :slug, "draft")'
            );
            $ins->execute([
                'c'     => $cid,
                'type'  => $type,
                'title' => $title,
                'slug'  => 'tmp-' . bin2hex(random_bytes(8)),
            ]);
            $id = (int) $db->lastInsertId();
        }

        $set = [];
        $bind = ['id' => $id, 'c' => $cid];
        foreach ($fields as $col => $val) {
            $set[] = "`{$col}` = :f_{$col}";
            $bind['f_' . $col] = $val;
        }
        // Slug follows the title until the post has been published once.
        if ($existing === null || empty($existing['published_at'])) {
            $set[] = '`slug` = :slug';
            $bind['slug'] = ecp_ht_make_slug($title, $id);
        }
        if ($submit) {
            $set[] = "`status` = 'submitted'";
            $set[] = '`submitted_at` = NOW()';
        }
        $db->prepare('UPDATE health_tips SET ' . implode(', ', $set) . ' WHERE id = :id AND contributor_id = :c')
           ->execute($bind);

        $db->prepare('DELETE FROM health_tip_ingredients WHERE tip_id = :t')->execute(['t' => $id]);
        if ($ingredients) {
            $insI = $db->prepare('INSERT INTO health_tip_ingredients (tip_id, sort, quantity, item) VALUES (:t, :s, :q, :i)');
            foreach ($ingredients as $n => $ing) {
                $insI->execute(['t' => $id, 's' => $n, 'q' => $ing['qty'], 'i' => $ing['item']]);
            }
        }
        $db->prepare('DELETE FROM health_tip_steps WHERE tip_id = :t')->execute(['t' => $id]);
        if ($steps) {
            $insS = $db->prepare('INSERT INTO health_tip_steps (tip_id, sort, body) VALUES (:t, :s, :b)');
            foreach ($steps as $n => $s) {
                $insS->execute(['t' => $id, 's' => $n, 'b' => $s]);
            }
        }
        $db->prepare('DELETE FROM health_tip_tag_map WHERE tip_id = :t')->execute(['t' => $id]);
        if ($tagIds) {
            $insT = $db->prepare('INSERT INTO health_tip_tag_map (tip_id, tag_id) VALUES (:t, :g)');
            foreach ($tagIds as $tid) {
                $insT->execute(['t' => $id, 'g' => $tid]);
            }
        }

        $dropPaths = [];
        foreach ($currentMedia as $m) {
            if (in_array((int) $m['id'], $removeIds, true)) {
                $db->prepare('DELETE FROM health_tip_media WHERE id = :m AND tip_id = :t')
                   ->execute(['m' => (int) $m['id'], 't' => $id]);
                $dropPaths[] = (string) $m['file_path'];
            }
        }
        if ($stored) {
            $next = (int) $db->query('SELECT COALESCE(MAX(sort), -1) + 1 FROM health_tip_media WHERE tip_id = ' . (int) $id)->fetchColumn();
            $insM = $db->prepare(
                'INSERT INTO health_tip_media (tip_id, contributor_id, sort, file_path, file_mime) VALUES (:t, :c, :s, :p, :m)'
            );
            foreach ($stored as $s) {
                $insM->execute(['t' => $id, 'c' => $cid, 's' => $next++, 'p' => $s['path'], 'm' => $s['mime']]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        foreach ($stored as $s) {
            ecp_ht_unlink($s['path']);
        }
        error_log('[health_tips] save: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not save. Please try again.'];
    }

    foreach ($dropPaths as $p) {
        ecp_ht_unlink($p);
    }

    return ['ok' => true, 'id' => (int) $id];
}

/**
 * Tell the admin a post is waiting for review. Inbox = HEALTHTIPS_ADMIN_EMAIL,
 * else SMTP_TO_EMAIL, else wecare@. Never blocks or fails the submit.
 */
function ecp_ht_notify_admin_submitted(array $c, int $tipId): void
{
    try {
        require_once __DIR__ . '/mailer.php';
        $tip = ecp_ht_find_owned((int) $c['id'], $tipId);
        if ($tip === null) {
            return;
        }
        $cfg = ecp_smtp_config();
        $inbox = trim((string) ($cfg['HEALTHTIPS_ADMIN_EMAIL'] ?? '')) ?: trim((string) ($cfg['SMTP_TO_EMAIL'] ?? '')) ?: 'wecare@eclinicpro.com';
        $from = trim((string) ($cfg['HEALTHTIPS_FROM'] ?? '')) ?: 'healthtips@eclinicpro.com';
        $reviewUrl = ecp_portal_url('/admin/health-tips/' . $tipId);
        $esc = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $type = ECP_HT_TYPES[$tip['type']]['label'] ?? 'Post';

        $body = '<p style="margin:0 0 16px; font-size:15px; line-height:1.65;">'
            . $esc($c['display_name']) . ' sent a ' . $esc(strtolower($type)) . ' for review:</p>'
            . '<p style="margin:0 0 8px; font-size:17px; font-weight:600;">' . $esc($tip['title']) . '</p>'
            . ($tip['summary'] ? '<p style="margin:0 0 20px; font-size:14px; color:#6e6e73; line-height:1.6;">' . $esc($tip['summary']) . '</p>' : '')
            . '<p style="margin:0;"><a href="' . $esc($reviewUrl) . '" style="display:inline-block; background:#0F9B6E; color:#fff; padding:12px 24px; border-radius:980px; text-decoration:none; font-weight:600;">Review it</a></p>';

        ecp_send_mail(
            $inbox,
            'Health tip to review: ' . $tip['title'],
            ecp_email_template('New ' . strtolower($type) . ' waiting for review', $body),
            'eClinicPro',
            null,
            $from,
            'eClinicPro Health Tips'
        );
    } catch (Throwable $e) {
        error_log('[health_tips] admin notify: ' . $e->getMessage());
    }
}

/** What a post needs before it can go to review. Returns an error or null. */
function ecp_ht_submit_check(string $type, array $f, array $ingredients, array $steps, int $photos): ?string
{
    if ($f['summary'] === null || mb_strlen((string) $f['summary']) < 20) {
        return 'Add a short summary (at least 20 characters) — it shows on the card.';
    }
    if ($type === 'recipe') {
        if (count($ingredients) < 2) {
            return 'Add at least 2 ingredients.';
        }
        if (count($steps) < 1) {
            return 'Add the method steps.';
        }
        if ($photos < 1) {
            return 'Add at least one photo of the dish.';
        }
    } elseif ($type === 'exercise') {
        if (count($steps) < 1) {
            return 'Add the steps to do the exercise.';
        }
        if ($photos < 1 && $f['video_url'] === null) {
            return 'Add a photo or a video link showing the exercise.';
        }
    } elseif ($f['body'] === null || mb_strlen((string) $f['body']) < 40) {
        return 'Write the tip itself (at least 40 characters).';
    }
    return null;
}

/**
 * Pull a post back to draft so it can be edited (from review or from live).
 *
 * @return array{ok:bool,error?:string}
 */
function ecp_ht_withdraw(int $contributorId, int $id): array
{
    $tip = ecp_ht_find_owned($contributorId, $id);
    if ($tip === null || !in_array($tip['status'], ['submitted', 'published'], true)) {
        return ['ok' => false, 'error' => 'This post can’t be withdrawn.'];
    }
    ecp_db()->prepare("UPDATE health_tips SET status = 'draft' WHERE id = :id AND contributor_id = :c")
            ->execute(['id' => $id, 'c' => $contributorId]);
    return ['ok' => true];
}

/** Soft-delete a post and remove its photos from disk. */
function ecp_ht_delete(int $contributorId, int $id): array
{
    $tip = ecp_ht_find_owned($contributorId, $id);
    if ($tip === null) {
        return ['ok' => false, 'error' => 'Post not found.'];
    }
    $db = ecp_db();
    $db->prepare('UPDATE health_tips SET is_deleted = 1 WHERE id = :id AND contributor_id = :c')
       ->execute(['id' => $id, 'c' => $contributorId]);
    foreach ($tip['media'] as $m) {
        ecp_ht_unlink((string) $m['file_path']);
    }
    $db->prepare('DELETE FROM health_tip_media WHERE tip_id = :t')->execute(['t' => $id]);
    return ['ok' => true];
}

// ---------------------------------------------------------------------
// Public reads
// ---------------------------------------------------------------------

/** Saves patch imported? (checked once per request so the site works either way) */
function ecp_ht_saves_enabled(): bool
{
    static $on = null;
    if ($on === null) {
        $db = ecp_db();
        try {
            $on = $db && $db->query("SHOW COLUMNS FROM health_tips LIKE 'save_count'")->fetchColumn() !== false;
        } catch (Throwable $e) {
            $on = false;
        }
    }
    return $on;
}

/**
 * Published posts, newest first, with optional filters.
 * saved_by = identity id → that patient's saved posts, most recently saved first.
 *
 * @param array{type?:string,tag?:string,q?:string,contributor_id?:int,page?:int,exclude_id?:int,limit?:int,saved_by?:int} $f
 * @return array{rows:list<array<string,mixed>>,total:int}
 */
function ecp_ht_public_list(array $f = []): array
{
    $db = ecp_db();
    if (!$db) {
        return ['rows' => [], 'total' => 0];
    }
    $where = ["t.status = 'published'", 't.is_deleted = 0', "c.status = 'active'"];
    $bind = [];
    $join = '';
    if (!empty($f['type']) && isset(ECP_HT_TYPES[$f['type']])) {
        $where[] = 't.type = :type';
        $bind['type'] = $f['type'];
    }
    if (!empty($f['tag'])) {
        $join = ' JOIN health_tip_tag_map tm ON tm.tip_id = t.id JOIN health_tip_tags tg ON tg.id = tm.tag_id AND tg.is_active = 1';
        $where[] = 'tg.slug = :tag';
        $bind['tag'] = (string) $f['tag'];
    }
    if (!empty($f['q'])) {
        $where[] = '(t.title LIKE :q1 OR t.summary LIKE :q2)';
        $bind['q1'] = '%' . $f['q'] . '%';
        $bind['q2'] = '%' . $f['q'] . '%';
    }
    if (!empty($f['contributor_id'])) {
        $where[] = 't.contributor_id = :cid';
        $bind['cid'] = (int) $f['contributor_id'];
    }
    $order = 't.published_at DESC, t.id DESC';
    if (!empty($f['saved_by'])) {
        if (!ecp_ht_saves_enabled()) {
            return ['rows' => [], 'total' => 0];
        }
        $join .= ' JOIN health_tip_saves sv ON sv.tip_id = t.id AND sv.identity_id = :sid';
        $bind['sid'] = (int) $f['saved_by'];
        $order = 'sv.created_at DESC, t.id DESC';
    }
    if (!empty($f['exclude_id'])) {
        $where[] = 't.id <> :xid';
        $bind['xid'] = (int) $f['exclude_id'];
    }
    $from = ' FROM health_tips t JOIN health_tip_contributors c ON c.id = t.contributor_id' . $join
          . ' WHERE ' . implode(' AND ', $where);

    $limit = (int) ($f['limit'] ?? ECP_HT_PER_PAGE);
    $page = max(1, (int) ($f['page'] ?? 1));

    try {
        $cnt = $db->prepare('SELECT COUNT(*)' . $from);
        $cnt->execute($bind);
        $total = (int) $cnt->fetchColumn();

        $st = $db->prepare(
            'SELECT t.id, t.type, t.title, t.slug, t.summary, t.prep_minutes, t.cook_minutes, t.protein_g,
                    t.duration_minutes, t.difficulty, t.published_at,
                    ' . (ecp_ht_saves_enabled() ? 't.save_count' : '0') . ' AS save_count,
                    c.display_name AS author_name, c.slug AS author_slug,
                    (SELECT m.id FROM health_tip_media m WHERE m.tip_id = t.id ORDER BY m.sort, m.id LIMIT 1) AS cover_id'
            . $from . ' ORDER BY ' . $order . ' LIMIT :lim OFFSET :off'
        );
        foreach ($bind as $k => $v) {
            $st->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', ($page - 1) * $limit, PDO::PARAM_INT);
        $st->execute();
        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total];
    } catch (Throwable $e) {
        error_log('[health_tips] list: ' . $e->getMessage());
        return ['rows' => [], 'total' => 0];
    }
}

/**
 * One post by slug, fully hydrated. Published only — unless $ownerContributorId
 * owns it (contributor preview of a draft).
 *
 * @return array<string,mixed>|null
 */
function ecp_ht_find_by_slug(string $slug, ?int $ownerContributorId = null): ?array
{
    $db = ecp_db();
    if (!$db || $slug === '') {
        return null;
    }
    try {
        $st = $db->prepare(
            'SELECT t.*, c.display_name AS author_name, c.slug AS author_slug, c.headline AS author_headline,
                    c.bio AS author_bio, c.instagram_handle AS author_instagram, c.status AS author_status,
                    (c.avatar_path IS NOT NULL) AS author_has_avatar
             FROM health_tips t JOIN health_tip_contributors c ON c.id = t.contributor_id
             WHERE t.slug = :s AND t.is_deleted = 0 LIMIT 1'
        );
        $st->execute(['s' => $slug]);
        $tip = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
    if ($tip === null) {
        return null;
    }
    $isPublic = $tip['status'] === 'published' && $tip['author_status'] === 'active';
    $isOwner = $ownerContributorId !== null && (int) $tip['contributor_id'] === $ownerContributorId;
    if (!$isPublic && !$isOwner) {
        return null;
    }
    $tip = ecp_ht_hydrate($tip);
    $tip['is_preview'] = !$isPublic;
    return $tip;
}

/** Attach ingredients, steps, media, tags. */
function ecp_ht_hydrate(array $tip): array
{
    $db = ecp_db();
    $id = (int) $tip['id'];
    $q = static function (string $sql) use ($db, $id): array {
        $st = $db->prepare($sql);
        $st->execute(['t' => $id]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    };
    $tip['ingredients'] = $q('SELECT quantity, item FROM health_tip_ingredients WHERE tip_id = :t ORDER BY sort, id');
    $tip['steps']       = array_column($q('SELECT body FROM health_tip_steps WHERE tip_id = :t ORDER BY sort, id'), 'body');
    $tip['media']       = $q('SELECT id, file_path, file_mime FROM health_tip_media WHERE tip_id = :t ORDER BY sort, id');
    $tip['tags']        = $q(
        'SELECT g.id, g.slug, g.name, g.tag_group FROM health_tip_tag_map m
         JOIN health_tip_tags g ON g.id = m.tag_id WHERE m.tip_id = :t ORDER BY g.tag_group, g.sort'
    );
    return $tip;
}

function ecp_ht_count_view(int $id): void
{
    $db = ecp_db();
    if ($db && $id > 0) {
        try {
            $db->prepare('UPDATE health_tips SET view_count = view_count + 1 WHERE id = :id')->execute(['id' => $id]);
        } catch (Throwable $e) {
            // non-fatal
        }
    }
}

// ---------------------------------------------------------------------
// Saves (patients bookmark / "like" a post) — health_tip_saves
// ---------------------------------------------------------------------

/**
 * Save or un-save a published post for this patient.
 *
 * @return array{ok:bool,error?:string,saved?:bool,count?:int}
 */
function ecp_ht_toggle_save(int $identityId, int $tipId): array
{
    $db = ecp_db();
    if (!$db || $identityId <= 0 || $tipId <= 0) {
        return ['ok' => false, 'error' => 'server_error'];
    }
    $chk = $db->prepare(
        "SELECT t.id FROM health_tips t JOIN health_tip_contributors c ON c.id = t.contributor_id
         WHERE t.id = :id AND t.status = 'published' AND t.is_deleted = 0 AND c.status = 'active' LIMIT 1"
    );
    $chk->execute(['id' => $tipId]);
    if ($chk->fetchColumn() === false) {
        return ['ok' => false, 'error' => 'not_found'];
    }

    $db->beginTransaction();
    try {
        $del = $db->prepare('DELETE FROM health_tip_saves WHERE tip_id = :t AND identity_id = :i');
        $del->execute(['t' => $tipId, 'i' => $identityId]);
        if ($del->rowCount() > 0) {
            $saved = false;
            $db->prepare('UPDATE health_tips SET save_count = GREATEST(save_count, 1) - 1 WHERE id = :t')->execute(['t' => $tipId]);
        } else {
            $saved = true;
            $db->prepare('INSERT IGNORE INTO health_tip_saves (tip_id, identity_id) VALUES (:t, :i)')
               ->execute(['t' => $tipId, 'i' => $identityId]);
            $db->prepare('UPDATE health_tips SET save_count = save_count + 1 WHERE id = :t')->execute(['t' => $tipId]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('[health_tips] save: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'server_error'];
    }

    $cnt = $db->prepare('SELECT save_count FROM health_tips WHERE id = :t');
    $cnt->execute(['t' => $tipId]);
    return ['ok' => true, 'saved' => $saved, 'count' => (int) $cnt->fetchColumn()];
}

/** Has this patient saved the post? */
function ecp_ht_is_saved(int $identityId, int $tipId): bool
{
    $db = ecp_db();
    if (!$db || $identityId <= 0 || $tipId <= 0) {
        return false;
    }
    try {
        $st = $db->prepare('SELECT 1 FROM health_tip_saves WHERE tip_id = :t AND identity_id = :i LIMIT 1');
        $st->execute(['t' => $tipId, 'i' => $identityId]);
        return $st->fetchColumn() !== false;
    } catch (Throwable $e) {
        return false;   // saves patch not run yet
    }
}

/** @return list<int> which of these post ids the patient has saved */
function ecp_ht_saved_ids(int $identityId, array $tipIds): array
{
    $db = ecp_db();
    $tipIds = array_values(array_unique(array_filter(array_map('intval', $tipIds))));
    if (!$db || $identityId <= 0 || !$tipIds) {
        return [];
    }
    try {
        $in = implode(',', $tipIds);   // ints only, safe to inline
        $st = $db->prepare("SELECT tip_id FROM health_tip_saves WHERE identity_id = :i AND tip_id IN ($in)");
        $st->execute(['i' => $identityId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

/** @return list<array<string,mixed>> active tags (optionally one group) */
function ecp_ht_tags(?string $group = null): array
{
    static $all = null;
    if ($all === null) {
        $all = [];
        $db = ecp_db();
        if ($db) {
            try {
                $all = $db->query('SELECT id, slug, name, tag_group FROM health_tip_tags WHERE is_active = 1 ORDER BY tag_group, sort, name')
                          ->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $all = [];
            }
        }
    }
    if ($group === null) {
        return $all;
    }
    return array_values(array_filter($all, static fn ($t) => $t['tag_group'] === $group));
}

/** @return list<array<string,mixed>> active contributors with ≥1 live post (sitemap) */
function ecp_ht_public_contributors(): array
{
    $db = ecp_db();
    if (!$db) {
        return [];
    }
    try {
        return $db->query(
            "SELECT c.slug, MAX(t.updated_at) AS last_updated FROM health_tip_contributors c
             JOIN health_tips t ON t.contributor_id = c.id AND t.status = 'published' AND t.is_deleted = 0
             WHERE c.status = 'active' GROUP BY c.id, c.slug"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

// ---------------------------------------------------------------------
// Video embeds — we never host video; Instagram / YouTube links only.
// ---------------------------------------------------------------------

/**
 * @return array{kind:string,src:string}|null iframe src for a supported link
 */
function ecp_ht_video_embed(?string $url): ?array
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }
    if (preg_match('#^https?://(?:www\.)?instagram\.com/(?:[A-Za-z0-9._]+/)?(p|reel|reels|tv)/([A-Za-z0-9_\-]+)#i', $url, $m)) {
        $kind = strtolower($m[1]) === 'p' ? 'p' : 'reel';
        return ['kind' => 'instagram', 'src' => 'https://www.instagram.com/' . $kind . '/' . $m[2] . '/embed'];
    }
    if (preg_match('#^https?://(?:www\.|m\.)?youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)([A-Za-z0-9_\-]{11})#i', $url, $m)
        || preg_match('#^https?://youtu\.be/([A-Za-z0-9_\-]{11})#i', $url, $m)) {
        return ['kind' => 'youtube', 'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1]];
    }
    return null;
}

// ---------------------------------------------------------------------
// Files + small helpers
// ---------------------------------------------------------------------

/**
 * Validate + store an uploaded photo under storage/health_tips/{contributor}/.
 *
 * @return array{ok:bool,error?:string,path?:string,mime?:string}
 */
function ecp_ht_store_photo(int $contributorId, array $file): array
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'A photo failed to upload. Please try again.'];
    }
    if ((int) ($file['size'] ?? 0) > ECP_HT_PHOTO_MAX) {
        return ['ok' => false, 'error' => 'Each photo must be under ' . (int) (ECP_HT_PHOTO_MAX / 1024 / 1024) . ' MB.'];
    }
    $mime = mime_content_type($file['tmp_name']) ?: '';
    if (!isset(ECP_HT_PHOTO_MIMES[$mime])) {
        return ['ok' => false, 'error' => 'Photos must be JPG, PNG or WebP.'];
    }
    $dir = __DIR__ . '/../storage/health_tips/' . $contributorId;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'upload_failed'];
    }
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . ECP_HT_PHOTO_MIMES[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'A photo failed to upload. Please try again.'];
    }
    return ['ok' => true, 'path' => 'storage/health_tips/' . $contributorId . '/' . $name, 'mime' => $mime];
}

/** Turn a multi-file $_FILES entry into a list of single-file arrays (skips empties). */
function ecp_ht_normalize_files(array $files): array
{
    $out = [];
    if (!isset($files['name']) || !is_array($files['name'])) {
        return $out;
    }
    foreach ($files['name'] as $i => $name) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($files['tmp_name'][$i])) {
            continue;
        }
        if (!is_uploaded_file($files['tmp_name'][$i])) {
            continue;
        }
        $out[] = [
            'name' => $name,
            'tmp_name' => $files['tmp_name'][$i],
            'size' => $files['size'][$i] ?? 0,
            'error' => $files['error'][$i] ?? UPLOAD_ERR_OK,
        ];
    }
    return $out;
}

/** Delete a stored file, but only inside storage/health_tips/. */
function ecp_ht_unlink(string $relPath): void
{
    if ($relPath === '' || !str_starts_with($relPath, 'storage/health_tips/')) {
        return;
    }
    $abs = __DIR__ . '/../' . $relPath;
    if (is_file($abs)) {
        @unlink($abs);
    }
}

function ecp_ht_make_slug(string $title, int $id): string
{
    $base = ecp_slug($title);
    $base = $base === '' ? 'health-tip' : rtrim(mb_substr($base, 0, 150), '-');
    return $base . '-' . $id;
}

function ecp_ht_clean(string $value, int $max): ?string
{
    $value = trim(str_replace("\r\n", "\n", $value));
    return $value === '' ? null : mb_substr($value, 0, $max);
}

function ecp_ht_int(mixed $value, int $min, int $max): ?int
{
    $value = trim((string) $value);
    if ($value === '' || !ctype_digit($value)) {
        return null;
    }
    $n = (int) $value;
    return ($n < $min || $n > $max) ? null : $n;
}

/** "25 min", "1 hr 10 min" */
function ecp_ht_minutes(?int $m): string
{
    if (!$m) {
        return '';
    }
    if ($m < 60) {
        return $m . ' min';
    }
    $h = intdiv($m, 60);
    $r = $m % 60;
    return $h . ' hr' . ($r ? ' ' . $r . ' min' : '');
}

function ecp_ht_image_url(int $mediaId): string
{
    return '/api/health_tip_image?m=' . $mediaId;
}

function ecp_ht_avatar_url(string $contributorSlug): string
{
    return '/api/health_tip_image?a=' . rawurlencode($contributorSlug);
}
