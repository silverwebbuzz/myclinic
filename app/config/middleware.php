<?php

declare(strict_types=1);

use App\Middleware\ApiBearerMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\ModuleGateMiddleware;
use App\Middleware\PartnerAuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RbacMiddleware;
use App\Middleware\RefreshTokenMiddleware;
use App\Middleware\SubscriptionMiddleware;
use App\Middleware\SuperAdminAuthMiddleware;
use App\Middleware\TenantMiddleware;

return [
    'refresh' => RefreshTokenMiddleware::class,
    'tenant' => TenantMiddleware::class,
    'auth' => AuthMiddleware::class,
    'subscription' => SubscriptionMiddleware::class,
    'rbac' => RbacMiddleware::class,
    'module' => ModuleGateMiddleware::class,
    'csrf' => CsrfMiddleware::class,
    'rate' => RateLimitMiddleware::class,
    'superadmin' => SuperAdminAuthMiddleware::class,
    'partner' => PartnerAuthMiddleware::class,
    'api_bearer' => ApiBearerMiddleware::class,
];
