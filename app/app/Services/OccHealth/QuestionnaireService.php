<?php

declare(strict_types=1);

namespace App\Services\OccHealth;

use App\Core\Database;
use App\Services\Corporate\CorpAudit;
use App\Support\Locale\LocalDate;

/**
 * Pre-exam health questionnaire (Phase 2D, H5). One response per booking,
 * filled by the employee before the visit (patient panel / app) or by the
 * clinic with the employee at the visit. Answers are clinical
 * (occ_questionnaire_responses): only the examining clinic and the employee
 * see them. The company only learns corp_bookings.questionnaire_done_at.
 *
 * The patient-panel side (partials/work_health.php ecp_wh_questionnaire_*)
 * validates the same way: keep normalize() and ecp_wh_q_normalize() in step.
 */
final class QuestionnaireService
{
    /** @return array{id:int, name:string, intro:?string, is_sample:int, items: list<array<string, mixed>>}|null the published questionnaire */
    public static function active(): ?array
    {
        try {
            $pdo = Database::connection();
            $q = $pdo->query("SELECT id, name, intro, is_sample FROM oh_questionnaires WHERE status = 'published' ORDER BY code = 'PRE-EXAM' DESC, version DESC LIMIT 1")->fetch();
            if (!$q) {
                return null;
            }
            $st = $pdo->prepare('SELECT code, section, type, text, options_json, detail_prompt, required FROM oh_questionnaire_items WHERE questionnaire_id = :q ORDER BY sort_order, id');
            $st->execute(['q' => (int) $q['id']]);
            $items = $st->fetchAll();
            foreach ($items as &$i) {
                $i['options'] = json_decode((string) ($i['options_json'] ?? '[]'), true) ?: [];
            }

            return ['id' => (int) $q['id'], 'name' => (string) $q['name'], 'intro' => $q['intro'], 'is_sample' => (int) $q['is_sample'], 'items' => $items];
        } catch (\Throwable $e) {
            return null;   // 2D patch not run yet
        }
    }

    /** @return array<string, mixed>|null the response for this booking, answers decoded */
    public static function forBooking(int $companyId, int $bookingId): ?array
    {
        try {
            $st = Database::connection()->prepare('SELECT * FROM occ_questionnaire_responses WHERE company_id = :cid AND booking_id = :b LIMIT 1');
            $st->execute(['cid' => $companyId, 'b' => $bookingId]);
            $r = $st->fetch();
        } catch (\Throwable $e) {
            return null;
        }
        if (!$r) {
            return null;
        }
        $r['answers'] = json_decode((string) $r['answers_json'], true) ?: [];

        return $r;
    }

    /**
     * Validate posted answers q[code][v], q[code][d] against the questionnaire.
     *
     * @param array<string, mixed> $posted
     * @return array{answers: array<string, array{q:string, s:string, v:string, d:string}>, error: ?string, has_yes: bool}
     */
    public static function normalize(array $questionnaire, array $posted): array
    {
        $answers = [];
        $hasYes = false;
        foreach ($questionnaire['items'] as $it) {
            $code = (string) $it['code'];
            $v = trim((string) ($posted[$code]['v'] ?? ''));
            $d = mb_substr(trim((string) ($posted[$code]['d'] ?? '')), 0, 500);
            if ($it['type'] === 'yes_no' && !in_array($v, ['yes', 'no'], true)) {
                $v = '';
            }
            if ($it['type'] === 'choice' && !in_array($v, $it['options'], true)) {
                $v = '';
            }
            if ($it['type'] === 'text') {
                $v = mb_substr($v, 0, 1000);
            }
            if ($v === '' && (int) $it['required'] === 1) {
                return ['answers' => [], 'error' => 'Please answer: "' . $it['text'] . '"', 'has_yes' => false];
            }
            if ($it['type'] !== 'yes_no' || $v !== 'yes') {
                $d = '';
            } else {
                $hasYes = true;
            }
            $answers[$code] = ['q' => (string) $it['text'], 's' => (string) $it['section'], 'v' => $v, 'd' => $d];
        }

        return ['answers' => $answers, 'error' => null, 'has_yes' => $hasYes];
    }

    /**
     * The clinic fills (or corrects) the questionnaire with the employee at the visit.
     *
     * @param array<string, mixed> $visit
     * @param array<string, mixed> $posted q[...]
     * @return array{ok: bool, error?: string}
     */
    public static function clinicSave(int $clinicId, int $userId, array $visit, array $posted): array
    {
        $panel = OccExamService::forVisit($clinicId, $visit, $userId);
        $q = self::active();
        if ($panel === null || $q === null) {
            return ['ok' => false, 'error' => 'No questionnaire for this visit.'];
        }
        $n = self::normalize($q, is_array($posted) ? $posted : []);
        if ($n['error'] !== null) {
            return ['ok' => false, 'error' => $n['error']];
        }
        $b = $panel['booking'];
        self::store((int) $b['company_id'], (int) $b['employment_id'], (int) $b['id'], (int) $q['id'], $n, 'clinic', $userId);
        CorpAudit::log((int) $b['company_id'], 'provider', $userId, 'questionnaire_done', 'corp_booking', (int) $b['id'], ['clinic' => $b['clinic_name'], 'employee_no' => $b['employee_no']]);

        return ['ok' => true];
    }

    /** @param array{answers: array, has_yes: bool} $n */
    private static function store(int $companyId, int $employmentId, int $bookingId, int $questionnaireId, array $n, string $by, int $byId): void
    {
        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO occ_questionnaire_responses (company_id, employment_id, booking_id, questionnaire_id, answers_json, has_yes, submitted_by, submitter_id, submitted_at, updated_at)
             VALUES (:cid, :eid, :b, :q, :a, :y, :by, :byid, :at, :up)
             ON DUPLICATE KEY UPDATE questionnaire_id = VALUES(questionnaire_id), answers_json = VALUES(answers_json), has_yes = VALUES(has_yes),
                                     submitted_by = VALUES(submitted_by), submitter_id = VALUES(submitter_id), updated_at = VALUES(updated_at)'
        )->execute(['cid' => $companyId, 'eid' => $employmentId, 'b' => $bookingId, 'q' => $questionnaireId,
            'a' => json_encode($n['answers'], JSON_UNESCAPED_UNICODE), 'y' => $n['has_yes'] ? 1 : 0, 'by' => $by, 'byid' => $byId, 'at' => $now, 'up' => $now]);
        $pdo->prepare('UPDATE corp_bookings SET questionnaire_done_at = COALESCE(questionnaire_done_at, :at) WHERE id = :id AND company_id = :cid')
            ->execute(['at' => $now, 'id' => $bookingId, 'cid' => $companyId]);
    }
}
