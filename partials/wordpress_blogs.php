<?php
declare(strict_types=1);

/**
 * Marketing-site helpers for fetching published WordPress posts on directory profiles.
 * Reads credentials from platform_settings (admin UI) with .env fallback.
 */

/** @return array<string, string> */
function ecp_wordpress_config(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $keys = [
        'wordpress_api_url' => 'WORDPRESS_API_URL',
        'wordpress_site_url' => 'WORDPRESS_SITE_URL',
        'wordpress_api_user' => 'WORDPRESS_API_USER',
        'wordpress_api_app_password' => 'WORDPRESS_API_APP_PASSWORD',
        'wordpress_bridge_secret' => 'WORDPRESS_BRIDGE_SECRET',
    ];
    $map = [];

    try {
        $db = ecp_db();
        if ($db !== null) {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $db->prepare(
                "SELECT setting_key, setting_value FROM platform_settings WHERE setting_key IN ({$placeholders})"
            );
            $stmt->execute(array_keys($keys));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $v = trim((string) ($row['setting_value'] ?? ''));
                if ($v !== '') {
                    $map[$row['setting_key']] = $v;
                }
            }
        }
    } catch (Throwable) {
        // use env fallback below
    }

    foreach ($keys as $dbKey => $envKey) {
        if (empty($map[$dbKey])) {
            $map[$dbKey] = trim(ecp_env($envKey));
        }
    }

    return $map;
}

function ecp_wordpress_setting(string $key): string
{
    return ecp_wordpress_config()[$key] ?? '';
}

/** @return list<array{title: string, excerpt: string, link: string, date: string, image: string, image_alt: string, category: string, category_slug: string, author_name: string, author_avatar: string}> */
function ecp_wordpress_posts_for_listing(PDO $db, array $row, string $entityType, int $limit = 6): array
{
    ecp_wordpress_sync_stale_links($db);

    if (!ecp_wordpress_is_configured()) {
        return [];
    }

    $authorIds = ecp_wordpress_author_ids_for_listing($db, $row, $entityType);
    if ($authorIds === []) {
        return [];
    }

    return ecp_wordpress_fetch_posts($authorIds, $limit);
}

function ecp_wordpress_is_configured(): bool
{
    return ecp_wordpress_setting('wordpress_api_url') !== ''
        && ecp_wordpress_setting('wordpress_api_user') !== ''
        && ecp_wordpress_setting('wordpress_api_app_password') !== '';
}

/** @return list<int> */
function ecp_wordpress_author_ids_for_listing(PDO $db, array $row, string $entityType): array
{
    try {
        $db->query('SELECT 1 FROM wordpress_doctor_links LIMIT 1');
    } catch (Throwable) {
        return [];
    }

    if ($entityType === 'doctor') {
        $directoryId = (int) ($row['id'] ?? 0);
        if ($directoryId <= 0) {
            return [];
        }
        $stmt = $db->prepare(
            "SELECT wp_user_id FROM wordpress_doctor_links
             WHERE status = 'active' AND directory_doctor_id = :did
             LIMIT 5"
        );
        $stmt->execute(['did' => $directoryId]);
    } else {
        $claimedTenantId = (int) ($row['claimed_tenant_id'] ?? 0);
        if ($claimedTenantId <= 0) {
            return [];
        }
        $stmt = $db->prepare(
            "SELECT wdl.wp_user_id FROM wordpress_doctor_links wdl
             INNER JOIN directory_doctors dd ON dd.id = wdl.directory_doctor_id
             WHERE wdl.status = 'active' AND dd.claimed_tenant_id = :cid"
        );
        $stmt->execute(['cid' => $claimedTenantId]);
    }

    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

/** @param list<int> $authorIds @return list<array{title: string, excerpt: string, link: string, date: string, image: string, image_alt: string, category: string, category_slug: string, author_name: string, author_avatar: string}> */
function ecp_wordpress_fetch_posts(array $authorIds, int $limit = 6): array
{
    $base = rtrim(ecp_wordpress_setting('wordpress_api_url'), '/');
    $user = ecp_wordpress_setting('wordpress_api_user');
    $pass = str_replace(' ', '', ecp_wordpress_setting('wordpress_api_app_password'));
    $secret = ecp_wordpress_setting('wordpress_bridge_secret');

    $all = [];
    $seenLinks = [];
    foreach ($authorIds as $authorId) {
        $query = http_build_query([
            'author' => $authorId,
            'status' => 'publish',
            'per_page' => min(10, $limit),
            '_embed' => 'author,wp:featuredmedia,wp:term',
        ]);
        $url = $base . '/wp/v2/posts?' . $query;

        $headers = [
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($user . ':' . $pass),
        ];
        if ($secret !== '') {
            $ts = (string) time();
            $headers[] = 'X-ECP-Bridge-Token: ' . $ts . '.' . hash_hmac('sha256', $ts, $secret);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        if ($raw === false) {
            continue;
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            continue;
        }

        foreach ($decoded as $post) {
            if (!is_array($post)) {
                continue;
            }
            $shaped = ecp_wordpress_shape_post($post);
            if ($shaped === null || $shaped['link'] === '' || isset($seenLinks[$shaped['link']])) {
                continue;
            }
            $seenLinks[$shaped['link']] = true;
            $all[] = $shaped;
        }
    }

    usort($all, static fn (array $a, array $b) => strcmp($b['date'], $a['date']));

    return array_slice($all, 0, $limit);
}

/** @param array<string, mixed> $post @return array{title: string, excerpt: string, link: string, date: string, image: string, image_alt: string, category: string, category_slug: string, author_name: string, author_avatar: string}|null */
function ecp_wordpress_shape_post(array $post): ?array
{
    $title = $post['title'] ?? '';
    if (is_array($title)) {
        $title = $title['rendered'] ?? '';
    }
    $excerpt = $post['excerpt'] ?? '';
    if (is_array($excerpt)) {
        $excerpt = $excerpt['rendered'] ?? '';
    }
    $title = html_entity_decode(strip_tags((string) $title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $excerpt = trim(strip_tags(html_entity_decode((string) $excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (mb_strlen($excerpt) > 140) {
        $excerpt = rtrim(mb_substr($excerpt, 0, 137)) . '…';
    }

    $embedded = is_array($post['_embedded'] ?? null) ? $post['_embedded'] : [];
    $image = '';
    $imageAlt = $title;
    $media = $embedded['wp:featuredmedia'][0] ?? null;
    if (is_array($media)) {
        $imageAlt = trim((string) ($media['alt_text'] ?? $title));
        $sizes = is_array($media['media_details']['sizes'] ?? null) ? $media['media_details']['sizes'] : [];
        foreach (['medium_large', 'large', 'neve-blog', 'medium'] as $sizeKey) {
            if (!empty($sizes[$sizeKey]['source_url'])) {
                $image = (string) $sizes[$sizeKey]['source_url'];
                break;
            }
        }
        if ($image === '') {
            $image = (string) ($media['source_url'] ?? '');
        }
    }

    $category = '';
    $categorySlug = '';
    foreach ((array) ($embedded['wp:term'] ?? []) as $termGroup) {
        if (!is_array($termGroup)) {
            continue;
        }
        foreach ($termGroup as $term) {
            if (!is_array($term) || ($term['taxonomy'] ?? '') !== 'category') {
                continue;
            }
            $category = (string) ($term['name'] ?? '');
            $categorySlug = (string) ($term['slug'] ?? '');
            break 2;
        }
    }

    $authorName = '';
    $authorAvatar = '';
    $author = $embedded['author'][0] ?? null;
    if (is_array($author)) {
        $authorName = (string) ($author['name'] ?? '');
        $avatars = is_array($author['avatar_urls'] ?? null) ? $author['avatar_urls'] : [];
        $authorAvatar = (string) ($avatars['48'] ?? $avatars['96'] ?? $avatars['24'] ?? '');
    }

    $link = (string) ($post['link'] ?? '');
    if ($title === '' && $link === '') {
        return null;
    }

    return [
        'title'          => $title,
        'excerpt'        => $excerpt,
        'link'           => $link,
        'date'           => (string) ($post['date'] ?? ''),
        'image'          => $image,
        'image_alt'      => $imageAlt,
        'category'       => $category,
        'category_slug'  => $categorySlug,
        'author_name'    => $authorName,
        'author_avatar'  => $authorAvatar,
    ];
}

/** Revoke links when the WordPress author was deleted outside eClinicPro. */
function ecp_wordpress_sync_stale_links(PDO $db): void
{
    if (!ecp_wordpress_is_configured()) {
        return;
    }

    try {
        $db->query('SELECT 1 FROM wordpress_doctor_links LIMIT 1');
    } catch (Throwable) {
        return;
    }

    $stmt = $db->query('SELECT id, wp_user_id FROM wordpress_doctor_links WHERE status = \'active\'');
    if ($stmt === false) {
        return;
    }

    $revoke = $db->prepare('UPDATE wordpress_doctor_links SET status = \'revoked\' WHERE id = :id AND status = \'active\'');

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $link) {
        $wpUserId = (int) ($link['wp_user_id'] ?? 0);
        if ($wpUserId <= 0 || ecp_wordpress_user_exists($wpUserId)) {
            continue;
        }
        $revoke->execute(['id' => (int) $link['id']]);
    }
}

function ecp_wordpress_user_exists(int $wpUserId): bool
{
    if ($wpUserId <= 0) {
        return false;
    }

    $base = rtrim(ecp_wordpress_setting('wordpress_api_url'), '/');
    $user = ecp_wordpress_setting('wordpress_api_user');
    $pass = str_replace(' ', '', ecp_wordpress_setting('wordpress_api_app_password'));
    $secret = ecp_wordpress_setting('wordpress_bridge_secret');

    $url = $base . '/wp/v2/users/' . $wpUserId;
    $headers = [
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($user . ':' . $pass),
    ];
    if ($secret !== '') {
        $ts = (string) time();
        $headers[] = 'X-ECP-Bridge-Token: ' . $ts . '.' . hash_hmac('sha256', $ts, $secret);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_NOBODY => false,
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status >= 200 && $status < 300;
}
