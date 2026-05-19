<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;

final class DocsController
{
    public function index(Request $request): Response
    {
        return Response::html($this->swaggerUi());
    }

    public function openapi(Request $request): Response
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'ManageClinic API',
                'version' => '1.0.0',
                'description' => 'REST API v1 — authenticate with Bearer API key from Settings → API.',
            ],
            'servers' => [
                ['url' => ($_ENV['APP_URL'] ?? 'http://localhost:8080') . '/api/v1/rest'],
            ],
            'security' => [['bearerAuth' => []]],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'API key prefixed with mc_live_',
                    ],
                ],
            ],
            'paths' => [
                '/patients' => [
                    'get' => ['summary' => 'List patients', 'responses' => ['200' => ['description' => 'OK']]],
                    'post' => ['summary' => 'Create patient', 'responses' => ['201' => ['description' => 'Created']]],
                ],
                '/patients/{id}' => [
                    'get' => ['summary' => 'Get patient', 'responses' => ['200' => ['description' => 'OK']]],
                ],
                '/appointments' => [
                    'get' => ['summary' => 'Calendar appointments', 'responses' => ['200' => ['description' => 'OK']]],
                    'post' => ['summary' => 'Book appointment', 'responses' => ['201' => ['description' => 'Created']]],
                ],
                '/visits' => [
                    'get' => ['summary' => 'Recent visits', 'responses' => ['200' => ['description' => 'OK']]],
                ],
                '/invoices' => [
                    'get' => ['summary' => 'List invoices', 'responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
        ];

        return Response::json($spec);
    }

    private function swaggerUi(): string
    {
        $specUrl = '/docs/openapi.json';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>ManageClinic API Docs</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    SwaggerUIBundle({ url: '{$specUrl}', dom_id: '#swagger-ui' });
  </script>
</body>
</html>
HTML;
    }
}
