<?php

declare(strict_types=1);

namespace App\Services\Wellness;

use App\Core\Database;
use App\Services\MailService;
use PDO;

/**
 * App-side door to /partials/wellness_comments.php (the same rules the public
 * pages use): Creator Studio "Questions" inbox and the admin moderation queue.
 */
final class Comments
{
    private static bool $loaded = false;

    private static function db(): PDO
    {
        if (!self::$loaded) {
            require_once dirname(__DIR__, 4) . '/partials/wellness_comments.php';
            self::$loaded = true;
        }

        return Database::connection();
    }

    public static function enabled(): bool
    {
        return \wh_c_enabled(self::db());
    }

    /** SQL: item belongs to this creator (tips via legacy contributor, events via creator_id). */
    private static function ownedSql(): string
    {
        return "((c.item_type = 'tip' AND c.item_id IN (SELECT t.id FROM health_tips t JOIN wh_creators w ON w.legacy_contributor_id = t.contributor_id WHERE w.id = :cr1))
              OR (c.item_type = 'event' AND c.item_id IN (SELECT e.id FROM wh_events e WHERE e.creator_id = :cr2)))";
    }

    /** Top-level questions on the creator's posts with no visible creator answer yet. */
    public static function unansweredCount(int $creatorId): int
    {
        try {
            if (!self::enabled()) {
                return 0;
            }
            $st = self::db()->prepare(
                "SELECT COUNT(*) FROM wh_comments c WHERE c.kind = 'question' AND c.status = 'visible' AND " . self::ownedSql() . "
                   AND NOT EXISTS (SELECT 1 FROM wh_comments a WHERE a.parent_id = c.id AND a.is_creator_answer = 1 AND a.status IN ('visible','held'))"
            );
            $st->execute(['cr1' => $creatorId, 'cr2' => $creatorId]);

            return (int) $st->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Inbox: top-level comments / questions on the creator's posts, with replies.
     *
     * @return list<array<string,mixed>>
     */
    public static function inbox(int $creatorId, string $filter): array
    {
        $db = self::db();
        $extra = $filter === 'unanswered'
            ? " AND c.kind = 'question' AND NOT EXISTS (SELECT 1 FROM wh_comments a WHERE a.parent_id = c.id AND a.is_creator_answer = 1 AND a.status IN ('visible','held'))"
            : '';
        $st = $db->prepare(
            "SELECT c.*, pi.name AS p_name, pi.first_name AS p_first, pi.last_name AS p_last,
                    w.display_name AS cr_name, w.creator_type AS cr_type, w.verify_status AS cr_verify, w.slug AS cr_slug
             FROM wh_comments c
             JOIN patient_identities pi ON pi.id = c.identity_id
             LEFT JOIN wh_creators w ON w.id = c.author_creator_id
             WHERE c.parent_id IS NULL AND c.status IN ('visible','held') AND " . self::ownedSql() . $extra . "
             ORDER BY c.id DESC LIMIT 100"
        );
        $st->execute(['cr1' => $creatorId, 'cr2' => $creatorId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $rep = $db->prepare(
            "SELECT c.*, pi.name AS p_name, pi.first_name AS p_first, pi.last_name AS p_last,
                    w.display_name AS cr_name, w.creator_type AS cr_type, w.verify_status AS cr_verify, w.slug AS cr_slug
             FROM wh_comments c JOIN patient_identities pi ON pi.id = c.identity_id
             LEFT JOIN wh_creators w ON w.id = c.author_creator_id
             WHERE c.parent_id = :p AND c.status IN ('visible','held') ORDER BY c.id"
        );
        foreach ($rows as &$r) {
            $r['author'] = \wh_c_author($r);
            $r['item'] = \wh_c_item($db, (string) $r['item_type'], (int) $r['item_id']);
            $rep->execute(['p' => (int) $r['id']]);
            $r['replies'] = array_map(static fn ($x) => $x + ['author' => \wh_c_author($x)], $rep->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }
        unset($r);

        return $rows;
    }

    /** Creator answers from the Studio (posted as their own patient identity). @return array{ok:bool,error?:string,held?:bool} */
    public static function reply(int $identityId, int $parentId, string $body): array
    {
        $db = self::db();
        $st = $db->prepare('SELECT item_type, item_id FROM wh_comments WHERE id = :id LIMIT 1');
        $st->execute(['id' => $parentId]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            return ['ok' => false, 'error' => 'Not found.'];
        }
        $res = \wh_c_post($db, $identityId, (string) $p['item_type'], (int) $p['item_id'], 'reply', $body, $parentId);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => (string) $res['error']];
        }
        self::sendMail($res['notify'] ?? []);

        return ['ok' => true, 'held' => $res['status'] === 'held'];
    }

    public static function hideByOwner(int $commentId, int $ownerIdentityId): bool
    {
        return \wh_c_hide_by_owner(self::db(), $commentId, $ownerIdentityId);
    }

    // ---- Admin ---------------------------------------------------------------

    /** @return array{held:int,reported:int} */
    public static function adminCounts(): array
    {
        try {
            if (!self::enabled()) {
                return ['held' => 0, 'reported' => 0];
            }
            $db = self::db();

            return [
                'held' => (int) $db->query("SELECT COUNT(*) FROM wh_comments WHERE status = 'held'")->fetchColumn(),
                'reported' => (int) $db->query("SELECT COUNT(DISTINCT comment_id) FROM wh_comment_reports WHERE status = 'open'")->fetchColumn(),
            ];
        } catch (\Throwable) {
            return ['held' => 0, 'reported' => 0];
        }
    }

    /** @return list<array<string,mixed>> */
    public static function adminList(string $tab): array
    {
        $db = self::db();
        $where = match ($tab) {
            'reported' => "c.id IN (SELECT comment_id FROM wh_comment_reports WHERE status = 'open')",
            'hidden' => "c.status = 'hidden'",
            'recent' => "c.status = 'visible'",
            default => "c.status = 'held'",
        };
        $rows = $db->query(
            "SELECT c.*, pi.name AS p_name, pi.first_name AS p_first, pi.last_name AS p_last, pi.phone AS p_phone,
                    w.display_name AS cr_name, w.creator_type AS cr_type, w.verify_status AS cr_verify, w.slug AS cr_slug,
                    (SELECT GROUP_CONCAT(r.reason) FROM wh_comment_reports r WHERE r.comment_id = c.id AND r.status = 'open') AS open_reasons
             FROM wh_comments c JOIN patient_identities pi ON pi.id = c.identity_id
             LEFT JOIN wh_creators w ON w.id = c.author_creator_id
             WHERE $where ORDER BY c.id DESC LIMIT 200"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['author'] = \wh_c_author($r);
            $r['item'] = \wh_c_item($db, (string) $r['item_type'], (int) $r['item_id']);
        }
        unset($r);

        return $rows;
    }

    /** approve | hide | delete | dismiss (reports). */
    public static function moderate(int $id, string $action): void
    {
        $db = self::db();
        if ($action === 'approve') {
            $st = $db->prepare("UPDATE wh_comments SET status = 'visible', hold_reason = NULL WHERE id = :id AND status IN ('held','hidden')");
            $st->execute(['id' => $id]);
            $db->prepare("UPDATE wh_comment_reports SET status = 'dismissed' WHERE comment_id = :id AND status = 'open'")->execute(['id' => $id]);
            if ($st->rowCount() === 1) {
                self::sendMail(\wh_c_notifications($db, $id));   // the emails it would have sent when posted
            }
        } elseif ($action === 'hide' || $action === 'delete') {
            $db->prepare("UPDATE wh_comments SET status = :s, hidden_by = 'admin' WHERE id = :id")->execute(['s' => $action === 'hide' ? 'hidden' : 'deleted', 'id' => $id]);
            $db->prepare("UPDATE wh_comment_reports SET status = 'resolved' WHERE comment_id = :id AND status = 'open'")->execute(['id' => $id]);
        } elseif ($action === 'dismiss') {
            $db->prepare("UPDATE wh_comment_reports SET status = 'dismissed' WHERE comment_id = :id AND status = 'open'")->execute(['id' => $id]);
        }
    }

    /** @param list<array{type:string,email:string,name:string,title:string,path:string,excerpt:string}> $list */
    public static function sendMail(array $list): void
    {
        foreach ($list as $n) {
            try {
                MailService::send($n['email'], $n['type'] === 'new_question' ? 'wellness_new_question' : 'wellness_answered', [
                    'name' => $n['name'], 'title' => $n['title'], 'excerpt' => $n['excerpt'],
                    'url' => CreatorService::siteUrl($n['path']),
                    'studio_url' => CreatorService::appUrl('/creator/questions'),
                ]);
            } catch (\Throwable $e) {
                error_log('[wellness comments mail] ' . $e->getMessage());
            }
        }
    }
}
