<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\MarketingService;
use App\Support\View;

/**
 * /admin/marketing — doctor list for manual WhatsApp outreach.
 *
 * Filter all directory listings, open a doctor's personalised draft, copy it
 * or open it in WhatsApp, and send it by hand. Nothing here ever sends a
 * message; "Mark sent" only records that the admin did.
 */
final class MarketingAdminController
{
    /** GET /admin/marketing */
    public function index(Request $request): Response
    {
        $filters = self::filtersFromRequest($request);
        $page = max(1, (int) ($request->query['page'] ?? 1));

        $total = MarketingService::count($filters);
        $perPage = MarketingService::PER_PAGE;

        return Response::html(View::render('admin/marketing', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'rows' => MarketingService::page($filters, $page),
            'filters' => $filters,
            'options' => MarketingService::filterOptions($filters['state']),
            'page' => $page,
            'pages' => (int) max(1, ceil($total / $perPage)),
            'total' => $total,
        ]));
    }

    /** POST /admin/marketing/sent — JSON; the admin confirms they sent it. */
    public function markSent(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::json(['ok' => false, 'error' => 'csrf'], 419);
        }
        $admin = RequestContext::superAdmin();
        $result = MarketingService::markSent(
            (int) ($request->post['directory_doctor_id'] ?? 0),
            isset($admin['id']) ? (int) $admin['id'] : null,
            ($request->post['again'] ?? '') === '1',
        );
        return Response::json($result, $result['ok'] ? 200 : 422);
    }

    /** GET /admin/marketing/templates */
    public function templates(Request $request): Response
    {
        return self::renderTemplates(MarketingService::templates(), (string) ($request->query['message'] ?? ''), [], null);
    }

    /** POST /admin/marketing/templates — save, or preview without saving. */
    public function saveTemplates(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/marketing/templates');
        }

        // Rebuild the map from the form: default + one row per specialty override.
        $templates = ['default' => (string) ($request->post['default'] ?? '')];
        $codes = (array) ($request->post['code'] ?? []);
        $bodies = (array) ($request->post['body'] ?? []);
        foreach ($codes as $i => $code) {
            $code = trim((string) $code);
            $body = (string) ($bodies[$i] ?? '');
            if ($code !== '' && $code !== 'default' && trim($body) !== '') {
                $templates[$code] = $body;
            }
        }

        $errors = [];
        foreach ($templates as $code => $tpl) {
            if (trim($tpl) === '' && $code === 'default') {
                $errors[] = 'The default template cannot be empty.';
            }
            $bad = MarketingService::unknownPlaceholders($tpl);
            if ($bad) {
                $errors[] = ($code === 'default' ? 'Default' : $code) . ': unknown placeholder ' . implode(', ', $bad);
            }
        }

        $previewCode = (string) ($request->post['preview'] ?? '');
        if ($previewCode !== '' || $errors) {
            return self::renderTemplates($templates, '', $errors, $previewCode !== '' ? $previewCode : null);
        }

        MarketingService::saveTemplates($templates);
        return Response::redirect('/admin/marketing/templates?message=saved');
    }

    /**
     * @param array<string,string> $templates
     * @param list<string> $errors
     */
    private static function renderTemplates(array $templates, string $message, array $errors, ?string $previewCode): Response
    {
        $preview = null;
        if ($previewCode !== null && isset($templates[$previewCode])) {
            $sample = MarketingService::sampleRow($previewCode);
            $preview = [
                'code' => $previewCode,
                'sample' => $sample,
                'text' => $sample !== null ? MarketingService::render($templates[$previewCode], $sample) : null,
            ];
        }

        return Response::html(View::render('admin/marketing_templates', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'templates' => $templates,
            'labels' => MarketingService::specialtyLabels(),
            'specialties' => MarketingService::filterOptions()['specialties'],
            'placeholders' => MarketingService::PLACEHOLDERS,
            'message' => $message,
            'errors' => $errors,
            'preview' => $preview,
        ]));
    }

    /** @return array<string,mixed> */
    private static function filtersFromRequest(Request $request): array
    {
        $q = $request->query;
        $str = static fn (string $k, string $def = ''): string => is_string($q[$k] ?? null) ? trim($q[$k]) : $def;
        $date = static fn (string $k): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $str($k)) === 1 ? $str($k) : '';

        return [
            'spec' => array_values(array_filter(array_map('strval', (array) ($q['spec'] ?? [])), fn ($s) => $s !== '')),
            'state' => $str('state'),
            'city' => $str('city'),
            'q' => $str('q'),
            'phone' => $str('phone'),
            // Unclaimed by default: claimed listings already joined.
            'claimed' => $str('claimed', '0'),
            'listed' => $str('listed'),
            'sent' => $str('sent'),
            'sent_from' => $date('sent_from'),
            'sent_to' => $date('sent_to'),
            'leads' => $str('leads'),
            'optout' => $str('optout', 'exclude'),
            'sort' => $str('sort', 'quality'),
            'dir' => $str('dir', 'desc'),
        ];
    }
}
