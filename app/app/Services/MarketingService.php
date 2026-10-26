<?php

declare(strict_types=1);

namespace App\Services;

use App\Controllers\GetListedController;
use App\Core\Database;
use App\Support\MessagingSettings;
use PDO;

/**
 * MarketingService — the doctor list behind /admin/marketing.
 *
 * Browses ALL directory listings (not only ones with leads, unlike
 * OutreachService) with full filters, and builds a personalised WhatsApp draft
 * per doctor from an admin-editable template. Sending is always manual: the
 * admin copies the draft / opens wa.me and sends it themselves.
 *
 * "Sent" state reuses directory_outreach (last_contacted_at / contacted_count),
 * so /admin/outreach and /admin/marketing agree about who was contacted.
 *
 * Templates live in platform_settings under TEMPLATE_KEY as JSON:
 *   {"default": "...", "<specialty code>": "..."}
 */
final class MarketingService
{
    public const PER_PAGE = 50;

    public const TEMPLATE_KEY = 'marketing_wa_templates';

    /** Placeholders a template may use. */
    public const PLACEHOLDERS = [
        'doctor_name' => 'Doctor name, with "Dr." prefix',
        'specialty' => 'Specialty label, e.g. Pediatrician',
        'clinic_name' => 'Clinic / hospital name (when different from the doctor)',
        'city' => 'City',
        'area' => 'Area / locality',
        'profile_url' => 'Public eClinicPro profile link (only for live listings)',
        'website_url' => 'https://eclinicpro.com',
    ];

    public const DEFAULT_TEMPLATE = "Hello {{doctor_name}},\n\n"
        . "Greetings from eClinicPro.\n"
        . "Your [[{{specialty}} ]]profile[[ at {{clinic_name}}]][[ in {{city}}]] is listed on eClinicPro, where patients can find you and request an appointment: {{profile_url}}\n\n"
        . "Please take a look. If anything needs correcting (timings, address, phone), just reply here and we will update it.\n\n"
        . "You can also claim this listing to manage it yourself and use eClinicPro for your clinic.\n"
        . "{{website_url}}\n\n"
        . "Reply STOP if you would prefer not to hear from us.";

    /**
     * Whitelisted sort keys → ORDER BY. Every sort ends in dd.id so paging is
     * stable. 'quality' (the default) matches idx_dir_claimed_quality
     * (is_claimed, quality_score, rating, +PK), so with claimed=0 MySQL walks
     * the index and stops after one page instead of sorting the table.
     */
    private const SORTS = [
        'quality' => 'dd.quality_score %1$s, dd.rating %1$s, dd.id %1$s',
        'name' => 'dd.name %1$s, dd.id %1$s',
        'city' => 'dd.city %1$s, dd.id %1$s',
        'leads' => '(' . self::LEADS_SQL . ') %1$s, dd.id %1$s',
        'rating' => 'dd.rating %1$s, dd.id %1$s',
        'reviews' => 'dd.reviews %1$s, dd.id %1$s',
        'sent' => 'o.last_contacted_at %1$s, dd.id %1$s',
    ];

    /** Booking requests for one listing — uses the fk_dl_doctor index, per row only when needed. */
    private const LEADS_SQL = "SELECT COUNT(*) FROM directory_leads dl
                                WHERE dl.directory_doctor_id = dd.id AND dl.type = 'book_submitted'";

    /** intl_phone with spaces/dashes stripped (pre-normalisation rows have "+91 82372 89431"). */
    private const PHONE_SQL = "REPLACE(REPLACE(COALESCE(dd.intl_phone, ''), ' ', ''), '-', '')";

    // ------------------------------------------------------------------
    // List
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $f
     * @return array{where:string, bind:array<string,mixed>}
     */
    private static function filterSql(array $f): array
    {
        $f += array_fill_keys(['state', 'city', 'q', 'phone', 'claimed', 'listed', 'sent', 'sent_from', 'sent_to', 'leads'], '');
        $f += ['optout' => 'exclude'];
        $where = ['1 = 1'];
        $bind = [];
        $mobile = self::PHONE_SQL . " REGEXP '^[+]91[6-9][0-9]{9}$'";

        $specs = array_values(array_filter((array) ($f['spec'] ?? []), 'is_string'));
        if ($specs) {
            $in = [];
            foreach ($specs as $i => $s) {
                $in[] = ':spec' . $i;
                $bind[':spec' . $i] = $s;
            }
            $where[] = 'dd.specialty IN (' . implode(', ', $in) . ')';
        }
        if (($f['state'] ?? '') !== '') {
            $where[] = 'dd.state = :state';
            $bind[':state'] = (string) $f['state'];
        }
        if (($f['city'] ?? '') !== '') {
            $where[] = 'dd.city = :city';
            $bind[':city'] = (string) $f['city'];
        }
        if (($f['q'] ?? '') !== '') {
            // Distinct names per use: EMULATE_PREPARES is off, reuse → HY093.
            $where[] = '(dd.name LIKE :q1 OR dd.doctor_name LIKE :q2)';
            $bind[':q1'] = $bind[':q2'] = '%' . $f['q'] . '%';
        }
        if (($f['phone'] ?? '') === 'mobile') {
            $where[] = $mobile;
        } elseif ($f['phone'] === 'other') {
            $where[] = "COALESCE(dd.phone, '') <> '' AND NOT (" . $mobile . ')';
        } elseif ($f['phone'] === 'none') {
            $where[] = "COALESCE(dd.phone, '') = '' AND COALESCE(dd.intl_phone, '') = ''";
        }
        if (in_array($f['claimed'] ?? '', ['0', '1'], true)) {
            $where[] = 'dd.is_claimed = ' . (int) $f['claimed'];
        }
        if (in_array($f['listed'] ?? '', ['0', '1'], true)) {
            $where[] = 'dd.is_active = ' . (int) $f['listed'];
        }
        if (($f['sent'] ?? '') === 'no') {
            $where[] = 'o.last_contacted_at IS NULL';
        } elseif ($f['sent'] === 'yes') {
            $where[] = 'o.last_contacted_at IS NOT NULL';
        }
        if (($f['sent_from'] ?? '') !== '') {
            $where[] = 'o.last_contacted_at >= :sent_from';
            $bind[':sent_from'] = $f['sent_from'] . ' 00:00:00';
        }
        if (($f['sent_to'] ?? '') !== '') {
            $where[] = 'o.last_contacted_at <= :sent_to';
            $bind[':sent_to'] = $f['sent_to'] . ' 23:59:59';
        }
        if (($f['leads'] ?? '') === 'yes') {
            $where[] = "EXISTS (SELECT 1 FROM directory_leads dl
                                 WHERE dl.directory_doctor_id = dd.id AND dl.type = 'book_submitted')";
        }
        if (($f['optout'] ?? 'exclude') === 'exclude') {
            $where[] = 'NOT (' . self::optedOutSql() . ')';
        } elseif ($f['optout'] === 'only') {
            $where[] = self::optedOutSql();
        }

        return ['where' => implode(' AND ', $where), 'bind' => $bind];
    }

    /** Opted out via the outreach row OR the global STOP ledger (messaging_optout). */
    private static function optedOutSql(): string
    {
        $sql = 'COALESCE(o.opted_out, 0) = 1';
        if (self::tableExists('messaging_optout')) {
            // messaging_optout is utf8mb4_unicode_ci, directory_doctors is
            // utf8mb4_general_ci → explicit COLLATE or MySQL errors "Illegal mix of collations".
            $sql .= ' OR EXISTS (SELECT 1 FROM messaging_optout mo
                                  WHERE mo.phone_tail = RIGHT(' . self::PHONE_SQL . ", 10) COLLATE utf8mb4_unicode_ci
                                    AND mo.channel IN ('all', 'whatsapp'))";
        }
        return '(' . $sql . ')';
    }

    /**
     * One page of listings + whether a next page exists. Never reads more than
     * one page of full rows:
     *   1. pick PER_PAGE + 1 ids with the filters (lean: no lead counts, no text);
     *   2. load details, lead counts and drafts for just those ids.
     * No COUNT(*) of the whole match set; the extra id only answers "is there a next page".
     *
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>, has_more:bool}
     */
    public static function page(array $filters, int $page = 1): array
    {
        $pdo = Database::connection();
        $f = self::filterSql($filters);
        $sort = self::SORTS[$filters['sort'] ?? ''] ?? self::SORTS['quality'];
        $dir = ($filters['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
        $offset = max(0, $page - 1) * self::PER_PAGE;

        // 1) ids for this page.
        $stmt = $pdo->prepare(
            "SELECT dd.id
               FROM directory_doctors dd
          LEFT JOIN directory_outreach o ON o.directory_doctor_id = dd.id
              WHERE {$f['where']}
              ORDER BY " . sprintf($sort, $dir) . '
              LIMIT :lim OFFSET :off'
        );
        self::bindAll($stmt, $f['bind']);
        $stmt->bindValue(':lim', self::PER_PAGE + 1, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $hasMore = count($ids) > self::PER_PAGE;
        $ids = array_slice($ids, 0, self::PER_PAGE);
        if (!$ids) {
            return ['rows' => [], 'has_more' => false];
        }

        // 2) details for those ids only (ids are ints from step 1 → safe to inline).
        $optional = '';
        foreach (['entity_type', 'listing_slug'] as $col) {
            if (self::hasColumn($col)) {
                $optional .= ', dd.' . $col;
            }
        }
        $byId = [];
        $rows = $pdo->query(
            "SELECT dd.id, dd.name, dd.doctor_name, dd.specialty, dd.phone, dd.intl_phone,
                    dd.city, dd.state, dd.area, dd.types, dd.is_active, dd.is_claimed,
                    dd.rating, dd.reviews{$optional},
                    (" . self::LEADS_SQL . ") AS leads,
                    o.status AS outreach_status, o.last_contacted_at, o.contacted_count,
                    " . self::optedOutSql() . " AS opted_out
               FROM directory_doctors dd
          LEFT JOIN directory_outreach o ON o.directory_doctor_id = dd.id
              WHERE dd.id IN (" . implode(',', $ids) . ')'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }

        $labels = self::specialtyLabels();
        $templates = self::templates();
        $out = [];
        foreach ($ids as $id) {
            if (!isset($byId[$id])) {
                continue;
            }
            $r = $byId[$id];
            $r['specialty_label'] = $labels[(string) $r['specialty']] ?? (string) $r['specialty'];
            $r['profile_url'] = (int) $r['is_active'] === 1
                ? DirectoryProfileUrlService::publicProfileUrlFromRow($r)
                : null;
            $r['wa_digits'] = self::waDigits($r);
            $r['message'] = self::render(self::templateFor((string) $r['specialty'], $templates), $r);
            $out[] = $r;
        }

        return ['rows' => $out, 'has_more' => $hasMore];
    }

    /**
     * Filter dropdown data. Specialties carry their all/unclaimed counts so the
     * admin can pick where to start; any new specialty shows up on its own.
     * Whole-table aggregates → cached 10 minutes. Cities load only once a state
     * is picked (the city box stays free text otherwise).
     *
     * @return array{specialties:list<array{code:string,label:string,total:int,unclaimed:int}>, states:list<string>, cities:list<string>}
     */
    public static function filterOptions(string $state = ''): array
    {
        $pdo = Database::connection();
        $labels = self::specialtyLabels();

        $counts = self::cached('spec_counts', 600, fn () => $pdo->query(
            "SELECT specialty, COUNT(*) AS total, SUM(is_claimed = 0) AS unclaimed
               FROM directory_doctors
              WHERE specialty IS NOT NULL AND specialty <> ''
              GROUP BY specialty"
        )->fetchAll(PDO::FETCH_ASSOC));

        $specialties = [];
        foreach ($counts as $r) {
            $code = (string) $r['specialty'];
            $specialties[] = [
                'code' => $code,
                'label' => (string) ($labels[$code] ?? $code),
                'total' => (int) $r['total'],
                'unclaimed' => (int) $r['unclaimed'],
            ];
        }
        usort($specialties, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        $states = self::cached('states', 600, fn () => $pdo->query(
            "SELECT DISTINCT state FROM directory_doctors
              WHERE state IS NOT NULL AND state <> '' ORDER BY state"
        )->fetchAll(PDO::FETCH_COLUMN));

        $cities = [];
        if ($state !== '') {
            $cities = self::cached('cities_' . md5($state), 600, function () use ($pdo, $state) {
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT city FROM directory_doctors
                      WHERE state = :st AND city IS NOT NULL AND city <> '' ORDER BY city LIMIT 3000"
                );
                $stmt->execute([':st' => $state]);
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            });
        }

        return ['specialties' => $specialties, 'states' => $states, 'cities' => $cities];
    }

    /**
     * Tiny file cache for whole-table aggregates (no shared cache in the app).
     *
     * @param callable():array<mixed> $build
     * @return array<mixed>
     */
    private static function cached(string $key, int $ttl, callable $build): array
    {
        $file = sys_get_temp_dir() . '/ecp_marketing_' . $key . '.json';
        if (is_file($file) && filemtime($file) > time() - $ttl) {
            $data = json_decode((string) @file_get_contents($file), true);
            if (is_array($data)) {
                return $data;
            }
        }
        $data = $build();
        @file_put_contents($file, json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
        return $data;
    }

    // ------------------------------------------------------------------
    // Mark sent
    // ------------------------------------------------------------------

    /**
     * Record that the admin manually sent a WhatsApp message. Refuses (returns
     * 'already_sent' + the earlier time) unless $again, so a double click never
     * silently re-stamps the original date.
     *
     * @return array{ok:bool, error?:string, sent_at?:string}
     */
    public static function markSent(int $doctorId, ?int $adminId, bool $again): array
    {
        if ($doctorId <= 0) {
            return ['ok' => false, 'error' => 'invalid'];
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT last_contacted_at, opted_out FROM directory_outreach WHERE directory_doctor_id = :id');
        $stmt->execute([':id' => $doctorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($row && (int) $row['opted_out'] === 1) {
            return ['ok' => false, 'error' => 'opted_out'];
        }
        if ($row && $row['last_contacted_at'] !== null && !$again) {
            return ['ok' => false, 'error' => 'already_sent', 'sent_at' => (string) $row['last_contacted_at']];
        }

        $pdo->prepare(
            "INSERT INTO directory_outreach
                (directory_doctor_id, status, last_channel, last_contacted_at, contacted_count, updated_by)
             VALUES (:id, 'contacted', 'whatsapp', NOW(), 1, :uid)
             ON DUPLICATE KEY UPDATE
                status = IF(status = 'not_contacted', 'contacted', status),
                last_channel = 'whatsapp',
                last_contacted_at = NOW(),
                contacted_count = contacted_count + 1,
                updated_by = VALUES(updated_by)"
        )->execute([':id' => $doctorId, ':uid' => $adminId]);

        return ['ok' => true, 'sent_at' => date('Y-m-d H:i:s')];
    }

    // ------------------------------------------------------------------
    // Templates
    // ------------------------------------------------------------------

    /** @return array<string,string> code → template; always has 'default'. */
    public static function templates(): array
    {
        $raw = MessagingSettings::get(self::TEMPLATE_KEY);
        $map = $raw ? json_decode($raw, true) : null;
        $map = is_array($map) ? array_filter($map, fn ($v) => is_string($v) && trim($v) !== '') : [];
        $map['default'] ??= self::DEFAULT_TEMPLATE;
        return $map;
    }

    /** @param array<string,string> $templates */
    public static function templateFor(string $specialty, array $templates): string
    {
        return $templates[$specialty] ?? $templates['default'];
    }

    /**
     * Placeholders in $template that aren't supported.
     *
     * @return list<string>
     */
    public static function unknownPlaceholders(string $template): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/i', $template, $m);
        $bad = array_diff(array_unique(array_map('strtolower', $m[1])), array_keys(self::PLACEHOLDERS));
        // Also catch malformed ones like {{doctor name}}.
        if (preg_match_all('/\{\{(?![\s]*[a-z_]+[\s]*\}\})[^}]*\}\}/i', $template, $mm)) {
            $bad = array_merge($bad, $mm[0]);
        }
        return array_values($bad);
    }

    /** @param array<string,string> $templates */
    public static function saveTemplates(array $templates): void
    {
        $clean = [];
        foreach ($templates as $code => $tpl) {
            $tpl = trim(str_replace("\r\n", "\n", $tpl));
            if ($tpl !== '') {
                $clean[(string) $code] = mb_substr($tpl, 0, 4000);
            }
        }
        MessagingSettings::set(self::TEMPLATE_KEY, json_encode($clean, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Fill a template from a listing row. Missing data never produces a broken
     * sentence:
     *   - [[ ... ]] is an optional chunk, dropped when any placeholder in it is empty;
     *   - any other line holding an empty placeholder is dropped whole.
     *
     * @param array<string,mixed> $row
     */
    public static function render(string $template, array $row): string
    {
        $vals = self::values($row);
        $fill = static function (string $text) use ($vals): ?string {
            $empty = false;
            $out = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', static function ($m) use ($vals, &$empty) {
                $v = $vals[strtolower($m[1])] ?? '';
                if ($v === '') {
                    $empty = true;
                }
                return $v;
            }, $text);
            return $empty ? null : $out;
        };

        $text = preg_replace_callback('/\[\[(.*?)\]\]/s', static fn ($m) => $fill($m[1]) ?? '', $template) ?? $template;

        $lines = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $text)) as $line) {
            $filled = $fill($line);
            if ($filled !== null) {
                $lines[] = rtrim($filled);
            }
        }
        $out = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) ?? '';
        return trim($out);
    }

    /**
     * Placeholder values from real listing data only; '' when unknown.
     *
     * @param array<string,mixed> $row
     * @return array<string,string>
     */
    private static function values(array $row): array
    {
        $name = trim((string) ($row['name'] ?? ''));
        $doctor = trim((string) ($row['doctor_name'] ?? ''));
        $nameIsDoctor = preg_match('/^dr\.?\s/i', $name) === 1;
        if ($doctor === '' && $nameIsDoctor) {
            $doctor = $name;
        }
        $bare = trim((string) preg_replace('/^dr\.?\s*/i', '', $doctor));
        $doctor = $bare !== '' ? 'Dr. ' . $bare : '';

        // Clinic name only when it isn't just the doctor's own name again.
        $clinic = ($nameIsDoctor || strcasecmp($name, $bare) === 0) ? '' : $name;

        $labels = self::specialtyLabels();
        $spec = (string) ($row['specialty'] ?? '');

        return [
            'doctor_name' => $doctor,
            'specialty' => $row['specialty_label'] ?? ($labels[$spec] ?? ''),
            'clinic_name' => $clinic,
            'city' => trim((string) ($row['city'] ?? '')),
            'area' => trim((string) ($row['area'] ?? '')),
            'profile_url' => (string) ($row['profile_url'] ?? ''),
            'website_url' => rtrim((string) ($_ENV['SITE_URL'] ?? 'https://eclinicpro.com'), '/'),
        ];
    }

    /**
     * A sample listing for the template preview (a live, unclaimed one).
     *
     * @return array<string,mixed>|null
     */
    public static function sampleRow(string $specialty = ''): ?array
    {
        $rows = self::page(array_filter([
            'spec' => $specialty !== '' && $specialty !== 'default' ? [$specialty] : [],
            'claimed' => '0',
            'listed' => '1',
            'phone' => 'mobile',
        ]), 1);
        return $rows['rows'][0] ?? null;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @param array<string,mixed> $row */
    private static function waDigits(array $row): string
    {
        $p = preg_replace('/[\s-]+/', '', (string) ($row['intl_phone'] ?? '')) ?? '';
        return preg_match('/^\+91[6-9]\d{9}$/', $p) === 1 ? substr($p, 1) : '';
    }

    /** @return array<string,string> specialty code → label (specialty_master, then the built-in catalog). */
    public static function specialtyLabels(): array
    {
        static $labels = null;
        if ($labels !== null) {
            return $labels;
        }
        $labels = GetListedController::specialtyCatalog();
        try {
            $rows = Database::connection()
                ->query("SELECT slug, label FROM specialty_master WHERE slug IS NOT NULL AND slug <> ''")
                ->fetchAll(PDO::FETCH_KEY_PAIR);
            $labels = array_merge($labels, $rows);
        } catch (\Throwable) {
            // specialty_master missing → built-in catalog only.
        }
        return $labels;
    }

    private static function hasColumn(string $col): bool
    {
        static $cols = null;
        if ($cols === null) {
            $cols = Database::connection()->query('SHOW COLUMNS FROM directory_doctors')->fetchAll(PDO::FETCH_COLUMN);
        }
        return in_array($col, $cols, true);
    }

    private static function tableExists(string $table): bool
    {
        static $seen = [];
        if (!isset($seen[$table])) {
            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
            );
            $stmt->execute([':t' => $table]);
            $seen[$table] = (int) $stmt->fetchColumn() > 0;
        }
        return $seen[$table];
    }

    /** @param array<string,mixed> $bind */
    private static function bindAll(\PDOStatement $stmt, array $bind): void
    {
        foreach ($bind as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
