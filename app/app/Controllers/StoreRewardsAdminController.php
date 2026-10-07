<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\PointsService;
use App\Services\Store\ReferralService;
use App\Services\Store\StoreAudit;
use App\Services\Store\StoreSettings;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * /admin/store/rewards — eClinicPro Points (document/store-rewards-plan.md).
 *   overview  : points outstanding / pending / spent / expired + programme settings
 *   referrals : every referral, flagged ones first; reject one that hasn't paid out
 *   customer  : look up by phone → balance, lots, history, add/remove points
 */
final class StoreRewardsAdminController
{
    /** setting key => [label, min, max] (integers) */
    private const INT_SETTINGS = [
        'store_points_welcome' => ['Welcome points for a new sign-up', 0, 10000],
        'store_points_welcome_min_order_paise' => ['Minimum order to use welcome points (₹)', 0, 10000000],
        'store_points_referral_each' => ['Referral points (each person)', 0, 10000],
        'store_points_redeem_cap_bp' => ['Referral/loyalty points: max % of an order', 0, 10000],
        'store_points_loyalty_earn_bp' => ['Loyalty: % back on orders that used no points', 0, 10000],
        'store_points_expiry_days' => ['Points expire after (days)', 1, 3650],
    ];

    public function index(Request $request): Response
    {
        $tab = (string) ($request->query['tab'] ?? 'overview');
        $tab = in_array($tab, ['overview', 'referrals', 'customer'], true) ? $tab : 'overview';
        $data = ['tab' => $tab, 'stats' => [], 'referrals' => [], 'customer' => null, 'q' => '', 'settings' => []];
        try {
            $pdo = Database::connection();
            if ($tab === 'overview') {
                $data['stats'] = $pdo->query(
                    "SELECT
                        COALESCE(SUM(CASE WHEN status = 'available' AND (expires_at IS NULL OR expires_at > NOW()) THEN points_left END), 0) AS outstanding,
                        COALESCE(SUM(CASE WHEN status = 'pending' THEN points END), 0) AS pending,
                        COUNT(DISTINCT CASE WHEN status IN ('available','pending') THEN identity_id END) AS customers
                       FROM store_point_lots"
                )->fetch() ?: [];
                $data['stats'] += $pdo->query(
                    "SELECT
                        COALESCE(-SUM(CASE WHEN type IN ('spend','refund') THEN points END), 0) AS spent,
                        COALESCE(-SUM(CASE WHEN type = 'expire' THEN points END), 0) AS expired
                       FROM store_point_txns"
                )->fetch() ?: [];
                $data['stats'] += $pdo->query(
                    "SELECT COUNT(*) AS referrals, COALESCE(SUM(status = 'rewarded'), 0) AS rewarded,
                            COALESCE(SUM(flag_reason IS NOT NULL AND status <> 'rejected'), 0) AS flagged
                       FROM store_referrals"
                )->fetch() ?: [];
                foreach (array_merge(['store_points_enabled', 'store_points_launch_at'], array_keys(self::INT_SETTINGS)) as $k) {
                    $data['settings'][$k] = StoreSettings::get($k, '');
                }
            } elseif ($tab === 'referrals') {
                $flagged = !empty($request->query['flagged']);
                $data['referrals'] = $pdo->query(
                    "SELECT r.*, a.name AS referrer_name, a.phone AS referrer_phone, b.name AS referee_name, b.phone AS referee_phone,
                            o.order_no
                       FROM store_referrals r
                       LEFT JOIN patient_identities a ON a.id = r.referrer_identity_id
                       LEFT JOIN patient_identities b ON b.id = r.referee_identity_id
                       LEFT JOIN store_orders o ON o.id = r.qualifying_order_id
                      " . ($flagged ? "WHERE r.flag_reason IS NOT NULL AND r.status <> 'rejected'" : '') . "
                      ORDER BY (r.flag_reason IS NOT NULL AND r.status <> 'rejected') DESC, r.id DESC LIMIT 300"
                )->fetchAll();
                $data['flagged'] = $flagged;
            } else {
                $data['q'] = trim((string) ($request->query['q'] ?? ''));
                if ($data['q'] !== '') {
                    $data['customer'] = self::customer($data['q']);
                }
            }
        } catch (\Throwable $e) {
            error_log('[StoreRewardsAdmin::index] ' . $e->getMessage());
            SessionFlash::put('store_err', 'Points tables are missing: run app/database/patches/2026_10_07_store_points.sql.');
        }

        return Response::html(View::render('admin/store_rewards', $data + [
            'intSettings' => self::INT_SETTINGS,
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }

    public function saveSettings(Request $request): Response
    {
        $p = $request->post;
        $errors = [];
        $values = ['store_points_enabled' => !empty($p['store_points_enabled']) ? '1' : '0'];
        $launch = trim((string) ($p['store_points_launch_at'] ?? ''));
        if ($launch !== '') {
            $ts = strtotime($launch);
            if ($ts === false) {
                $errors[] = 'Launch date: pick a date and time, e.g. 10-10-2026 12:00 AM.';
            } else {
                $launch = date('Y-m-d H:i:s', $ts);
            }
        }
        if ($values['store_points_enabled'] === '1' && $launch === '') {
            $errors[] = 'Set the launch date before switching points on (only sign-ups after it get welcome points).';
        }
        $values['store_points_launch_at'] = $launch;
        foreach (self::INT_SETTINGS as $k => [$label, $min, $max]) {
            $raw = trim((string) ($p[$k] ?? ''));
            // The form shows ₹ and % so admins don't have to think in paise / basis points.
            $num = is_numeric($raw) ? (float) $raw : null;
            if ($num === null) {
                $errors[] = $label . ': enter a number.';
                continue;
            }
            $v = (int) round(str_ends_with($k, '_paise') ? $num * 100 : (str_ends_with($k, '_bp') ? $num * 100 : $num));
            if ($v < $min || $v > $max) {
                $errors[] = $label . ': out of range.';
                continue;
            }
            $values[$k] = (string) $v;
        }
        if ($errors) {
            SessionFlash::put('store_err', implode(' ', $errors));

            return Response::redirect('/admin/store/rewards');
        }
        foreach ($values as $k => $v) {
            StoreSettings::set($k, $v);
        }
        StoreAudit::log('points.settings', 'platform_settings', null, null, $values);
        SessionFlash::put('store_ok', 'Points settings saved. Changes apply to points given from now on; existing points keep their rules.');

        return Response::redirect('/admin/store/rewards');
    }

    public function adjust(Request $request): Response
    {
        $identityId = (int) ($request->post['identity_id'] ?? 0);
        $points = (int) ($request->post['points'] ?? 0);
        $res = PointsService::adjust($identityId, $points, (string) ($request->post['note'] ?? ''), $this->adminId());
        if ($res['ok']) {
            StoreAudit::log('points.adjust', 'patient_identity', $identityId, null, ['points' => $points]);
        }
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Points updated.' : ($res['error'] ?? 'Could not update points.'));

        return Response::redirect('/admin/store/rewards?tab=customer&q=' . rawurlencode((string) ($request->post['q'] ?? '')));
    }

    public function rejectReferral(Request $request, string $id): Response
    {
        $res = ReferralService::reject((int) $id, trim((string) ($request->post['reason'] ?? '')), $this->adminId());
        if ($res['ok']) {
            StoreAudit::log('points.referral_reject', 'referral', (int) $id);
        }
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Referral rejected; its pending points were cancelled.' : ($res['error'] ?? 'Could not reject.'));

        return Response::redirect('/admin/store/rewards?tab=referrals');
    }

    /** @return array<string, mixed>|null */
    private static function customer(string $q): ?array
    {
        $digits = substr(preg_replace('/\D/', '', $q), -10);
        if (strlen($digits) !== 10) {
            return null;
        }
        $st = Database::connection()->prepare(
            'SELECT id, name, phone, source, created_at FROM patient_identities WHERE phone LIKE :p ORDER BY id LIMIT 1'
        );
        $st->execute(['p' => '%' . $digits]);
        $p = $st->fetch();
        if (!$p) {
            return null;
        }
        $id = (int) $p['id'];
        $lots = Database::connection()->prepare(
            'SELECT * FROM store_point_lots WHERE identity_id = :i ORDER BY id DESC LIMIT 100'
        );
        $lots->execute(['i' => $id]);

        return [
            'identity' => $p,
            'summary' => PointsService::summary($id),
            'all_lots' => $lots->fetchAll(),
        ];
    }

    private function adminId(): int
    {
        return (int) (RequestContext::superAdmin()['id'] ?? 0);
    }
}
