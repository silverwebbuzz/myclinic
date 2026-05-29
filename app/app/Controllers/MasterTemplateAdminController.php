<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Support\View;
use PDO;

/**
 * MasterTemplateAdminController — /admin/rx-templates (super-admin only).
 *
 * CRUD over the GLOBAL master prescription templates
 * (prescription_templates_master + _items). These are the system-provided
 * starter packs shown in the doctor's prescription panel, per specialty.
 * Inactive templates (is_active=0) are hidden from doctors but visible here
 * for review/activation.
 */
final class MasterTemplateAdminController
{
    public function index(Request $request): Response
    {
        $pdo = Database::connection();
        $specialty = trim((string) ($request->query['specialty'] ?? ''));

        $where = '';
        $params = [];
        if ($specialty !== '') {
            $where = 'WHERE t.specialty = :s';
            $params[':s'] = $specialty;
        }

        $stmt = $pdo->prepare(
            "SELECT t.*, (SELECT COUNT(*) FROM prescription_template_master_items i WHERE i.template_id = t.id) AS item_count
               FROM prescription_templates_master t
               $where
               ORDER BY t.specialty ASC, t.is_active DESC, t.name ASC"
        );
        $stmt->execute($params);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Distinct specialties present, for the filter dropdown.
        $specs = $pdo->query(
            'SELECT DISTINCT specialty FROM prescription_templates_master ORDER BY specialty'
        )->fetchAll(PDO::FETCH_COLUMN);

        return Response::html(View::render('admin/rx_templates', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'templates' => $templates,
            'specialties' => $specs,
            'filterSpecialty' => $specialty,
            'message' => $request->query['message'] ?? null,
        ]));
    }

    /** GET /admin/rx-templates/new — blank form. */
    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    /** GET /admin/rx-templates/{id} — edit form. */
    public function edit(Request $request, string $id): Response
    {
        $pdo = Database::connection();
        $tpl = $pdo->prepare('SELECT * FROM prescription_templates_master WHERE id = ?');
        $tpl->execute([(int) $id]);
        $template = $tpl->fetch(PDO::FETCH_ASSOC);
        if (!$template) {
            return Response::redirect('/admin/rx-templates?message=not_found');
        }
        $items = $pdo->prepare(
            'SELECT i.*, d.name AS drug_name
               FROM prescription_template_master_items i
          LEFT JOIN drugs d ON d.id = i.drug_id
              WHERE i.template_id = ? ORDER BY i.sort_order ASC'
        );
        $items->execute([(int) $id]);

        return $this->form($template, $items->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param array<string,mixed>|null $template @param list<array<string,mixed>> $items */
    private function form(?array $template, array $items = []): Response
    {
        $specs = Database::connection()
            ->query('SELECT slug, label FROM specialty_master WHERE is_active = 1 ORDER BY label')
            ->fetchAll(PDO::FETCH_ASSOC);

        return Response::html(View::render('admin/rx_template_form', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'template' => $template,
            'items' => $items,
            'allSpecialties' => $specs,
        ]));
    }

    /** POST /admin/rx-templates/save — create or update header + items. */
    public function save(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/rx-templates');
        }
        $p = $request->post;
        $id = (int) ($p['id'] ?? 0);
        $specialty = trim((string) ($p['specialty'] ?? ''));
        $name = trim((string) ($p['name'] ?? ''));
        if ($specialty === '' || $name === '') {
            return Response::redirect('/admin/rx-templates?message=missing_fields');
        }
        $mode = in_array($p['mode'] ?? 'allopathic', ['allopathic', 'homeopathic'], true) ? $p['mode'] : 'allopathic';
        $active = isset($p['is_active']) ? 1 : 0;

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE prescription_templates_master
                        SET specialty = :s, name = :n, description = :d, mode = :m, is_active = :a
                      WHERE id = :id'
                )->execute([
                    ':s' => $specialty, ':n' => mb_substr($name, 0, 120),
                    ':d' => mb_substr(trim((string) ($p['description'] ?? '')), 0, 240) ?: null,
                    ':m' => $mode, ':a' => $active, ':id' => $id,
                ]);
                // Replace items wholesale (simpler + correct for an edit form).
                $pdo->prepare('DELETE FROM prescription_template_master_items WHERE template_id = ?')->execute([$id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO prescription_templates_master (specialty, name, description, mode, is_active)
                     VALUES (:s, :n, :d, :m, :a)'
                )->execute([
                    ':s' => $specialty, ':n' => mb_substr($name, 0, 120),
                    ':d' => mb_substr(trim((string) ($p['description'] ?? '')), 0, 240) ?: null,
                    ':m' => $mode, ':a' => $active,
                ]);
                $id = (int) $pdo->lastInsertId();
            }

            // Items arrive as parallel arrays (drug_id[], dose_unit[], …).
            $drugIds = $p['item_drug_id'] ?? [];
            $insItem = $pdo->prepare(
                'INSERT INTO prescription_template_master_items
                    (template_id, mode, drug_id, match_name, dose_unit, dose_amount,
                     frequency_preset, duration_days, food_timing, instructions, sort_order)
                 VALUES (:t, :m, :dr, :mn, :du, :da, :fp, :dd, :ft, :ins, :o)'
            );
            $sort = 0;
            foreach ((array) $drugIds as $i => $drugId) {
                $drugId = (int) $drugId;
                if ($drugId <= 0) {
                    continue;
                }
                $food = $p['item_food'][$i] ?? 'any';
                $insItem->execute([
                    ':t' => $id,
                    ':m' => $mode,
                    ':dr' => $drugId,
                    ':mn' => trim((string) ($p['item_name'][$i] ?? '')) ?: null,
                    ':du' => trim((string) ($p['item_dose_unit'][$i] ?? '')) ?: null,
                    ':da' => ($p['item_dose_amount'][$i] ?? '') !== '' ? (float) $p['item_dose_amount'][$i] : null,
                    ':fp' => trim((string) ($p['item_freq'][$i] ?? '')) ?: null,
                    ':dd' => ($p['item_days'][$i] ?? '') !== '' ? (int) $p['item_days'][$i] : null,
                    ':ft' => in_array($food, ['before', 'after', 'with', 'empty', 'bedtime', 'any'], true) ? $food : 'any',
                    ':ins' => trim((string) ($p['item_instructions'][$i] ?? '')) ?: null,
                    ':o' => $sort++,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return Response::redirect('/admin/rx-templates?message=save_error');
        }

        return Response::redirect('/admin/rx-templates?message=saved');
    }

    /** POST /admin/rx-templates/{id}/toggle — flip is_active. */
    public function toggle(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/rx-templates');
        }
        Database::connection()
            ->prepare('UPDATE prescription_templates_master SET is_active = 1 - is_active WHERE id = ?')
            ->execute([(int) $id]);

        return Response::redirect('/admin/rx-templates?message=toggled');
    }

    /** POST /admin/rx-templates/{id}/delete — remove template + items (cascade). */
    public function delete(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/rx-templates');
        }
        // _items has ON DELETE CASCADE, so deleting the header is enough.
        Database::connection()
            ->prepare('DELETE FROM prescription_templates_master WHERE id = ?')
            ->execute([(int) $id]);

        return Response::redirect('/admin/rx-templates?message=deleted');
    }
}
