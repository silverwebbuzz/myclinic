<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\WordPressSettings;

final class WordPressService
{
    /** @return array{ok: bool, status: int, body: mixed, error?: string} */
    public static function request(string $method, string $path, ?array $body = null): array
    {
        if (!WordPressSettings::isConfigured()) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'error' => 'WordPress API is not configured.'];
        }

        $base = WordPressSettings::apiBaseUrl();
        $url = $base . '/' . ltrim($path, '/');

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $user = WordPressSettings::apiUser();
        $pass = WordPressSettings::apiAppPassword();
        $headers[] = 'Authorization: Basic ' . base64_encode($user . ':' . $pass);

        $bridge = self::bridgeHeader();
        if ($bridge !== null) {
            $headers[] = 'X-ECP-Bridge-Token: ' . $bridge;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'error' => $curlErr ?: 'Request failed'];
        }

        $decoded = json_decode((string) $raw, true);
        $ok = $status >= 200 && $status < 300;

        return [
            'ok' => $ok,
            'status' => $status,
            'body' => $decoded ?? $raw,
            'error' => $ok ? null : self::extractError($decoded, $status),
        ];
    }

    /**
     * @return array{ok: bool, wp_user_id?: int, wp_username?: string, wp_email?: string, error?: string, linked_existing?: bool}
     */
    public static function createOrLinkAuthor(string $username, string $email, string $displayName, string $password): array
    {
        $created = self::createAuthor($username, $email, $displayName, $password);
        if ($created['ok']) {
            return $created;
        }

        $code = $created['code'] ?? '';
        if (!in_array($code, ['existing_user_login', 'existing_user_email'], true)) {
            return $created;
        }

        $existing = self::findUserBySlug($username);
        if ($existing === null && $code === 'existing_user_email') {
            $existing = self::findUserByEmail($email);
        }

        if ($existing === null) {
            return [
                'ok' => false,
                'error' => $created['error'] ?? 'WordPress user already exists but could not be found to link.',
            ];
        }

        self::ensureAuthorRole((int) $existing['id']);

        return [
            'ok' => true,
            'wp_user_id' => (int) $existing['id'],
            'wp_username' => (string) ($existing['slug'] ?? $username),
            'wp_email' => (string) ($existing['email'] ?? $email),
            'linked_existing' => true,
        ];
    }

    /**
     * @return array{ok: bool, wp_user_id?: int, wp_username?: string, wp_email?: string, error?: string, code?: string}
     */
    public static function createAuthor(string $username, string $email, string $displayName, string $password): array
    {
        $resp = self::request('POST', '/wp/v2/users', [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'name' => $displayName,
            'roles' => ['author'],
        ]);

        if (!$resp['ok'] || !is_array($resp['body'])) {
            $code = is_array($resp['body']) ? (string) ($resp['body']['code'] ?? '') : '';

            return [
                'ok' => false,
                'error' => (string) ($resp['error'] ?? 'WordPress user create failed'),
                'code' => $code,
            ];
        }

        $id = (int) ($resp['body']['id'] ?? 0);
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'WordPress returned an invalid user id.'];
        }

        return [
            'ok' => true,
            'wp_user_id' => $id,
            'wp_username' => (string) ($resp['body']['slug'] ?? $username),
            'wp_email' => (string) ($resp['body']['email'] ?? $email),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function findUserBySlug(string $slug): ?array
    {
        $resp = self::request('GET', '/wp/v2/users?slug=' . rawurlencode($slug) . '&context=edit');
        if (!$resp['ok'] || !is_array($resp['body']) || $resp['body'] === []) {
            return null;
        }

        $user = $resp['body'][0] ?? null;

        return is_array($user) ? $user : null;
    }

    /** @return array<string, mixed>|null */
    public static function findUserByEmail(string $email): ?array
    {
        $resp = self::request('GET', '/wp/v2/users?search=' . rawurlencode($email) . '&context=edit');
        if (!$resp['ok'] || !is_array($resp['body'])) {
            return null;
        }

        $needle = strtolower(trim($email));
        foreach ($resp['body'] as $user) {
            if (!is_array($user)) {
                continue;
            }
            if (strtolower((string) ($user['email'] ?? '')) === $needle) {
                return $user;
            }
        }

        return null;
    }

    public static function ensureAuthorRole(int $wpUserId): void
    {
        $resp = self::request('GET', '/wp/v2/users/' . $wpUserId . '?context=edit');
        if (!$resp['ok'] || !is_array($resp['body'])) {
            return;
        }

        $roles = $resp['body']['roles'] ?? [];
        if (!is_array($roles)) {
            $roles = [];
        }
        if (in_array('author', $roles, true) || in_array('administrator', $roles, true)) {
            return;
        }

        $roles[] = 'author';
        self::request('POST', '/wp/v2/users/' . $wpUserId, ['roles' => array_values(array_unique($roles))]);
    }

    public static function wpUserExists(int $wpUserId): bool
    {
        if ($wpUserId <= 0) {
            return false;
        }

        $resp = self::request('GET', '/wp/v2/users/' . $wpUserId);

        return $resp['ok'];
    }

    public static function deleteWpUser(int $wpUserId, int $reassign = 1): bool
    {
        if ($wpUserId <= 0) {
            return true;
        }

        $resp = self::request('DELETE', '/wp/v2/users/' . $wpUserId . '?force=true&reassign=' . $reassign);

        return $resp['ok'] || $resp['status'] === 404;
    }


    /** @return list<array<string, mixed>> */
    public static function listPostsForAuthors(array $authorIds, string $status = 'publish', int $perPage = 10): array
    {
        $authorIds = array_values(array_filter(array_map('intval', $authorIds), static fn (int $id) => $id > 0));
        if ($authorIds === []) {
            return [];
        }

        $all = [];
        foreach ($authorIds as $authorId) {
            $page = 1;
            do {
                $query = http_build_query([
                    'author' => $authorId,
                    'status' => $status,
                    'per_page' => min(20, $perPage),
                    'page' => $page,
                    '_fields' => 'id,title,excerpt,link,date,status,slug,author',
                ]);
                $resp = self::request('GET', '/wp/v2/posts?' . $query);
                if (!$resp['ok'] || !is_array($resp['body'])) {
                    break;
                }
                foreach ($resp['body'] as $post) {
                    if (is_array($post)) {
                        $all[] = self::normalizePost($post);
                    }
                }
                $count = is_array($resp['body']) ? count($resp['body']) : 0;
                $page++;
            } while ($count >= 20 && count($all) < $perPage);
        }

        usort($all, static fn (array $a, array $b) => strcmp((string) $b['date'], (string) $a['date']));

        return array_slice($all, 0, $perPage);
    }

    /** @return list<array<string, mixed>> */
    public static function listPostsForAuthor(int $authorId, string $status = 'any', int $perPage = 50): array
    {
        $query = http_build_query([
            'author' => $authorId,
            'status' => $status,
            'per_page' => min(100, $perPage),
            '_fields' => 'id,title,excerpt,link,date,status,slug,author,content',
        ]);
        $resp = self::request('GET', '/wp/v2/posts?' . $query);
        if (!$resp['ok'] || !is_array($resp['body'])) {
            return [];
        }

        $posts = [];
        foreach ($resp['body'] as $post) {
            if (is_array($post)) {
                $posts[] = self::normalizePost($post, true);
            }
        }

        return $posts;
    }

    /** @return array<string, mixed>|null */
    public static function getPost(int $postId): ?array
    {
        $resp = self::request('GET', '/wp/v2/posts/' . $postId . '?context=edit');
        if (!$resp['ok'] || !is_array($resp['body'])) {
            return null;
        }

        return self::normalizePost($resp['body'], true);
    }

    /**
     * @param array{title?: string, content?: string, status?: string, excerpt?: string} $data
     * @return array<string, mixed>|null
     */
    public static function createPost(int $authorId, array $data): ?array
    {
        $payload = [
            'title' => (string) ($data['title'] ?? ''),
            'content' => (string) ($data['content'] ?? ''),
            'status' => (string) ($data['status'] ?? 'draft'),
            'author' => $authorId,
        ];
        if (isset($data['excerpt'])) {
            $payload['excerpt'] = (string) $data['excerpt'];
        }

        $resp = self::request('POST', '/wp/v2/posts', $payload);
        if (!$resp['ok'] || !is_array($resp['body'])) {
            return null;
        }

        return self::normalizePost($resp['body'], true);
    }

    /**
     * @param array{title?: string, content?: string, status?: string, excerpt?: string} $data
     */
    public static function updatePost(int $postId, int $authorId, array $data): bool
    {
        $existing = self::getPost($postId);
        if ($existing === null || (int) ($existing['author'] ?? 0) !== $authorId) {
            return false;
        }

        $payload = [];
        foreach (['title', 'content', 'status', 'excerpt'] as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = $data[$key];
            }
        }

        $resp = self::request('POST', '/wp/v2/posts/' . $postId, $payload);

        return $resp['ok'];
    }

    public static function deletePost(int $postId, int $authorId): bool
    {
        $existing = self::getPost($postId);
        if ($existing === null || (int) ($existing['author'] ?? 0) !== $authorId) {
            return false;
        }

        $resp = self::request('DELETE', '/wp/v2/posts/' . $postId . '?force=true');

        return $resp['ok'];
    }

    public static function generateBridgeToken(): string
    {
        return 'ecp_wp_' . bin2hex(random_bytes(24));
    }

    public static function hashBridgeToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    private static function bridgeHeader(): ?string
    {
        $secret = WordPressSettings::bridgeSecret();
        if ($secret === '') {
            return null;
        }

        $ts = (string) time();

        return $ts . '.' . hash_hmac('sha256', $ts, $secret);
    }

    /** @param array<string, mixed> $post */
    private static function normalizePost(array $post, bool $withContent = false): array
    {
        $title = $post['title'] ?? '';
        if (is_array($title)) {
            $title = $title['rendered'] ?? '';
        }

        $excerpt = $post['excerpt'] ?? '';
        if (is_array($excerpt)) {
            $excerpt = $excerpt['rendered'] ?? '';
        }

        $content = $post['content'] ?? '';
        if (is_array($content)) {
            $content = $content['rendered'] ?? ($content['raw'] ?? '');
        }

        $normalized = [
            'id' => (int) ($post['id'] ?? 0),
            'title' => html_entity_decode(strip_tags((string) $title), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'excerpt' => trim(strip_tags(html_entity_decode((string) $excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
            'link' => (string) ($post['link'] ?? ''),
            'date' => (string) ($post['date'] ?? ''),
            'status' => (string) ($post['status'] ?? ''),
            'slug' => (string) ($post['slug'] ?? ''),
            'author' => (int) ($post['author'] ?? 0),
        ];

        if ($withContent) {
            $normalized['content'] = is_string($content) ? $content : '';
        }

        return $normalized;
    }

  /** @param mixed $decoded */
    private static function extractError(mixed $decoded, int $status): string
    {
        if (is_array($decoded)) {
            $msg = $decoded['message'] ?? $decoded['code'] ?? null;
            if (is_string($msg) && $msg !== '') {
                return $msg;
            }
        }

        return 'WordPress API error (HTTP ' . $status . ')';
    }
}
