<?php

declare(strict_types=1);

namespace App\Services\Wellness;

use App\Core\Database;
use App\Services\LocationCatalogService;
use App\Services\MailService;
use PDO;

/**
 * Wellness Hub — health camps & events (Phase 2). Plan: document/wellness-hub-plan.md §9.
 *
 *   draft → submitted → published | changes_requested | rejected
 *   published → (creator edits) submitted · (creator) cancelled · (admin) unpublished
 *
 * "Ended" is derived from end_at, never stored. A free camp must be free for
 * attendees (fee forced to 0); anything with a fee is an event.
 * Contact phone, registration link and online link are shown on the public page
 * to signed-in patients only (plan §8A).
 */
final class EventService
{
    public const KINDS = ['free_camp' => 'Free health camp', 'event' => 'Event'];
    public const MODES = ['in_person' => 'In person', 'online' => 'Online', 'hybrid' => 'In person + online'];
    public const AUDIENCES = ['all' => 'Everyone', 'women' => 'Women', 'children' => 'Children', 'seniors' => 'Senior citizens', 'doctors' => 'Doctors only'];
    public const JOIN_MODES = ['walk_in' => 'Just walk in', 'call' => 'Call to book a slot', 'register' => 'Register online'];
    public const STATUS_LABELS = [
        'draft' => 'Draft', 'submitted' => 'In review', 'changes_requested' => 'Changes needed', 'rejected' => 'Not accepted',
        'published' => 'Live', 'unpublished' => 'Unpublished', 'cancelled' => 'Cancelled',
    ];
    /** Creator may open the edit form in these statuses. */
    public const EDITABLE = ['draft', 'changes_requested', 'unpublished', 'published'];

    private const POSTER_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const POSTER_MAX = 3 * 1024 * 1024;

    // -----------------------------------------------------------------
    // Reference data
    // -----------------------------------------------------------------

    /** @return list<array{slug:string,name:string}> */
    public static function categories(string $kind): array
    {
        try {
            $st = Database::connection()->prepare('SELECT slug, name FROM wh_event_categories WHERE kind = :k AND is_active = 1 ORDER BY sort, name');
            $st->execute(['k' => $kind]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Active directory cities for the city picker. @return list<array{name:string,slug:string,state:string}> */
    public static function cities(): array
    {
        $out = [];
        foreach (LocationCatalogService::cities(null, true) as $c) {
            $out[] = ['name' => (string) $c['name'], 'slug' => (string) $c['slug'], 'state' => (string) ($c['state_name'] ?? $c['state'] ?? '')];
        }

        return $out;
    }

    // -----------------------------------------------------------------
    // Creator side
    // -----------------------------------------------------------------

    /** @return list<array<string,mixed>> */
    public static function forCreator(int $creatorId): array
    {
        $st = Database::connection()->prepare(
            'SELECT * FROM wh_events WHERE creator_id = :c AND is_deleted = 0 ORDER BY start_at DESC, id DESC'
        );
        $st->execute(['c' => $creatorId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed>|null */
    public static function findOwned(int $creatorId, int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM wh_events WHERE id = :id AND creator_id = :c AND is_deleted = 0 LIMIT 1');
        $st->execute(['id' => $id, 'c' => $creatorId]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Empty form, pre-filled from the creator's profile. @return array<string,mixed> */
    public static function blank(string $kind, array $creator): array
    {
        return [
            'id' => null, 'kind' => $kind, 'status' => 'draft', 'category_slug' => '', 'title' => '', 'summary' => '', 'description' => '',
            'start_date' => '', 'start_time' => '09:00', 'end_date' => '', 'end_time' => '13:00',
            'mode' => 'in_person', 'venue_name' => in_array($creator['creator_type'] ?? '', CreatorService::ORG_TYPES, true) ? (string) ($creator['org_name'] ?? '') : '',
            'address' => '', 'city_slug' => self::citySlugFor((string) ($creator['city'] ?? '')), 'city_other' => '', 'state' => (string) ($creator['state'] ?? ''),
            'pincode' => '', 'map_url' => '', 'online_url' => '', 'fee_type' => 'free', 'fee_amount' => '', 'fee_note' => '',
            'join_mode' => 'walk_in', 'register_url' => '', 'contact_name' => (string) ($creator['display_name'] ?? ''), 'contact_phone' => '',
            'audience' => 'all', 'languages' => '', 'organised_by' => '', 'poster_path' => null, 'review_note' => null,
        ];
    }

    /** DB row → form values. @return array<string,mixed> */
    public static function toForm(array $e): array
    {
        $known = self::cityBySlug((string) $e['city_slug']) !== null;

        return $e + [
            'start_date' => date('Y-m-d', strtotime((string) $e['start_at'])), 'start_time' => date('H:i', strtotime((string) $e['start_at'])),
            'end_date' => date('Y-m-d', strtotime((string) $e['end_at'])), 'end_time' => date('H:i', strtotime((string) $e['end_at'])),
            'fee_type' => (float) $e['fee_amount'] > 0 ? 'paid' : 'free',
            'city_slug' => $known ? $e['city_slug'] : ($e['city_name'] ? '_other' : ''),
            'city_other' => $known ? '' : (string) ($e['city_name'] ?? ''),
        ];
    }

    /**
     * Create or update. $submit = send for review (full checks) vs save draft.
     * $adminId set = admin posting under eClinicPro Desk: publishes at once.
     *
     * @param array<string,mixed>      $creator
     * @param array<string,mixed>      $in
     * @param array<string,mixed>|null $poster $_FILES entry
     * @return array{ok:bool,error?:string,id?:int,status?:string}
     */
    public static function save(array $creator, ?int $id, string $kind, array $in, ?array $poster, bool $submit, ?int $adminId = null): array
    {
        $existing = null;
        if ($id) {
            $existing = self::findOwned((int) $creator['id'], $id);
            if ($existing === null) {
                return ['ok' => false, 'error' => 'Listing not found.'];
            }
            if ($adminId === null && !in_array($existing['status'], self::EDITABLE, true)) {
                return ['ok' => false, 'error' => 'This listing can’t be edited right now.'];
            }
            $kind = (string) $existing['kind'];
        }
        if (!isset(self::KINDS[$kind])) {
            return ['ok' => false, 'error' => 'Choose a camp or an event.'];
        }
        if ($adminId === null && !in_array($kind, CreatorService::approvedTypes((int) $creator['id']), true)) {
            return ['ok' => false, 'error' => 'You are not approved to post ' . ($kind === 'free_camp' ? 'free camps' : 'events') . ' yet. Apply in “What I can post”.'];
        }
        // A live listing goes back to review when changed; it can't drop to a draft.
        if ($existing && $existing['status'] === 'published' && $adminId === null) {
            $submit = true;
        }

        $v = self::validate($kind, $in, $submit || $adminId !== null, $adminId !== null);
        if (isset($v['error'])) {
            return ['ok' => false, 'error' => $v['error']];
        }
        // Phase 4: sending for review needs a free monthly slot or a credit (free camps never do).
        if ($submit && $adminId === null) {
            $q = Quota::check((int) $creator['id'], 'event', $existing ? (int) $existing['id'] : null, $kind);
            if (!$q['ok']) {
                return ['ok' => false, 'error' => $q['error'] === 'no_credits'
                    ? Quota::blockedMessage((int) $creator['id'], $kind) . ' (Your changes are still in the form — or press “Save draft”.)'
                    : 'Could not check your monthly posts. Please try again.'];
            }
        }

        $posterPath = null;
        if ($poster && ($poster['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $saved = self::storePoster((int) $creator['id'], $poster);
            if (!$saved['ok']) {
                return $saved;
            }
            $posterPath = $saved['path'];
        }

        $status = $adminId !== null ? 'published' : ($submit ? 'submitted' : (($existing['status'] ?? 'draft') === 'changes_requested' ? 'changes_requested' : 'draft'));
        if ($existing && $existing['status'] === 'unpublished' && !$submit && $adminId === null) {
            $status = 'unpublished';
        }

        $db = Database::connection();
        $cols = $v['fields'];
        $cols['status'] = $status;
        if ($posterPath !== null) {
            $cols['poster_path'] = $posterPath;
        }
        if ($status === 'submitted') {
            $cols['submitted_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'published') {
            $cols['reviewed_at'] = date('Y-m-d H:i:s');
            $cols['reviewed_by'] = $adminId;
            if (empty($existing['published_at'])) {
                $cols['published_at'] = date('Y-m-d H:i:s');
            }
        }
        // Slug: frozen once the listing has ever been published.
        if (!$existing || empty($existing['published_at'])) {
            $cols['slug'] = self::uniqueSlug($cols['title'] . ' ' . ($cols['city_name'] ?? '') . ' ' . date('M Y', strtotime($cols['start_at'])), $existing ? (int) $existing['id'] : null);
        }

        if ($existing) {
            $set = [];
            $bind = ['id' => (int) $existing['id']];
            foreach ($cols as $k => $val) {
                $set[] = "`$k` = :$k";
                $bind[$k] = $val;
            }
            $db->prepare('UPDATE wh_events SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($bind);
            $eventId = (int) $existing['id'];
            if ($posterPath !== null && !empty($existing['poster_path'])) {
                self::unlinkPoster((string) $existing['poster_path']);
            }
        } else {
            $cols['creator_id'] = (int) $creator['id'];
            $cols['kind'] = $kind;
            $names = array_keys($cols);
            $db->prepare(
                'INSERT INTO wh_events (`' . implode('`, `', $names) . '`) VALUES (:' . implode(', :', $names) . ')'
            )->execute($cols);
            $eventId = (int) $db->lastInsertId();
        }

        if ($status === 'submitted') {
            $r = Quota::reserve((int) $creator['id'], 'event', $eventId, $kind);
            if (!$r['ok']) {
                $db->prepare("UPDATE wh_events SET status = IF(published_at IS NULL, 'draft', 'unpublished') WHERE id = :id")->execute(['id' => $eventId]);

                return ['ok' => false, 'error' => Quota::blockedMessage((int) $creator['id'], $kind) . ' It was saved as a draft.'];
            }
            CreatorService::notifyAdmin(
                ($kind === 'free_camp' ? 'Free camp' : 'Event') . ' waiting for review: ' . $cols['title'],
                [
                    $creator['display_name'] . ' sent a ' . ($kind === 'free_camp' ? 'free health camp' : 'event') . ' for review'
                        . ($existing && $existing['status'] === 'published' ? ' (changes to a live listing — it is hidden until you approve)' : '') . '.',
                    self::whenText($cols['start_at'], $cols['end_at']) . ($cols['city_name'] ? ' · ' . $cols['city_name'] : ' · Online'),
                ],
                '/admin/wellness/events/' . $eventId,
            );
        }
        if ($status === 'published') {
            self::clearSitemapCache();
        }

        return ['ok' => true, 'id' => $eventId, 'status' => $status];
    }

    /**
     * @param array<string,mixed> $in
     * @return array{error?:string,fields?:array<string,mixed>}
     */
    private static function validate(string $kind, array $in, bool $full, bool $isAdmin): array
    {
        $clean = static fn (string $k, int $max): ?string => CreatorService::clean((string) ($in[$k] ?? ''), $max);

        $title = $clean('title', 160);
        if ($title === null || mb_strlen($title) < 5) {
            return ['error' => 'Please add a clear title (at least 5 characters).'];
        }
        $category = (string) ($in['category_slug'] ?? '');
        if (!in_array($category, array_column(self::categories($kind), 'slug'), true)) {
            return ['error' => 'Please choose a category.'];
        }

        $start = self::dt((string) ($in['start_date'] ?? ''), (string) ($in['start_time'] ?? ''));
        $end = self::dt((string) ($in['end_date'] ?? '') ?: (string) ($in['start_date'] ?? ''), (string) ($in['end_time'] ?? ''));
        if ($start === null || $end === null) {
            return ['error' => 'Please enter the start and end date and time.'];
        }
        if ($end <= $start) {
            return ['error' => 'The end time must be after the start time.'];
        }
        if ($end - $start > 31 * 86400) {
            return ['error' => 'A listing can run for at most 31 days. For a regular weekly camp, post each date separately.'];
        }
        if ($full && $end < time()) {
            return ['error' => 'This date has already passed.'];
        }

        $mode = (string) ($in['mode'] ?? 'in_person');
        if (!isset(self::MODES[$mode])) {
            $mode = 'in_person';
        }
        $physical = $mode !== 'online';

        // City: a directory city, or "_other" + a typed name.
        $cityName = null;
        $citySlug = null;
        $state = $clean('state', 80);
        $pick = (string) ($in['city_slug'] ?? '');
        if ($pick === '_other') {
            $cityName = $clean('city_other', 80);
            $citySlug = $cityName !== null ? self::slugify($cityName) : null;
        } elseif ($pick !== '') {
            $c = self::cityBySlug($pick);
            if ($c !== null) {
                $cityName = $c['name'];
                $citySlug = $c['slug'];
                $state = $state ?: ($c['state'] ?: null);
            }
        }
        $address = $clean('address', 300);
        $pincode = preg_replace('/\D/', '', (string) ($in['pincode'] ?? '')) ?? '';
        if ($pincode !== '' && strlen($pincode) !== 6) {
            return ['error' => 'Pincode should be 6 digits.'];
        }
        $mapUrl = self::url((string) ($in['map_url'] ?? ''));
        if ($mapUrl === false) {
            return ['error' => 'The map link must be a full https:// link (e.g. from Google Maps → Share).'];
        }
        $onlineUrl = self::url((string) ($in['online_url'] ?? ''));
        if ($onlineUrl === false) {
            return ['error' => 'The online link must be a full https:// link.'];
        }

        $feeAmount = 0.0;
        if ($kind === 'event' && ($in['fee_type'] ?? 'free') === 'paid') {
            $feeAmount = round((float) preg_replace('/[^\d.]/', '', (string) ($in['fee_amount'] ?? '')), 2);
            if ($feeAmount <= 0 || $feeAmount > 100000) {
                return ['error' => 'Enter the ticket price in rupees (or choose Free).'];
            }
        }

        $joinMode = (string) ($in['join_mode'] ?? 'walk_in');
        if (!isset(self::JOIN_MODES[$joinMode])) {
            $joinMode = 'walk_in';
        }
        $registerUrl = self::url((string) ($in['register_url'] ?? ''));
        if ($registerUrl === false) {
            return ['error' => 'The registration link must be a full https:// link.'];
        }
        $phone = self::phone((string) ($in['contact_phone'] ?? ''));
        if ($phone === false) {
            return ['error' => 'Enter a valid 10-digit contact number.'];
        }

        if ($full) {
            $summary = $clean('summary', 300);
            $desc = $clean('description', 5000);
            if (mb_strlen((string) $summary . (string) $desc) < 40) {
                return ['error' => 'Please describe what happens at the ' . ($kind === 'free_camp' ? 'camp' : 'event') . ' (at least a couple of sentences).'];
            }
            if ($physical && ($address === null || $cityName === null)) {
                return ['error' => 'Please add the venue address and city.'];
            }
            if ($mode !== 'in_person' && $onlineUrl === null && $registerUrl === null) {
                return ['error' => 'For an online ' . ($kind === 'free_camp' ? 'session' : 'event') . ', add the joining link or a registration link.'];
            }
            if ($joinMode === 'register' && $registerUrl === null) {
                return ['error' => 'Add the registration link, or choose another way to join.'];
            }
            if ($phone === null) {
                return ['error' => 'Add a contact number — people who sign in can call it.'];
            }
            if (!$isAdmin && empty($in['confirm_genuine'])) {
                return ['error' => 'Please confirm the details are genuine.'];
            }
            if (!$isAdmin && $kind === 'free_camp' && empty($in['confirm_free'])) {
                return ['error' => 'Please confirm the camp is completely free for everyone who attends.'];
            }
        }

        $audience = (string) ($in['audience'] ?? 'all');

        return ['fields' => [
            'category_slug' => $category,
            'title' => $title,
            'summary' => $clean('summary', 300),
            'description' => $clean('description', 5000),
            'start_at' => date('Y-m-d H:i:s', $start),
            'end_at' => date('Y-m-d H:i:s', $end),
            'mode' => $mode,
            'venue_name' => $physical ? $clean('venue_name', 160) : null,
            'address' => $physical ? $address : null,
            'city_name' => $physical ? $cityName : null,
            'city_slug' => $physical ? $citySlug : null,
            'state' => $physical ? $state : null,
            'pincode' => $physical && $pincode !== '' ? $pincode : null,
            'map_url' => $physical ? ($mapUrl ?: null) : null,
            'online_url' => $mode !== 'in_person' ? ($onlineUrl ?: null) : null,
            'fee_amount' => $feeAmount,
            'fee_note' => $kind === 'event' ? $clean('fee_note', 160) : null,
            'join_mode' => $joinMode,
            'register_url' => $registerUrl ?: null,
            'contact_name' => $clean('contact_name', 120),
            'contact_phone' => $phone ?: null,
            'audience' => isset(self::AUDIENCES[$audience]) ? $audience : 'all',
            'languages' => $clean('languages', 120),
            'organised_by' => $isAdmin ? $clean('organised_by', 160) : null,
        ]];
    }

    /** @return array{ok:bool,error?:string} */
    public static function withdraw(array $creator, int $id): array
    {
        $e = self::findOwned((int) $creator['id'], $id);
        if (!$e || $e['status'] !== 'submitted') {
            return ['ok' => false, 'error' => 'Only a listing waiting for review can be taken back.'];
        }
        Database::connection()->prepare("UPDATE wh_events SET status = IF(published_at IS NULL, 'draft', 'unpublished') WHERE id = :id")->execute(['id' => $id]);
        Quota::release('event', $id, 'taken back from review');

        return ['ok' => true];
    }

    /** Creator cancels a live listing (it stays visible, marked Cancelled). @return array{ok:bool,error?:string} */
    public static function cancel(array $creator, int $id, string $reason): array
    {
        $e = self::findOwned((int) $creator['id'], $id);
        if (!$e || $e['status'] !== 'published') {
            return ['ok' => false, 'error' => 'Only a live listing can be cancelled.'];
        }
        $reason = CreatorService::clean($reason, 300);
        if ($reason === null) {
            return ['ok' => false, 'error' => 'Tell attendees why it’s cancelled (or the new date).'];
        }
        Database::connection()->prepare("UPDATE wh_events SET status = 'cancelled', cancel_reason = :r WHERE id = :id")->execute(['r' => $reason, 'id' => $id]);
        CreatorService::log((int) $creator['id'], 'event_cancelled', $e['title'] . ' — ' . $reason, null);
        self::clearSitemapCache();

        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function delete(array $creator, int $id): array
    {
        $e = self::findOwned((int) $creator['id'], $id);
        if (!$e || !in_array($e['status'], ['draft', 'rejected', 'changes_requested'], true) || !empty($e['published_at'])) {
            return ['ok' => false, 'error' => 'Only a draft that was never live can be deleted.'];
        }
        Database::connection()->prepare('UPDATE wh_events SET is_deleted = 1 WHERE id = :id')->execute(['id' => $id]);

        return ['ok' => true];
    }

    // -----------------------------------------------------------------
    // Admin
    // -----------------------------------------------------------------

    /** @return array<string,int> */
    public static function statusCounts(): array
    {
        try {
            $rows = Database::connection()->query('SELECT status, COUNT(*) n FROM wh_events WHERE is_deleted = 0 GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r['status']] = (int) $r['n'];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public static function pendingCount(): int
    {
        try {
            return (int) Database::connection()->query("SELECT COUNT(*) FROM wh_events WHERE status = 'submitted' AND is_deleted = 0")->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return list<array<string,mixed>> */
    public static function adminList(string $status, string $q): array
    {
        $where = ['e.is_deleted = 0'];
        $bind = [];
        if ($status === 'upcoming') {
            $where[] = "e.status = 'published' AND e.end_at >= NOW()";
        } elseif ($status === 'ended') {
            $where[] = "e.status = 'published' AND e.end_at < NOW()";
        } elseif (isset(self::STATUS_LABELS[$status])) {
            $where[] = 'e.status = :st';
            $bind['st'] = $status;
        }
        if (trim($q) !== '') {
            $where[] = '(e.title LIKE :qt OR e.city_name LIKE :qc OR w.display_name LIKE :qw)';
            $like = '%' . trim($q) . '%';
            $bind += ['qt' => $like, 'qc' => $like, 'qw' => $like];
        }
        $order = $status === 'submitted' ? 'e.submitted_at ASC' : 'e.start_at ' . ($status === 'ended' ? 'DESC' : 'ASC');
        $st = Database::connection()->prepare(
            'SELECT e.*, w.display_name AS creator_name, w.creator_type, w.verify_status,
                    (SELECT COUNT(*) FROM wh_item_reports r WHERE r.item_type = "event" AND r.item_id = e.id AND r.status = "open") AS open_reports
             FROM wh_events e JOIN wh_creators w ON w.id = e.creator_id
             WHERE ' . implode(' AND ', $where) . " ORDER BY $order LIMIT 300"
        );
        $st->execute($bind);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed>|null */
    public static function adminFind(int $id): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT e.*, w.display_name AS creator_name, w.creator_type, w.verify_status, w.email AS creator_email, w.slug AS creator_slug,
                    w.status AS creator_status, pi.phone AS creator_phone
             FROM wh_events e JOIN wh_creators w ON w.id = e.creator_id
             LEFT JOIN patient_identities pi ON pi.id = w.identity_id
             WHERE e.id = :id AND e.is_deleted = 0 LIMIT 1'
        );
        $st->execute(['id' => $id]);
        $e = $st->fetch(PDO::FETCH_ASSOC);
        if (!$e) {
            return null;
        }
        $r = Database::connection()->prepare("SELECT * FROM wh_item_reports WHERE item_type = 'event' AND item_id = :id ORDER BY id DESC");
        $r->execute(['id' => $id]);

        return $e + ['reports' => $r->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    /**
     * publish | changes | reject | unpublish
     *
     * @return array{ok:bool,error?:string}
     */
    public static function review(int $id, string $decision, string $note, ?int $adminId): array
    {
        $e = self::adminFind($id);
        if ($e === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $note = CreatorService::clean($note, 1000);
        $allowed = [
            'publish' => ['submitted', 'changes_requested', 'unpublished', 'rejected'],
            'changes' => ['submitted', 'published'],
            'reject' => ['submitted', 'changes_requested'],
            'unpublish' => ['published', 'cancelled'],
        ];
        if (!isset($allowed[$decision]) || !in_array($e['status'], $allowed[$decision], true)) {
            return ['ok' => false, 'error' => 'bad_decision'];
        }
        if ($decision !== 'publish' && $note === null) {
            return ['ok' => false, 'error' => 'note_required'];
        }
        $status = ['publish' => 'published', 'changes' => 'changes_requested', 'reject' => 'rejected', 'unpublish' => 'unpublished'][$decision];
        Database::connection()->prepare(
            'UPDATE wh_events SET status = :s, review_note = :n, reviewed_at = NOW(), reviewed_by = :a,
                    published_at = IF(:pub = 1 AND published_at IS NULL, NOW(), published_at)
             WHERE id = :id'
        )->execute(['s' => $status, 'n' => $note, 'a' => $adminId, 'pub' => $status === 'published' ? 1 : 0, 'id' => $id]);
        CreatorService::log((int) $e['creator_id'], 'event_' . $status, $e['title'] . ($note ? ' — ' . $note : ''), $adminId);
        if ($status === 'published') {
            Quota::finalize('event', $id);
        } elseif ($status === 'rejected') {
            Quota::release('event', $id, 'not accepted');
        }
        self::clearSitemapCache();

        if (in_array($status, ['published', 'changes_requested', 'rejected'], true) && trim((string) $e['creator_email']) !== '') {
            try {
                MailService::send((string) $e['creator_email'], 'wellness_event_decision', [
                    'name' => (string) $e['creator_name'],
                    'decision' => $status,
                    'title' => (string) $e['title'],
                    'kind_label' => $e['kind'] === 'free_camp' ? 'free health camp' : 'event',
                    'note' => (string) $note,
                    'url' => self::publicUrl($e),
                    'studio_url' => CreatorService::appUrl('/creator/events/' . $id),
                ]);
            } catch (\Throwable $ex) {
                error_log('[wellness event notify] ' . $ex->getMessage());
            }
        }

        return ['ok' => true];
    }

    /** The "eClinicPro Desk" creator the admin posts under. @return array<string,mixed>|null */
    public static function deskCreator(): ?array
    {
        $st = Database::connection()->prepare("SELECT * FROM wh_creators WHERE slug = 'eclinicpro-desk' LIMIT 1");
        $st->execute();

        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function reports(string $status): array
    {
        $st = Database::connection()->prepare(
            "SELECT r.*, e.title, e.slug, e.kind, e.status AS event_status, pi.phone AS reporter_phone
             FROM wh_item_reports r
             LEFT JOIN wh_events e ON r.item_type = 'event' AND e.id = r.item_id
             LEFT JOIN patient_identities pi ON pi.id = r.identity_id
             WHERE r.status = :s ORDER BY r.id DESC LIMIT 300"
        );
        $st->execute(['s' => in_array($status, ['open', 'resolved', 'dismissed'], true) ? $status : 'open']);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function openReportCount(): int
    {
        try {
            return (int) Database::connection()->query("SELECT COUNT(*) FROM wh_item_reports WHERE status = 'open'")->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function resolveReport(int $id, string $status, string $note, ?int $adminId): void
    {
        if (!in_array($status, ['resolved', 'dismissed'], true)) {
            return;
        }
        Database::connection()->prepare(
            'UPDATE wh_item_reports SET status = :s, admin_note = :n, resolved_at = NOW(), resolved_by = :a WHERE id = :id'
        )->execute(['s' => $status, 'n' => CreatorService::clean($note, 500), 'a' => $adminId, 'id' => $id]);
    }

    // -----------------------------------------------------------------
    // Poster
    // -----------------------------------------------------------------

    /**
     * Posters live in storage/wellness/posters/{creator}/ (not web-readable);
     * the main site serves them via api/wellness_image.php once published.
     *
     * @return array{ok:bool,error?:string,path?:string}
     */
    private static function storePoster(int $creatorId, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => 'The poster failed to upload. Please try again.'];
        }
        if ((int) ($file['size'] ?? 0) > self::POSTER_MAX) {
            return ['ok' => false, 'error' => 'Poster must be under 3 MB.'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, (string) $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!isset(self::POSTER_MIMES[$mime])) {
            return ['ok' => false, 'error' => 'Poster must be JPG, PNG or WebP.'];
        }
        $rel = 'storage/wellness/posters/' . $creatorId;
        $dir = self::repoRoot() . '/' . $rel;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not save the poster. Please try again.'];
        }
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . self::POSTER_MIMES[$mime];
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Could not save the poster. Please try again.'];
        }

        return ['ok' => true, 'path' => $rel . '/' . $name];
    }

    public static function posterResponse(array $e): \App\Http\Response
    {
        $base = realpath(self::repoRoot() . '/storage/wellness/posters');
        $abs = !empty($e['poster_path']) ? realpath(self::repoRoot() . '/' . $e['poster_path']) : false;
        if ($base === false || $abs === false || !str_starts_with($abs, $base . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return \App\Http\Response::html('Not found', 404);
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = array_search($ext === 'jpeg' ? 'jpg' : $ext, self::POSTER_MIMES, true) ?: 'application/octet-stream';

        return new \App\Http\Response((string) file_get_contents($abs), 200, [
            'Content-Type' => (string) $mime, 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function unlinkPoster(string $rel): void
    {
        $base = realpath(self::repoRoot() . '/storage/wellness/posters');
        $abs = realpath(self::repoRoot() . '/' . $rel);
        if ($base !== false && $abs !== false && str_starts_with($abs, $base . DIRECTORY_SEPARATOR) && is_file($abs)) {
            @unlink($abs);
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    public static function publicUrl(array $e): string
    {
        return CreatorService::siteUrl('/wellness/' . ($e['kind'] === 'free_camp' ? 'camp' : 'event') . '/' . $e['slug']);
    }

    /** "Sat, 14 Dec 2026 · 09:00 AM – 01:00 PM" (Indian format). */
    public static function whenText(string $start, string $end): string
    {
        $s = strtotime($start);
        $e = strtotime($end);
        if (date('Y-m-d', $s) === date('Y-m-d', $e)) {
            return date('D, d M Y', $s) . ' · ' . date('h:i A', $s) . ' – ' . date('h:i A', $e);
        }

        return date('d M Y, h:i A', $s) . ' – ' . date('d M Y, h:i A', $e);
    }

    /** @return array{name:string,slug:string,state:string}|null */
    private static function cityBySlug(string $slug): ?array
    {
        if ($slug === '' || $slug === '_other') {
            return null;
        }
        foreach (self::cities() as $c) {
            if ($c['slug'] === $slug) {
                return $c;
            }
        }

        return null;
    }

    private static function citySlugFor(string $name): string
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return '';
        }
        foreach (self::cities() as $c) {
            if (strtolower($c['name']) === $name) {
                return $c['slug'];
            }
        }

        return '';
    }

    private static function dt(string $date, string $time): ?int
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            return null;
        }
        $ts = strtotime($date . ' ' . $time . ':00');

        return $ts === false ? null : $ts;
    }

    /** '' → '', valid https URL → URL, anything else → false. */
    private static function url(string $raw): string|false
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        return filter_var($raw, FILTER_VALIDATE_URL) && preg_match('#^https://#i', $raw) ? mb_substr($raw, 0, 500) : false;
    }

    /** '' → null, valid Indian number → +91XXXXXXXXXX, else false. */
    private static function phone(string $raw): string|null|false
    {
        $d = preg_replace('/\D/', '', $raw) ?? '';
        if ($d === '') {
            return null;
        }
        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            $d = substr($d, 2);
        } elseif (strlen($d) === 11 && $d[0] === '0') {
            $d = substr($d, 1);
        }
        if (strlen($d) === 10) {
            return '+91' . $d;
        }
        return false;   // a landline with STD code (0261 2345678) becomes 10 digits above and passes
    }

    private static function uniqueSlug(string $text, ?int $selfId): string
    {
        $base = substr(self::slugify($text), 0, 180) ?: 'health-camp';
        $st = Database::connection()->prepare('SELECT id FROM wh_events WHERE slug = :s LIMIT 1');
        $slug = $base;
        for ($n = 2; $n < 500; $n++) {
            $st->execute(['s' => $slug]);
            $owner = $st->fetchColumn();
            if ($owner === false || ($selfId !== null && (int) $owner === $selfId)) {
                return $slug;
            }
            $slug = $base . '-' . $n;
        }

        return $base . '-' . bin2hex(random_bytes(3));
    }

    private static function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($t !== false) {
                $s = $t;
            }
        }

        return trim(preg_replace('/[^a-z0-9]+/', '-', $s) ?? '', '-');
    }

    private static function clearSitemapCache(): void
    {
        @unlink(self::repoRoot() . '/storage/cache/sitemap.xml');
    }

    private static function repoRoot(): string
    {
        return dirname(__DIR__, 4);
    }
}
