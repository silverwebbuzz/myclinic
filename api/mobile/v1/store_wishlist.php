<?php
// =====================================================================
// api/mobile/v1/store_wishlist.php — saved store products. Bearer.
//
// Same data + toggle as the web (store/wishlist.php, api/store_wishlist.php):
// store_wishlist_toggle() and store_products_simple() in store/_lib.php.
// Products that stop being visible drop out of the list automatically.
//
//   GET  ?action=list                    → { products:[card…], count }
//   POST ?action=toggle  { product_id }  → { saved, count }
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_store.php';

ecp_ms_boot();
$me = ecp_m_require_patient();
$identityId = (int) $me['id'];

$action = (string) ($_GET['action'] ?? 'list');

switch ($action) {

    case 'list': {
        ecp_m_require_method('GET');
        $rows = store_products_simple(
            'AND EXISTS (SELECT 1 FROM store_wishlist w WHERE w.product_id = p.id AND w.identity_id = :i)',
            ['i' => $identityId],
            'p.in_stock DESC, p.name',
            48
        );
        ecp_m_ok(['products' => ecp_ms_cards($rows), 'count' => count($rows)]);
        break;
    }

    case 'toggle': {
        ecp_m_require_method('POST');
        $in = ecp_m_input();
        $res = store_wishlist_toggle($identityId, (int) ($in['product_id'] ?? 0));
        if (!$res['ok']) {
            $status = ['db_unavailable' => 503, 'product_required' => 400, 'product_not_found' => 404][$res['error']] ?? 400;
            ecp_m_err((string) $res['error'], $status);
        }
        ecp_m_ok(['saved' => $res['saved'], 'count' => $res['count']]);
        break;
    }

    default:
        ecp_m_err('unknown_action', 400);
}
