<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Platform side of the rule library (design S2, S12): the exam catalogue and
 * the screening templates. Only DRAFTS can be edited. Publishing makes a
 * version visible to companies and retires the previous published version
 * of the same code; companies that adopted an older version see "Update
 * available" and review it themselves (never changed silently).
 *
 * oh_* tables are platform-owned (no company_id). Country admins are limited
 * to their countries by the caller (PlatformAdminScope).
 */
final class TemplateAdminService
{
    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'retired' => 'Retired'];

    // ------------------------------------------------------------------
    // Exam catalogue
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> all, with how many template items use each */
    public static function examTypes(): array
    {
        return Database::connection()->query(
            'SELECT et.*, (SELECT COUNT(*) FROM oh_template_items ti WHERE ti.exam_type_id = et.id) AS used
               FROM oh_exam_types et ORDER BY et.is_active DESC, et.sort_order, et.name'
        )->fetchAll();
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, error?: string}
     */
    public static function saveExamType(int $id, array $post): array
    {
        $code = strtolower(trim((string) ($post['code'] ?? '')));
        $name = mb_substr(trim((string) ($post['name'] ?? '')), 0, 120);
        $kind = (string) ($post['kind'] ?? 'exam');
        $descr = mb_substr(trim((string) ($post['description'] ?? '')), 0, 255);
        $sort = max(0, min(9999, (int) ($post['sort_order'] ?? 100)));
        $error = match (true) {
            $id === 0 && preg_match('/^[a-z][a-z0-9_]{1,39}$/', $code) !== 1 => 'Code: lower-case letters, digits and _ (e.g. blood_lead).',
            mb_strlen($name) < 3 => 'Enter a name.',
            !array_key_exists($kind, ProgrammeService::KINDS) => 'Choose a kind.',
            default => null,
        };
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        $pdo = Database::connection();
        if ($id === 0) {
            $dup = $pdo->prepare('SELECT 1 FROM oh_exam_types WHERE code = :c');
            $dup->execute(['c' => $code]);
            if ($dup->fetchColumn() !== false) {
                return ['ok' => false, 'error' => 'That code already exists.'];
            }
            $pdo->prepare('INSERT INTO oh_exam_types (code, name, kind, description, is_active, sort_order) VALUES (:c, :n, :k, :d, 1, :s)')
                ->execute(['c' => $code, 'n' => $name, 'k' => $kind, 'd' => $descr !== '' ? $descr : null, 's' => $sort]);
        } else {
            // The code never changes: templates, programmes and (later) clinics refer to it.
            $pdo->prepare('UPDATE oh_exam_types SET name = :n, kind = :k, description = :d, sort_order = :s WHERE id = :id')
                ->execute(['n' => $name, 'k' => $kind, 'd' => $descr !== '' ? $descr : null, 's' => $sort, 'id' => $id]);
        }

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function toggleExamType(int $id): array
    {
        $st = Database::connection()->prepare('UPDATE oh_exam_types SET is_active = 1 - is_active WHERE id = :id');
        $st->execute(['id' => $id]);

        return $st->rowCount() === 1 ? ['ok' => true] : ['ok' => false, 'error' => 'Not found.'];
    }

    // ------------------------------------------------------------------
    // Templates
    // ------------------------------------------------------------------

    /**
     * Every version, newest first per code, with how many companies use each code.
     *
     * @param list<string>|null $countries  null = all
     * @return list<array<string, mixed>>
     */
    public static function templates(?array $countries): array
    {
        $where = '1 = 1';
        if ($countries !== null) {
            $countries = array_values(array_filter($countries, static fn ($c) => preg_match('/^[A-Z]{2}$/', (string) $c) === 1));
            $where = $countries === [] ? '1 = 0' : "t.country_code IN ('" . implode("','", $countries) . "')";
        }

        return Database::connection()->query(
            "SELECT t.*, i.name AS industry_name,
                    (SELECT COUNT(*) FROM oh_template_items ti WHERE ti.template_id = t.id) AS item_count,
                    (SELECT COUNT(*) FROM corp_programmes p /* cross-company: admin */ WHERE p.template_code = t.code AND p.status = 'active') AS companies_using
               FROM oh_rule_templates t LEFT JOIN corp_industries i ON i.id = t.industry_id
              WHERE {$where}
              ORDER BY t.country_code, t.code, t.version DESC"
        )->fetchAll();
    }

    /** @return array<string, mixed>|null + items */
    public static function find(int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT t.*, i.name AS industry_name FROM oh_rule_templates t LEFT JOIN corp_industries i ON i.id = t.industry_id WHERE t.id = :id');
        $st->execute(['id' => $id]);
        $t = $st->fetch();
        if (!$t) {
            return null;
        }
        $t['items'] = ProgrammeService::templateItems($id);

        return $t;
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function createDraft(int $adminId, array $post): array
    {
        $code = strtoupper(trim((string) ($post['code'] ?? '')));
        if (preg_match('/^[A-Z]{2}-[A-Z0-9][A-Z0-9\-]{1,36}$/', $code) !== 1) {
            return ['ok' => false, 'error' => 'Code: country code, a dash, then letters/digits/dashes (e.g. IN-CON-WELD).'];
        }
        $dup = Database::connection()->prepare('SELECT 1 FROM oh_rule_templates WHERE code = :c');
        $dup->execute(['c' => $code]);
        if ($dup->fetchColumn() !== false) {
            return ['ok' => false, 'error' => 'That code already exists. Open it and make a new version instead.'];
        }
        $fields = self::fields($post);
        if (isset($fields['error'])) {
            return ['ok' => false, 'error' => $fields['error']];
        }
        if (substr($code, 0, 2) !== $fields['country_code']) {
            return ['ok' => false, 'error' => 'The code must start with the country code (' . $fields['country_code'] . '-…).'];
        }
        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO oh_rule_templates (code, version, country_code, industry_id, name, audience, description, source_note, status, is_sample, created_by, created_at, updated_at)
             VALUES (:code, 1, :cc, :ind, :name, :aud, :descr, :src, \'draft\', :sample, :by, :cr, :up)'
        )->execute([
            'code' => $code, 'cc' => $fields['country_code'], 'ind' => $fields['industry_id'], 'name' => $fields['name'], 'aud' => $fields['audience'],
            'descr' => $fields['description'], 'src' => $fields['source_note'], 'sample' => $fields['is_sample'], 'by' => $adminId, 'cr' => $now, 'up' => $now,
        ]);

        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, error?: string}
     */
    public static function saveDraft(int $id, array $post): array
    {
        $t = self::find($id);
        if ($t === null || $t['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only drafts can be edited. Make a new version first.'];
        }
        $fields = self::fields($post, (string) $t['country_code']);
        if (isset($fields['error'])) {
            return ['ok' => false, 'error' => $fields['error']];
        }
        Database::connection()->prepare(
            'UPDATE oh_rule_templates SET industry_id = :ind, name = :name, audience = :aud, description = :descr, source_note = :src, is_sample = :sample, updated_at = :up WHERE id = :id'
        )->execute([
            'ind' => $fields['industry_id'], 'name' => $fields['name'], 'aud' => $fields['audience'], 'descr' => $fields['description'],
            'src' => $fields['source_note'], 'sample' => $fields['is_sample'], 'up' => LocalDate::nowUtc(), 'id' => $id,
        ]);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function saveItem(int $templateId, array $post): array
    {
        $t = self::find($templateId);
        if ($t === null || $t['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only drafts can be edited.'];
        }
        $exam = (int) ($post['exam_type_id'] ?? 0);
        $cat = (string) ($post['category'] ?? '');
        $req = (string) ($post['requirement'] ?? 'recommended');
        $months = $cat === 'periodic' ? (int) ($post['frequency_months'] ?? 0) : null;
        $note = mb_substr(trim((string) ($post['note'] ?? '')), 0, 255);
        $chk = Database::connection()->prepare('SELECT 1 FROM oh_exam_types WHERE id = :id AND is_active = 1');
        $chk->execute(['id' => $exam]);
        $error = match (true) {
            $chk->fetchColumn() === false => 'Choose an exam or test.',
            !array_key_exists($cat, ProgrammeService::CATEGORIES) => 'Choose when it is done.',
            !in_array($req, ['mandatory', 'recommended'], true) => 'Choose Mandatory or Recommended.',
            $months !== null && ($months < 1 || $months > 120) => 'Periodic checks need a frequency of 1–120 months.',
            default => null,
        };
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        // Same exam + when = update it (one row per pair).
        Database::connection()->prepare(
            'INSERT INTO oh_template_items (template_id, exam_type_id, category, frequency_months, requirement, note, sort_order)
             VALUES (:t, :e, :c, :m, :r, :n, :s)
             ON DUPLICATE KEY UPDATE frequency_months = VALUES(frequency_months), requirement = VALUES(requirement), note = VALUES(note)'
        )->execute(['t' => $templateId, 'e' => $exam, 'c' => $cat, 'm' => $months, 'r' => $req, 'n' => $note !== '' ? $note : null, 's' => (count($t['items']) + 1) * 10]);
        self::touch($templateId);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function removeItem(int $templateId, int $itemId): array
    {
        $t = self::find($templateId);
        if ($t === null || $t['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only drafts can be edited.'];
        }
        Database::connection()->prepare('DELETE FROM oh_template_items WHERE id = :id AND template_id = :t')->execute(['id' => $itemId, 't' => $templateId]);
        self::touch($templateId);

        return ['ok' => true];
    }

    /** Draft → published; the previous published version of the code is retired. */
    public static function publish(int $id, int $adminId): array
    {
        $t = self::find($id);
        if ($t === null || $t['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only a draft can be published.'];
        }
        if ($t['items'] === []) {
            return ['ok' => false, 'error' => 'Add at least one check before publishing.'];
        }
        $pdo = Database::connection();
        $now = LocalDate::nowUtc();
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE oh_rule_templates SET status = 'retired', updated_at = :up WHERE code = :code AND status = 'published'")
            ->execute(['up' => $now, 'code' => $t['code']]);
        $pdo->prepare("UPDATE oh_rule_templates SET status = 'published', published_at = :pa, updated_at = :up WHERE id = :id AND status = 'draft'")
            ->execute(['pa' => $now, 'up' => $now, 'id' => $id]);
        $pdo->commit();
        error_log('[TemplateAdmin] admin ' . $adminId . ' published ' . $t['code'] . ' v' . $t['version']);

        return ['ok' => true];
    }

    /**
     * Start editing a published / retired template: copies the newest version into a new draft.
     *
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function newVersion(int $id, int $adminId): array
    {
        $t = self::find($id);
        if ($t === null) {
            return ['ok' => false, 'error' => 'Not found.'];
        }
        $pdo = Database::connection();
        $draft = $pdo->prepare("SELECT id FROM oh_rule_templates WHERE code = :code AND status = 'draft' LIMIT 1");
        $draft->execute(['code' => $t['code']]);
        if (($existing = $draft->fetchColumn()) !== false) {
            return ['ok' => true, 'id' => (int) $existing];
        }
        $latest = $pdo->prepare('SELECT id FROM oh_rule_templates WHERE code = :code ORDER BY version DESC LIMIT 1');
        $latest->execute(['code' => $t['code']]);
        $src = self::find((int) $latest->fetchColumn());
        $now = LocalDate::nowUtc();
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO oh_rule_templates (code, version, country_code, industry_id, name, audience, description, source_note, status, is_sample, created_by, created_at, updated_at)
             VALUES (:code, :ver, :cc, :ind, :name, :aud, :descr, :src, \'draft\', :sample, :by, :cr, :up)'
        )->execute([
            'code' => $src['code'], 'ver' => (int) $src['version'] + 1, 'cc' => $src['country_code'], 'ind' => (int) $src['industry_id'], 'name' => $src['name'],
            'aud' => $src['audience'], 'descr' => $src['description'], 'src' => $src['source_note'], 'sample' => (int) $src['is_sample'], 'by' => $adminId, 'cr' => $now, 'up' => $now,
        ]);
        $newId = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare(
            'INSERT INTO oh_template_items (template_id, exam_type_id, category, frequency_months, requirement, note, sort_order) VALUES (:t, :e, :c, :m, :r, :n, :s)'
        );
        foreach ($src['items'] as $it) {
            $ins->execute(['t' => $newId, 'e' => (int) $it['exam_type_id'], 'c' => $it['category'], 'm' => $it['frequency_months'], 'r' => $it['requirement'], 'n' => $it['note'], 's' => (int) $it['sort_order']]);
        }
        $pdo->commit();

        return ['ok' => true, 'id' => $newId];
    }

    /** Published → retired: hidden from the library; companies keep their programmes. */
    public static function retire(int $id): array
    {
        $st = Database::connection()->prepare("UPDATE oh_rule_templates SET status = 'retired', updated_at = :up WHERE id = :id AND status = 'published'");
        $st->execute(['up' => LocalDate::nowUtc(), 'id' => $id]);

        return $st->rowCount() === 1 ? ['ok' => true] : ['ok' => false, 'error' => 'Only a published template can be retired.'];
    }

    /** Throw a draft away (never used by anyone). */
    public static function deleteDraft(int $id): array
    {
        $t = self::find($id);
        if ($t === null || $t['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only drafts can be deleted.'];
        }
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM oh_template_items WHERE template_id = :id')->execute(['id' => $id]);
        $pdo->prepare("DELETE FROM oh_rule_templates WHERE id = :id AND status = 'draft'")->execute(['id' => $id]);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function fields(array $post, ?string $fixedCountry = null): array
    {
        $cc = $fixedCountry ?? strtoupper(trim((string) ($post['country_code'] ?? '')));
        $out = [
            'country_code' => $cc,
            'industry_id' => max(0, (int) ($post['industry_id'] ?? 0)),
            'name' => mb_substr(trim((string) ($post['name'] ?? '')), 0, 150),
            'audience' => mb_substr(trim((string) ($post['audience'] ?? '')), 0, 255) ?: null,
            'description' => mb_substr(trim((string) ($post['description'] ?? '')), 0, 2000) ?: null,
            'source_note' => mb_substr(trim((string) ($post['source_note'] ?? '')), 0, 500) ?: null,
            'is_sample' => !empty($post['is_sample']) ? 1 : 0,
        ];
        $error = match (true) {
            \App\Support\Locale\CountryRepo::country($cc) === null => 'Choose a country.',
            mb_strlen($out['name']) < 3 => 'Enter a name.',
            default => null,
        };

        return $error !== null ? ['error' => $error] : $out;
    }

    private static function touch(int $id): void
    {
        Database::connection()->prepare('UPDATE oh_rule_templates SET updated_at = :up WHERE id = :id')->execute(['up' => LocalDate::nowUtc(), 'id' => $id]);
    }
}
