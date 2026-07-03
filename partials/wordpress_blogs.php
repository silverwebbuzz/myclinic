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

/** @return list<array{title: string, excerpt: string, link: string, date: string}> */
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
    $claimedTenantId = (int) ($row['claimed_tenant_id'] ?? 0);
    if ($claimedTenantId <= 0) {
        return [];
    }

    try {
        $db->query('SELECT 1 FROM wordpress_doctor_links LIMIT 1');
    } catch (Throwable) {
        return [];
    }

    if ($entityType === 'doctor') {
        $directoryId = (int) ($row['id'] ?? 0);
        $stmt = $db->prepare(
            "SELECT wp_user_id FROM wordpress_doctor_links
             WHERE status = 'active'
               AND (directory_doctor_id = :did OR (clinic_id = :cid AND directory_doctor_id IS NULL))
             LIMIT 5"
        );
        $stmt->execute(['did' => $directoryId, 'cid' => $claimedTenantId]);
    } else {
        $stmt = $db->prepare(
            "SELECT wp_user_id FROM wordpress_doctor_links
             WHERE status = 'active' AND clinic_id = :cid"
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

/** @param list<int> $authorIds @return list<array{title: string, excerpt: string, link: string, date: string}> */
function ecp_wordpress_fetch_posts(array $authorIds, int $limit = 6): array
{
    $base = rtrim(ecp_wordpress_setting('wordpress_api_url'), '/');
    $user = ecp_wordpress_setting('wordpress_api_user');
    $pass = str_replace(' ', '', ecp_wordpress_setting('wordpress_api_app_password'));
    $secret = ecp_wordpress_setting('wordpress_bridge_secret');

    $all = [];
    foreach ($authorIds as $authorId) {
        $query = http_build_query([
            'author' => $authorId,
            'status' => 'publish',
            'per_page' => min(10, $limit),
            '_fields' => 'id,title,excerpt,link,date',
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
            $title = $post['title'] ?? '';
            if (is_array($title)) {
                $title = $title['rendered'] ?? '';
            }
            $excerpt = $post['excerpt'] ?? '';
            if (is_array($excerpt)) {
                $excerpt = $excerpt['rendered'] ?? '';
            }
            $all[] = [
                'title' => html_entity_decode(strip_tags((string) $title), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'excerpt' => trim(strip_tags(html_entity_decode((string) $excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                'link' => (string) ($post['link'] ?? ''),
                'date' => (string) ($post['date'] ?? ''),
            ];
        }
    }

    usort($all, static fn (array $a, array $b) => strcmp($b['date'], $a['date']));

    return array_slice($all, 0, $limit);
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
