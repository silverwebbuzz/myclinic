<?php

declare(strict_types=1);

namespace App\Gates;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Response;
use App\Services\RedisClient;

final class ModuleGate
{
    public static function check(string $moduleId): bool
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return false;
        }

        $modules = self::activeModules($clinicId);

        return in_array($moduleId, $modules, true);
    }

    public static function require(string $moduleId): ?Response
    {
        if (!self::check($moduleId)) {
            return Response::json([
                'error' => 'Module not active',
                'module' => $moduleId,
                'code' => 'MODULE_INACTIVE',
            ], 402);
        }

        return null;
    }

    /** @return list<string> */
    public static function activeModules(int $clinicId): array
    {
        $cacheKey = "clinic:{$clinicId}:modules";
        $cached = RedisClient::get($cacheKey);
        if ($cached !== null) {
            $decoded = json_decode($cached, true);

            return is_array($decoded) ? $decoded : [];
        }

        $rows = QueryBuilder::table('clinic_modules')
            ->forClinic($clinicId)
            ->where('is_active', '=', 1)
            ->get();

        $ids = array_map(static fn (array $r) => (string) $r['module_id'], $rows);
        RedisClient::setex($cacheKey, 600, json_encode($ids));

        return $ids;
    }

    public static function invalidateCache(int $clinicId): void
    {
        RedisClient::del("clinic:{$clinicId}:modules");
    }
}
