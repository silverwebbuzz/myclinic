<?php
declare(strict_types=1);

require_once __DIR__ . '/search_doctors_query.php';

/**
 * Build normalized find-a-doctor filters from request query params.
 *
 * @param array<string, mixed> $get
 * @param array<string, mixed>|null $seoMeta
 * @return array<string, mixed>
 */
function ecp_parse_find_doctor_filters(array $get, ?array $seoMeta = null): array
{
    $filters = [
        'q'          => trim((string) ($get['q'] ?? '')),
        'country'    => strtoupper(trim((string) ($get['country'] ?? 'IN'))) ?: 'IN',
        'state'      => trim((string) ($get['state'] ?? '')),
        'city'       => trim((string) ($seoMeta['filter_city'] ?? ($get['city'] ?? ''))),
        'area'       => trim((string) ($get['area'] ?? '')),
        'spec'       => trim((string) ($seoMeta['filter_spec'] ?? ($get['spec'] ?? 'all'))),
        'min_rating' => (float) ($get['min_rating'] ?? 0),
        'sort'       => trim((string) ($get['sort'] ?? 'relevance')),
        'page'       => max(1, (int) ($get['page'] ?? 1)),
        'per_page'   => min(50, max(1, (int) ($get['per_page'] ?? 20))),
        'loc'        => trim((string) ($get['loc'] ?? '')),
    ];

    if ($filters['spec'] === '' || $filters['spec'] === 'all') {
        $filters['spec'] = 'all';
    }

    // Free-text location field: resolve when structured fields were not supplied.
    if ($filters['loc'] !== '' && $filters['city'] === '' && $filters['state'] === '' && $filters['area'] === '') {
        $resolved = ecp_resolve_location_filter($filters['loc'], $filters['country']);
        foreach (['state', 'city', 'area'] as $key) {
            if (!empty($resolved[$key])) {
                $filters[$key] = (string) $resolved[$key];
            }
        }
    }

    return $filters;
}
