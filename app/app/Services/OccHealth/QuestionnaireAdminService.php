<?php

declare(strict_types=1);

namespace App\Services\OccHealth;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Super admin › Workforce Health › Questionnaires (Phase 2D-2): edit the
 * pre-exam health questionnaire. Same life cycle as the rule templates:
 * a draft is edited; Publish makes it the live version (the previous
 * published version of the same code is retired); published / retired
 * versions are never edited, "Make a new version" copies the newest one
 * into a draft. Responses keep their own copy of each question's wording,
 * so changing questions never alters past answers.
 */
final class QuestionnaireAdminService
{
    public const TYPES = ['yes_no' => 'Yes / No (with optional detail on Yes)', 'choice' => 'Choose one option', 'text' => 'Free text'];

    /** @return list<array<string, mixed>> every version, newest first per code, with item and response counts */
    public static function all(): array
    {
        return Database::connection()->query(
            'SELECT q.*, (SELECT COUNT(*) FROM oh_questionnaire_items i WHERE i.questionnaire_id = q.id) AS items,
                    (SELECT COUNT(*) FROM occ_questionnaire_responses r WHERE r.questionnaire_id = q.id) AS responses /* cross-company: admin */
               FROM oh_questionnaires q ORDER BY q.code, q.version DESC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null + items */
    public static function find(int $id): ?array
    {
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT * FROM oh_questionnaires WHERE id = :id');
        $st->execute(['id' => $id]);
        $q = $st->fetch();
        if (!$q) {
            return null;
        }
        $it = $pdo->prepare('SELECT * FROM oh_questionnaire_items WHERE questionnaire_id = :q ORDER BY sort_order, id');
        $it->execute(['q' => $id]);
        $q['items'] = $it->fetchAll();

        return $q;
    }

    /** @param array<string, mixed> $post name, intro, is_sample @return array{ok: bool, error?: string} */
    public static function saveDraft(int $id, array $post): array
    {
        $q = self::find($id);
        if ($q === null || $q['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only a draft can be edited.'];
        }
        $name = mb_substr(trim((string) ($post['name'] ?? '')), 0, 150);
        if ($name === '') {
            return ['ok' => false, 'error' => 'Enter a name.'];
        }
        Database::connection()->prepare('UPDATE oh_questionnaires SET name = :n, intro = :i, is_sample = :s, updated_at = :up WHERE id = :id AND status = \'draft\'')
            ->execute(['n' => $name, 'i' => mb_substr(trim((string) ($post['intro'] ?? '')), 0, 500) ?: null, 's' => !empty($post['is_sample']) ? 1 : 0,
                'up' => LocalDate::nowUtc(), 'id' => $id]);

        return ['ok' => true];
    }

    /**
     * Add or update one question (by its code) in a draft.
     *
     * @param array<string, mixed> $post code, section, type, text, options (one per line), detail_prompt, required, sort_order
     * @return array{ok: bool, error?: string}
     */
    public static function saveItem(int $id, array $post): array
    {
        $q = self::find($id);
        if ($q === null || $q['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only a draft can be edited.'];
        }
        $code = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', trim((string) ($post['code'] ?? ''))) ?? '');
        $code = trim($code, '_');
        $type = (string) ($post['type'] ?? '');
        $text = mb_substr(trim((string) ($post['text'] ?? '')), 0, 300);
        $section = mb_substr(trim((string) ($post['section'] ?? '')), 0, 80);
        $options = array_values(array_filter(array_map(static fn ($o) => mb_substr(trim((string) $o), 0, 60), preg_split('/\R/', (string) ($post['options'] ?? '')) ?: [])));
        $error = match (true) {
            $code === '' || strlen($code) > 40 => 'Give the question a short code (letters, numbers, _), e.g. "back_pain".',
            !isset(self::TYPES[$type]) => 'Choose the answer type.',
            $text === '' => 'Write the question.',
            $section === '' => 'Enter a section, e.g. "Your health".',
            $type === 'choice' && count($options) < 2 => 'Give at least two options, one per line.',
            default => null,
        };
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        Database::connection()->prepare(
            'INSERT INTO oh_questionnaire_items (questionnaire_id, code, section, type, text, options_json, detail_prompt, required, sort_order)
             VALUES (:q, :code, :sec, :type, :text, :opts, :detail, :req, :sort)
             ON DUPLICATE KEY UPDATE section = VALUES(section), type = VALUES(type), text = VALUES(text), options_json = VALUES(options_json),
                                     detail_prompt = VALUES(detail_prompt), required = VALUES(required), sort_order = VALUES(sort_order)'
        )->execute(['q' => $id, 'code' => $code, 'sec' => $section, 'type' => $type, 'text' => $text,
            'opts' => $type === 'choice' ? json_encode($options, JSON_UNESCAPED_UNICODE) : null,
            'detail' => $type === 'yes_no' ? (mb_substr(trim((string) ($post['detail_prompt'] ?? '')), 0, 200) ?: null) : null,
            'req' => !empty($post['required']) ? 1 : 0, 'sort' => max(0, min(9999, (int) ($post['sort_order'] ?? 0)))]);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function removeItem(int $id, int $itemId): array
    {
        $q = self::find($id);
        if ($q === null || $q['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only a draft can be edited.'];
        }
        Database::connection()->prepare('DELETE FROM oh_questionnaire_items WHERE id = :id AND questionnaire_id = :q')->execute(['id' => $itemId, 'q' => $id]);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function publish(int $id): array
    {
        $q = self::find($id);
        if ($q === null || $q['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only a draft can be published.'];
        }
        if ($q['items'] === []) {
            return ['ok' => false, 'error' => 'Add at least one question first.'];
        }
        $pdo = Database::connection();
        $now = LocalDate::nowUtc();
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE oh_questionnaires SET status = 'retired', updated_at = :up WHERE code = :code AND status = 'published'")->execute(['up' => $now, 'code' => $q['code']]);
        $pdo->prepare("UPDATE oh_questionnaires SET status = 'published', updated_at = :up WHERE id = :id AND status = 'draft'")->execute(['up' => $now, 'id' => $id]);
        $pdo->commit();

        return ['ok' => true];
    }

    /** Copy the newest version of this code into a new draft (or return the existing draft). @return array{ok: bool, error?: string, id?: int} */
    public static function newVersion(int $id): array
    {
        $q = self::find($id);
        if ($q === null) {
            return ['ok' => false, 'error' => 'Not found.'];
        }
        $pdo = Database::connection();
        $st = $pdo->prepare("SELECT id FROM oh_questionnaires WHERE code = :code AND status = 'draft' LIMIT 1");
        $st->execute(['code' => $q['code']]);
        if (($draft = $st->fetchColumn()) !== false) {
            return ['ok' => true, 'id' => (int) $draft];
        }
        $st = $pdo->prepare('SELECT id, version FROM oh_questionnaires WHERE code = :code ORDER BY version DESC LIMIT 1');
        $st->execute(['code' => $q['code']]);
        $latest = $st->fetch();
        $src = self::find((int) $latest['id']);
        $now = LocalDate::nowUtc();
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO oh_questionnaires (code, version, name, intro, status, is_sample, created_at, updated_at) VALUES (:code, :v, :n, :i, 'draft', :s, :cr, :up)")
            ->execute(['code' => $q['code'], 'v' => (int) $latest['version'] + 1, 'n' => $src['name'], 'i' => $src['intro'], 's' => (int) $src['is_sample'], 'cr' => $now, 'up' => $now]);
        $newId = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare(
            'INSERT INTO oh_questionnaire_items (questionnaire_id, code, section, type, text, options_json, detail_prompt, required, sort_order)
             VALUES (:q, :code, :sec, :type, :text, :opts, :detail, :req, :sort)'
        );
        foreach ($src['items'] as $it) {
            $ins->execute(['q' => $newId, 'code' => $it['code'], 'sec' => $it['section'], 'type' => $it['type'], 'text' => $it['text'], 'opts' => $it['options_json'],
                'detail' => $it['detail_prompt'], 'req' => (int) $it['required'], 'sort' => (int) $it['sort_order']]);
        }
        $pdo->commit();

        return ['ok' => true, 'id' => $newId];
    }

    /** @return array{ok: bool, error?: string} */
    public static function deleteDraft(int $id): array
    {
        $q = self::find($id);
        if ($q === null || $q['status'] !== 'draft') {
            return ['ok' => false, 'error' => 'Only a draft can be deleted.'];
        }
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM oh_questionnaire_items WHERE questionnaire_id = :q')->execute(['q' => $id]);
        $pdo->prepare("DELETE FROM oh_questionnaires WHERE id = :id AND status = 'draft'")->execute(['id' => $id]);

        return ['ok' => true];
    }
}
