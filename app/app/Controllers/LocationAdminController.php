<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\LocationCatalogService;
use App\Support\View;

/** /admin/locations — manage directory states & cities (super-admin). */
final class LocationAdminController
{
    public function index(Request $request): Response
    {
        $ready = LocationCatalogService::tablesReady();

        return Response::html(View::render('admin/locations', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'states' => $ready ? LocationCatalogService::states(false) : [],
            'cities' => $ready ? LocationCatalogService::cities(null, false) : [],
            'tableMissing' => !$ready,
            'message' => $request->query['message'] ?? null,
            'tab' => in_array(($request->query['tab'] ?? 'states'), ['states', 'cities'], true)
                ? (string) $request->query['tab']
                : 'states',
        ]));
    }

    public function saveState(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/locations?tab=states');
        }
        $res = LocationCatalogService::saveState($request->post);

        return Response::redirect('/admin/locations?tab=states&message=' . ($res['ok'] ? 'saved' : ($res['error'] ?? 'save_error')));
    }

    public function saveCity(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/locations?tab=cities');
        }
        $res = LocationCatalogService::saveCity($request->post);

        return Response::redirect('/admin/locations?tab=cities&message=' . ($res['ok'] ? 'saved' : ($res['error'] ?? 'save_error')));
    }

    public function toggleState(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/locations?tab=states');
        }
        $active = LocationCatalogService::toggleState((int) $id);
        $message = $active === 1 ? 'active' : ($active === 0 ? 'inactive' : 'save_error');

        return Response::redirect('/admin/locations?tab=states&message=' . $message);
    }

    public function toggleCity(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/locations?tab=cities');
        }
        $active = LocationCatalogService::toggleCity((int) $id);
        $message = $active === 1 ? 'active' : ($active === 0 ? 'inactive' : 'save_error');

        return Response::redirect('/admin/locations?tab=cities&message=' . $message);
    }
}
