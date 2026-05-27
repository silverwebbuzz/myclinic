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

        $pdo = Database::connection();

        // Phase 3: empty query → top-N by usage_count.
        if ($q === '') {
            $stmt = $pdo->prepare(
                'SELECT id, name, abbreviation, antidotes, dietary_restrictions
                 FROM remedies WHERE is_active = 1
                 ORDER BY usage_count DESC, name ASC
                 LIMIT :lim',
            );
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        }

        // Prefix match — fast and what doctors actually expect.
        $prefix = $q . '%';
        $stmt = $pdo->prepare(
            'SELECT id, name, abbreviation, antidotes, dietary_restrictions
             FROM remedies
             WHERE is_active = 1 AND (name LIKE :p OR abbreviation LIKE :p)
             ORDER BY usage_count DESC, name ASC
             LIMIT :lim',
        );
        $stmt->bindValue(':p', $prefix);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }

        // Fulltext fallback for substring matches against key_indications.
        $stmt = $pdo->prepare(
            'SELECT id, name, abbreviation, antidotes, dietary_restrictions
             FROM remedies
             WHERE is_active = 1
             AND MATCH(name, abbreviation, key_indications) AGAINST(:q IN BOOLEAN MODE)
             ORDER BY usage_count DESC
             LIMIT :lim',
        );
        $term = '+' . implode('* +', array_filter(explode(' ', $q))) . '*';
        $stmt->bindValue(':q', $term);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }

        // Final fallback: contains LIKE.
        $like = '%' . $q . '%';
        $fallback = $pdo->prepare(
            'SELECT id, name, abbreviation, antidotes, dietary_restrictions
             FROM remedies WHERE is_active = 1 AND (name LIKE ? OR abbreviation LIKE ?)
             ORDER BY usage_count DESC, name ASC LIMIT ?',
        );
        $fallback->execute([$like, $like, $limit]);

        return $fallback->fetchAll() ?: [];
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
