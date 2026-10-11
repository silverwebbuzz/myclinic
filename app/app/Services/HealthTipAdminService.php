<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * HealthTipAdminService — admin side of Health Tips (/admin/health-tips).
 *
 * Contributors are patient identities the admin grants access to; their posts
 * (recipes / exercises / quick tips) come here for review before going live
 * on the public site. Data layer for the public site + contributor studio is
 * partials/health_tips.php (same tables, same database).
 *
 * Photos live in repo-root storage/health_tips/{contributor_id}/.
 */
final class HealthTipAdminService
{
    public const PER_PAGE = 50;

    public const STATUSES = [
        'submitted'         => 'Waiting for review',
        'changes_requested' => 'Changes requested',
        'published'         => 'Live',
        'unpublished'       => 'Unpublished',
        'rejected'          => 'Rejected',
        'draft'             => 'Drafts',
    ];

    public const TYPES = ['recipe' => 'Recipe', 'exercise' => 'Exercise', 'tip' => 'Quick tip'];

    /** @return array<string,int> status => count (not deleted) */
    public static function statusCounts(): array
    {
        $counts = array_fill_keys(array_keys(self::STATUSES), 0);
        try {
            $rows = Database::connection()
                ->query('SELECT status, COUNT(*) AS n FROM health_tips WHERE is_deleted = 0 GROUP BY status')
                ->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $counts[$r['status']] = (int) $r['n'];
            }
        } catch (\Throwable) {
            // patch not run yet
        }
        return $counts;
    }

    public static function pendingCount(): int
    {
        try {
            return (int) Database::connection()
                ->query("SELECT COUNT(*) FROM health_tips WHERE status = 'submitted' AND is_deleted = 0")
                ->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return list<array<string,mixed>> */
    public static function listByStatus(string $status, int $page): array
    {
        if (!isset(self::STATUSES[$status])) {
            $status = 'submitted';
        }
        // Oldest first while waiting (fair queue); newest first elsewhere.
        $order = $status === 'submitted' ? 't.submitted_at ASC, t.id ASC' : 't.updated_at DESC, t.id DESC';
        $st = Database::connection()->prepare(
            'SELECT t.id, t.type, t.title, t.slug, t.status, t.submitted_at, t.reviewed_at, t.published_at,
                    t.view_count, t.updated_at, c.id AS contributor_id, c.display_name, c.status AS contributor_status,
                    (SELECT m.id FROM health_tip_media m WHERE m.tip_id = t.id ORDER BY m.sort, m.id LIMIT 1) AS cover_id
             FROM health_tips t JOIN health_tip_contributors c ON c.id = t.contributor_id
             WHERE t.status = :s AND t.is_deleted = 0
             ORDER BY ' . $order . ' LIMIT :lim OFFSET :off'
        );
        $st->bindValue(':s', $status);
        $st->bindValue(':lim', self::PER_PAGE, PDO::PARAM_INT);
        $st->bindValue(':off', (max(1, $page) - 1) * self::PER_PAGE, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed>|null one post with everything the review screen needs */
    public static function find(int $id): ?array
    {
        $db = Database::connection();
        $st = $db->prepare(
            'SELECT t.*, c.display_name, c.slug AS contributor_slug, c.status AS contributor_status,
                    c.identity_id, c.instagram_handle, c.terms_accepted_at,
                    pi.name AS identity_name, pi.phone AS identity_phone, pi.email AS identity_email
             FROM health_tips t
             JOIN health_tip_contributors c ON c.id = t.contributor_id
             LEFT JOIN patient_identities pi ON pi.id = c.identity_id
             WHERE t.id = :id AND t.is_deleted = 0 LIMIT 1'
        );
        $st->execute([':id' => $id]);
        $tip = $st->fetch(PDO::FETCH_ASSOC);
        if (!$tip) {
            return null;
        }
        $q = static function (string $sql) use ($db, $id): array {
            $s = $db->prepare($sql);
            $s->execute([':t' => $id]);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        };
        $tip['ingredients'] = $q('SELECT quantity, item FROM health_tip_ingredients WHERE tip_id = :t ORDER BY sort, id');
        $tip['steps'] = array_column($q('SELECT body FROM health_tip_steps WHERE tip_id = :t ORDER BY sort, id'), 'body');
        $tip['media'] = $q('SELECT id, file_mime FROM health_tip_media WHERE tip_id = :t ORDER BY sort, id');
        $tip['tags'] = $q(
            'SELECT g.name, g.tag_group FROM health_tip_tag_map m JOIN health_tip_tags g ON g.id = m.tag_id
             WHERE m.tip_id = :t ORDER BY g.tag_group, g.sort'
        );
        return $tip;
    }

    /**
     * Apply a review decision.
     *   publish   submitted|changes_requested|unpublished → published
     *   changes   submitted|published → changes_requested (note required)
     *   reject    submitted|changes_requested → rejected (note required)
     *   unpublish published → unpublished
     *
     * @return array{ok:bool,error?:string}
     */
    public static function review(int $id, string $decision, string $note, ?int $adminId): array
    {
        $tip = self::find($id);
        if ($tip === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $from = (string) $tip['status'];
        $map = [
            'publish'   => [['submitted', 'changes_requested', 'unpublished'], 'published'],
            'changes'   => [['submitted', 'published'], 'changes_requested'],
            'reject'    => [['submitted', 'changes_requested'], 'rejected'],
            'unpublish' => [['published'], 'unpublished'],
        ];
        if (!isset($map[$decision]) || !in_array($from, $map[$decision][0], true)) {
            return ['ok' => false, 'error' => 'invalid_transition'];
        }
        $note = trim($note);
        if (in_array($decision, ['changes', 'reject'], true) && $note === '') {
            return ['ok' => false, 'error' => 'note_required'];
        }

        $sql = 'UPDATE health_tips SET status = :to, review_note = :note, reviewed_at = NOW(), reviewed_by = :admin';
        if ($decision === 'publish') {
            $sql .= ', published_at = COALESCE(published_at, NOW())';
        }
        Database::connection()->prepare($sql . ' WHERE id = :id')->execute([
            ':to'    => $map[$decision][1],
            ':note'  => $note !== '' ? mb_substr($note, 0, 1000) : null,
            ':admin' => $adminId,
            ':id'    => $id,
        ]);
        self::clearSitemapCache();
        self::notifyContributor($tip, $decision, $note);
        return ['ok' => true];
    }

    /**
     * Email the contributor the outcome (publish / changes / reject). Skipped
     * silently when their account has no email — MailService guards that too.
     */
    private static function notifyContributor(array $tip, string $decision, string $note): void
    {
        $template = [
            'publish' => 'health_tip_published',
            'changes' => 'health_tip_changes',
            'reject'  => 'health_tip_rejected',
        ][$decision] ?? null;
        $email = trim((string) ($tip['identity_email'] ?? ''));
        if ($template === null || $email === '') {
            return;
        }
        try {
            MailService::send($email, $template, [
                'name'       => (string) $tip['display_name'],
                'title'      => (string) $tip['title'],
                'type_label' => strtolower(self::TYPES[$tip['type']] ?? 'post'),
                'note'       => $note,
                'url'        => self::siteUrl('/health-tips/' . $tip['slug']),
                'studio_url' => self::siteUrl('/my-health-tips?edit=' . (int) $tip['id']),
            ]);
        } catch (\Throwable $e) {
            error_log('[health tips] notify failed: ' . $e->getMessage());   // never block the review
        }
    }

    /** Absolute path of a post photo for the admin preview, or null. */
    public static function mediaFile(int $mediaId): ?array
    {
        $st = Database::connection()->prepare('SELECT file_path, file_mime FROM health_tip_media WHERE id = :id LIMIT 1');
        $st->execute([':id' => $mediaId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $base = realpath(self::repoRoot() . '/storage/health_tips') ?: '';
        $real = realpath(self::repoRoot() . '/' . $row['file_path']) ?: '';
        if ($base === '' || $real === '' || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) {
            return null;
        }
        return ['path' => $real, 'mime' => (string) $row['file_mime']];
    }

    // -----------------------------------------------------------------
    // Contributors
    // -----------------------------------------------------------------

    /** @return list<array<string,mixed>> */
    public static function contributors(): array
    {
        try {
            return Database::connection()->query(
                "SELECT c.*, pi.name AS identity_name, pi.phone AS identity_phone,
                        (SELECT COUNT(*) FROM health_tips t WHERE t.contributor_id = c.id AND t.is_deleted = 0 AND t.status = 'published') AS live_count,
                        (SELECT COUNT(*) FROM health_tips t WHERE t.contributor_id = c.id AND t.is_deleted = 0 AND t.status = 'submitted') AS pending_count,
                        (SELECT COUNT(*) FROM health_tips t WHERE t.contributor_id = c.id AND t.is_deleted = 0) AS total_count
                 FROM health_tip_contributors c
                 LEFT JOIN patient_identities pi ON pi.id = c.identity_id
                 ORDER BY c.status = 'active' DESC, c.display_name"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string,mixed>|null */
    public static function contributorForIdentity(int $identityId): ?array
    {
        try {
            $st = Database::connection()->prepare('SELECT * FROM health_tip_contributors WHERE identity_id = :i LIMIT 1');
            $st->execute([':i' => $identityId]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            return null;   // patch not run yet
        }
    }

    /**
     * Find patient identities by phone (last 10 digits) — for the grant form.
     *
     * @return list<array<string,mixed>>
     */
    public static function findIdentitiesByPhone(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 10) {
            return [];
        }
        $st = Database::connection()->prepare(
            'SELECT id, name, phone, email, created_at FROM patient_identities
             WHERE is_active = 1 AND phone LIKE :p ORDER BY id LIMIT 5'
        );
        $st->execute([':p' => '%' . substr($digits, -10)]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Grant contributor access to an identity (or re-activate a suspended one).
     *
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function grant(int $identityId, ?string $note, ?int $adminId): array
    {
        $db = Database::connection();
        $st = $db->prepare('SELECT id, name FROM patient_identities WHERE id = :id AND is_active = 1 LIMIT 1');
        $st->execute([':id' => $identityId]);
        $identity = $st->fetch(PDO::FETCH_ASSOC);
        if (!$identity) {
            return ['ok' => false, 'error' => 'identity_not_found'];
        }

        $existing = self::contributorForIdentity($identityId);
        if ($existing) {
            $db->prepare("UPDATE health_tip_contributors SET status = 'active' WHERE id = :id")
               ->execute([':id' => (int) $existing['id']]);
            return ['ok' => true, 'id' => (int) $existing['id']];
        }

        $name = trim((string) $identity['name']) ?: 'Contributor';
        $db->prepare(
            "INSERT INTO health_tip_contributors (identity_id, slug, display_name, status, granted_by, granted_at, admin_note)
             VALUES (:i, :slug, :name, 'active', :admin, NOW(), :note)"
        )->execute([
            ':i'     => $identityId,
            ':slug'  => self::uniqueSlug($name),
            ':name'  => mb_substr($name, 0, 120),
            ':admin' => $adminId,
            ':note'  => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null,
        ]);
        return ['ok' => true, 'id' => (int) $db->lastInsertId()];
    }

    public static function setStatus(int $contributorId, string $status): void
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            return;
        }
        Database::connection()->prepare('UPDATE health_tip_contributors SET status = :s WHERE id = :id')
            ->execute([':s' => $status, ':id' => $contributorId]);
        self::clearSitemapCache();
    }

    // -----------------------------------------------------------------

    private static function uniqueSlug(string $name): string
    {
        $base = strtolower(trim($name));
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
            if ($t !== false) {
                $base = $t;
            }
        }
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', $base) ?? '', '-');
        $base = $base === '' ? 'contributor' : substr($base, 0, 60);

        $st = Database::connection()->prepare('SELECT 1 FROM health_tip_contributors WHERE slug = :s LIMIT 1');
        $slug = $base;
        for ($n = 2; $n < 500; $n++) {
            $st->execute([':s' => $slug]);
            if ($st->fetchColumn() === false) {
                return $slug;
            }
            $slug = $base . '-' . $n;
        }
        return $base . '-' . bin2hex(random_bytes(3));
    }

    /** Public marketing-site URL, e.g. https://eclinicpro.com/health-tips/x */
    public static function siteUrl(string $path = ''): string
    {
        return rtrim((string) ($_ENV['SITE_URL'] ?? 'https://eclinicpro.com'), '/') . $path;
    }

    private static function repoRoot(): string
    {
        return dirname(__DIR__, 3); // app/app/Services → repo root
    }

    /** The public sitemap caches for 6h; drop it so publish/unpublish shows up sooner. */
    private static function clearSitemapCache(): void
    {
        @unlink(self::repoRoot() . '/storage/cache/sitemap.xml');
    }
}
