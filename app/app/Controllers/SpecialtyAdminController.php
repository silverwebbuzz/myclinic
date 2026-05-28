<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Support\SpecialtyCatalog;
use App\Support\View;
use PDO;

/**
 * SpecialtyAdminController — /admin/specialties (super-admin only).
 *
 * CRUD over the specialty_master catalog. Generic specialties can be added,
 * edited, and activated/deactivated freely. Specialties with has_custom_form=1
 * have bespoke clinical code (SpecialtyAdapter) — the flag is editable but a
 * developer must add the actual form for it to do anything.
 */
final class SpecialtyAdminController
{
    public function index(Request $request): Response
    {
        $rows = [];
        try {
            $rows = Database::connection()
                ->query('SELECT * FROM specialty_master ORDER BY sort_order ASC, label ASC')
                ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { /* table not migrated yet */ }

        return Response::html(View::render('admin/specialties', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'specialties' => $rows,
            'tableMissing' => $rows === [] && !$this->tableExists(),
            'message' => $request->query['message'] ?? null,
        ]));
    }

    /** POST /admin/specialties — create or update one specialty. */
    public function save(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/specialties');
        }

        $id = (int) ($request->post['id'] ?? 0);
        $slug = $this->slugify((string) ($request->post['slug'] ?? ''));
        $label = trim((string) ($request->post['label'] ?? ''));
        if ($slug === '' || $label === '') {
            return Response::redirect('/admin/specialties?message=missing_fields');
        }

        $mode = (string) ($request->post['prescription_mode'] ?? 'allopathic');
        if (!in_array($mode, ['allopathic', 'homeopathic', 'dental', 'both'], true)) {
            $mode = 'allopathic';
        }

        $data = [
            'slug' => $slug,
            'label' => $label,
            'plural_label' => trim((string) ($request->post['plural_label'] ?? '')) ?: ($label . 's'),
            'category' => trim((string) ($request->post['category'] ?? 'General & specialists')) ?: 'General & specialists',
            'icon' => trim((string) ($request->post['icon'] ?? '')) ?: '🩺',
            'prescription_mode' => $mode,
            'has_custom_form' => isset($request->post['has_custom_form']) ? 1 : 0,
            'is_active' => isset($request->post['is_active']) ? 1 : 0,
            'seo_safe' => isset($request->post['seo_safe']) ? 1 : 0,
            'sort_order' => (int) ($request->post['sort_order'] ?? 100),
        ];

        $pdo = Database::connection();
        try {
            if ($id > 0) {
                // slug is immutable on edit (it's a foreign key in tenants.specialty)
                unset($data['slug']);
                $sets = implode(', ', array_map(static fn ($k) => "$k = :$k", array_keys($data)));
                $stmt = $pdo->prepare("UPDATE specialty_master SET $sets WHERE id = :id");
                $data['id'] = $id;
                $stmt->execute($data);
            } else {
                $cols = implode(', ', array_keys($data));
                $vals = implode(', ', array_map(static fn ($k) => ":$k", array_keys($data)));
                $stmt = $pdo->prepare("INSERT INTO specialty_master ($cols) VALUES ($vals)");
                $stmt->execute($data);
            }
        } catch (\Throwable $e) {
            return Response::redirect('/admin/specialties?message=save_error');
        }

        SpecialtyCatalog::flush();
        return Response::redirect('/admin/specialties?message=saved');
    }

    /** POST /admin/specialties/{id}/toggle — flip is_active. */
    public function toggle(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/specialties');
        }
        try {
            Database::connection()
                ->prepare('UPDATE specialty_master SET is_active = 1 - is_active WHERE id = ?')
                ->execute([(int) $id]);
            SpecialtyCatalog::flush();
        } catch (\Throwable $e) { /* ignore */ }

        return Response::redirect('/admin/specialties?message=toggled');
    }

    private function tableExists(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM specialty_master LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function slugify(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? '';
        return trim($s, '_');
    }
}
