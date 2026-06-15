<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class RemedyService
{
    /** @return list<array<string, mixed>> */
    public static function search(string $q, int $limit = 15): array
    {
        $q = trim($q);
        if (!Database::ping()) {
            return [];
        }

        // Ranked queries need the Phase-3 `usage_count` column / catalog
        // columns, which may be missing on older live DBs. Try the rich query;
        // on ANY SQL error fall back to a minimal, schema-safe query so the
        // autocomplete returns results instead of "Remedy search failed".
        try {
            return self::runSearch($q, $limit, true);
        } catch (\Throwable $e) {
            error_log('[RemedyService::search] ranked query failed, using safe fallback: ' . $e->getMessage());

            try {
                return self::runSearch($q, $limit, false);
            } catch (\Throwable $e2) {
                error_log('[RemedyService::search] safe fallback also failed: ' . $e2->getMessage());

                return [];
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private static function runSearch(string $q, int $limit, bool $ranked): array
    {
        $pdo = Database::connection();
        $cols = $ranked ? 'id, name, abbreviation, antidotes, dietary_restrictions' : 'id, name';
        $order = $ranked ? 'usage_count DESC, name ASC' : 'name ASC';

        if ($q === '') {
            $stmt = $pdo->prepare("SELECT {$cols} FROM remedies WHERE is_active = 1 ORDER BY {$order} LIMIT :lim");
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll() ?: [];
        }

        $stmt = $pdo->prepare(
            "SELECT {$cols} FROM remedies
             WHERE is_active = 1 AND (name LIKE :p OR abbreviation LIKE :p)
             ORDER BY {$order} LIMIT :lim",
        );
        $stmt->bindValue(':p', $q . '%');
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }

        $stmt = $pdo->prepare(
            "SELECT {$cols} FROM remedies
             WHERE is_active = 1 AND (name LIKE :p OR abbreviation LIKE :p)
             ORDER BY {$order} LIMIT :lim",
        );
        $stmt->bindValue(':p', '%' . $q . '%');
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public static function find(int $id): ?array
    {
        $row = QueryBuilder::table('remedies')->where('id', '=', $id)->first();

        return $row ?: null;
    }

    /** @return list<string> */
    public static function dietaryWarnings(array $remedy): array
    {
        $warnings = [];
        $diet = trim((string) ($remedy['dietary_restrictions'] ?? ''));
        if ($diet !== '') {
            $warnings[] = 'Dietary: ' . $diet;
        }
        $antidotes = $remedy['antidotes'] ?? null;
        if (is_string($antidotes)) {
            $antidotes = json_decode($antidotes, true);
        }
        if (is_array($antidotes)) {
            foreach ($antidotes as $a) {
                $warnings[] = 'Antidote: ' . (is_string($a) ? $a : ($a['name'] ?? json_encode($a)));
            }
        }

        return $warnings;
    }
}
