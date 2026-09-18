<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\DiscountService;
use App\Support\View;

/**
 * DiscountAdminController — /admin/discounts (super-admin only).
 *
 * Discount codes for the subscription plan, applied by doctors on the signup
 * checkout (/register/checkout). Percent or flat ₹ off the plan price (before
 * GST), with optional usage limit and validity window. Codes with
 * redemptions can't be deleted (history stays intact) — deactivate instead.
 */
final class DiscountAdminController
{
    public function index(Request $request): Response
    {
        $ready = DiscountService::ensureSchema();

        return Response::html(View::render('admin/discounts', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'codes' => $ready ? DiscountService::all() : [],
            'tableMissing' => !$ready,
            'message' => $request->query['message'] ?? null,
        ]));
    }

    /** POST /admin/discounts — create or update a code. */
    public function save(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null) || !DiscountService::ensureSchema()) {
            return Response::redirect('/admin/discounts');
        }

        $id = (int) ($request->post['id'] ?? 0);
        $code = DiscountService::normalize((string) ($request->post['code'] ?? ''));
        $type = ($request->post['discount_type'] ?? 'percent') === 'flat' ? 'flat' : 'percent';
        $value = max(0, (float) ($request->post['discount_value'] ?? 0));
        $maxUses = trim((string) ($request->post['max_uses'] ?? ''));
        $from = $this->date($request->post['valid_from'] ?? '');
        $until = $this->date($request->post['valid_until'] ?? '');

        if ($code === '' || strlen($code) < 3) {
            return Response::redirect('/admin/discounts?message=code_needs_3+_letters_or_numbers');
        }
        if ($value <= 0 || ($type === 'percent' && $value > 100)) {
            return Response::redirect('/admin/discounts?message=' . ($type === 'percent' ? 'percent_must_be_1-100' : 'amount_must_be_above_0'));
        }
        if ($from !== null && $until !== null && $until < $from) {
            return Response::redirect('/admin/discounts?message=end_date_is_before_start_date');
        }

        $data = [
            'code' => $code,
            'description' => mb_substr(trim((string) ($request->post['description'] ?? '')), 0, 190),
            'discount_type' => $type,
            'discount_value' => $value,
            'max_uses' => $maxUses === '' ? null : max(1, (int) $maxUses),
            'valid_from' => $from,
            'valid_until' => $until,
            'is_active' => isset($request->post['is_active']) ? 1 : 0,
        ];

        $pdo = Database::connection();
        try {
            if ($id > 0) {
                $sets = implode(', ', array_map(static fn ($k) => "$k = :$k", array_keys($data)));
                $data['id'] = $id;
                $pdo->prepare("UPDATE plan_discount_codes SET $sets WHERE id = :id")->execute($data);
            } else {
                $cols = implode(', ', array_keys($data));
                $vals = implode(', ', array_map(static fn ($k) => ":$k", array_keys($data)));
                $pdo->prepare("INSERT INTO plan_discount_codes ($cols) VALUES ($vals)")->execute($data);
            }
        } catch (\PDOException $e) {
            $dup = str_contains($e->getMessage(), 'Duplicate') || $e->getCode() === '23000';

            return Response::redirect('/admin/discounts?message=' . ($dup ? 'that_code_already_exists' : 'save_error'));
        }

        return Response::redirect('/admin/discounts?message=saved');
    }

    /** POST /admin/discounts/{id}/toggle — activate / deactivate. */
    public function toggle(Request $request, string $id): Response
    {
        if (CsrfService::verify($request->post['_csrf'] ?? null) && DiscountService::ensureSchema()) {
            Database::connection()
                ->prepare('UPDATE plan_discount_codes SET is_active = 1 - is_active WHERE id = ?')
                ->execute([(int) $id]);
        }

        return Response::redirect('/admin/discounts?message=updated');
    }

    /** POST /admin/discounts/{id}/delete — only codes never redeemed. */
    public function delete(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null) || !DiscountService::ensureSchema()) {
            return Response::redirect('/admin/discounts');
        }
        if (DiscountService::redemptionCount((int) $id) > 0) {
            return Response::redirect('/admin/discounts?message=code_has_been_used_—_deactivate_it_instead');
        }
        Database::connection()->prepare('DELETE FROM plan_discount_codes WHERE id = ?')->execute([(int) $id]);

        return Response::redirect('/admin/discounts?message=deleted');
    }

    private function date(mixed $raw): ?string
    {
        $s = trim((string) $raw);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
    }
}
