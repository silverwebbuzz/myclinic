<?php

declare(strict_types=1);

namespace App\Services\Wellness;

/**
 * Creator Studio editor for recipes, exercises, quick tips and videos
 * (moved here from eclinicpro.com/my-health-tips).
 *
 * It calls the SAME functions the old editor used — /partials/health_tips.php
 * (validation, photos, tags, slugs, admin alert) and through it the shared
 * quota rules — so nothing about how posts are saved, checked or charged
 * changes. Posts still belong to the creator's health_tip_contributors row,
 * which CreatorService::syncLegacy() keeps in step.
 */
final class Posts
{
    public const GROUPS = [
        'recipe' => ['diet' => 'Diet', 'meal' => 'Meal', 'goal' => 'Good for'],
        'exercise' => ['focus' => 'Focus'],
        'tip' => ['diet' => 'Diet', 'meal' => 'Meal', 'goal' => 'Good for', 'focus' => 'Focus'],
        'video' => ['diet' => 'Diet', 'meal' => 'Meal', 'goal' => 'Good for', 'focus' => 'Focus'],
    ];

    private static bool $loaded = false;

    public static function load(): void
    {
        if (!self::$loaded) {
            require_once dirname(__DIR__, 4) . '/partials/health_tips.php';   // also loads db.php, seo_slugs.php, wellness_quota.php
            self::$loaded = true;
        }
    }

    /** @return array<string, array{label:string,plural:string,icon:string,path:string}> */
    public static function types(): array
    {
        self::load();

        return \ECP_HT_TYPES;
    }

    /** The creator's Health Tips contributor row (created on demand when they're approved). */
    public static function contributor(array $creator): ?array
    {
        self::load();
        $c = \ecp_ht_contributor_for_identity((int) $creator['identity_id']);
        if ($c === null && array_intersect(CreatorService::TIP_TYPES, CreatorService::approvedTypes((int) $creator['id']))) {
            CreatorService::syncLegacy((int) $creator['id']);
            $c = \ecp_ht_contributor_for_identity((int) $creator['identity_id']);
        }

        return $c;
    }

    /** @return list<string> types this creator may START (approved), in display order */
    public static function allowedTypes(array $contributor): array
    {
        self::load();

        return \ecp_ht_allowed_types($contributor);
    }

    /** @return array<string,mixed>|null hydrated post (ingredients, steps, media, tags) */
    public static function find(array $contributor, int $id): ?array
    {
        self::load();

        return \ecp_ht_find_owned((int) $contributor['id'], $id);
    }

    public static function isEditable(array $tip): bool
    {
        self::load();

        return \ecp_ht_is_editable($tip);
    }

    public static function canSubmit(array $contributor): bool
    {
        self::load();

        return \ecp_ht_can_submit($contributor);
    }

    /**
     * @param array<string,mixed> $post  $_POST
     * @param array<string,mixed> $files $_FILES['photos'] (multi)
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function save(array $contributor, ?int $id, array $post, array $files, bool $submit): array
    {
        self::load();
        $res = \ecp_ht_save($contributor, $id, $post, $files, $submit);
        if ($res['ok'] && $submit) {
            \ecp_ht_notify_admin_submitted($contributor, (int) $res['id']);
        }

        return $res;
    }

    /** @return array{ok:bool,error?:string} */
    public static function withdraw(array $contributor, int $id): array
    {
        self::load();

        return \ecp_ht_withdraw((int) $contributor['id'], $id);
    }

    /** @return array{ok:bool,error?:string} */
    public static function delete(array $contributor, int $id): array
    {
        self::load();

        return \ecp_ht_delete((int) $contributor['id'], $id);
    }

    /** @return list<array<string,mixed>> */
    public static function tags(string $group): array
    {
        self::load();

        return \ecp_ht_tags($group);
    }

    /** Photo URL on the main site (drafts are served to their owner via the shared login cookie). */
    public static function imageUrl(int $mediaId): string
    {
        self::load();

        return CreatorService::siteUrl(\ecp_ht_image_url($mediaId));
    }

    public static function publicUrl(array $tip): string
    {
        self::load();

        return CreatorService::siteUrl(\ecp_ht_post_url($tip));
    }

    /** Free posts this month for one type + whether this post already holds a charge. @return array{limit:int,used:int,balance:int,counted:bool}|null */
    public static function quotaHint(array $creator, string $type, ?int $id): ?array
    {
        if (!Quota::enabled()) {
            return null;
        }
        $q = Quota::status((int) $creator['id'], [$type]);
        $counted = false;
        if ($id) {
            $st = \App\Core\Database::connection()->prepare("SELECT status FROM wh_post_charges WHERE item_type = 'tip' AND item_id = :i LIMIT 1");
            $st->execute(['i' => $id]);
            $counted = in_array($st->fetchColumn(), ['reserved', 'used'], true);
        }

        return ['limit' => (int) ($q['types'][$type]['limit'] ?? 0), 'used' => (int) ($q['types'][$type]['used'] ?? 0), 'balance' => (int) $q['balance'], 'counted' => $counted];
    }

    // ---- Form state (ported from my-health-tips.php) ---------------------------

    /** @return array<string,mixed> */
    public static function blank(string $type): array
    {
        return [
            'id' => 0, 'type' => $type, 'status' => 'draft', 'slug' => '', 'review_note' => null,
            'title' => '', 'summary' => '', 'body' => '', 'video_url' => '',
            'servings' => '', 'prep_minutes' => '', 'cook_minutes' => '', 'protein_g' => '', 'calories_kcal' => '',
            'duration_minutes' => '', 'difficulty' => '', 'reps_sets' => '', 'equipment' => '', 'body_area' => '', 'precautions' => '',
            'ingredients' => [], 'steps' => [], 'media' => [], 'tag_ids' => [],
        ];
    }

    /** @return array<string,mixed> */
    public static function fromTip(array $tip): array
    {
        $f = self::blank((string) $tip['type']);
        foreach ($f as $k => $_) {
            if (array_key_exists($k, $tip) && !is_array($tip[$k])) {
                $f[$k] = $tip[$k] ?? '';
            }
        }
        $f['id'] = (int) $tip['id'];
        $f['review_note'] = $tip['review_note'];
        $f['ingredients'] = array_map(static fn ($r) => ['quantity' => (string) $r['quantity'], 'item' => (string) $r['item']], $tip['ingredients']);
        $f['steps'] = array_map('strval', $tip['steps']);
        $f['media'] = $tip['media'];
        $f['tag_ids'] = array_map(static fn ($t) => (int) $t['id'], $tip['tags']);

        return $f;
    }

    /** Re-fill the form from a rejected POST so nothing typed is lost. @return array<string,mixed> */
    public static function fromPost(array $post, ?array $tip, string $type): array
    {
        $f = $tip ? self::fromTip($tip) : self::blank($type);
        foreach ($f as $k => $_) {
            if (isset($post[$k]) && !is_array($post[$k]) && !in_array($k, ['id', 'type', 'status', 'slug', 'review_note'], true)) {
                $f[$k] = (string) $post[$k];
            }
        }
        $f['ingredients'] = [];
        foreach ((array) ($post['ing_item'] ?? []) as $i => $item) {
            $f['ingredients'][] = ['quantity' => (string) ($post['ing_qty'][$i] ?? ''), 'item' => (string) $item];
        }
        $f['steps'] = array_map('strval', (array) ($post['step'] ?? []));
        $f['tag_ids'] = array_map('intval', (array) ($post['tags'] ?? []));

        return $f;
    }
}
