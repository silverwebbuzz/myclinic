<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Admin-managed state → city catalog used by "Listed on eClinicPro"
 * and other location pickers.
 */
final class LocationCatalogService
{
    /** @return list<array<string, mixed>> */
    public static function states(bool $activeOnly = false): array
    {
        try {
            $sql = 'SELECT * FROM directory_states';
            if ($activeOnly) {
                $sql .= ' WHERE is_active = 1';
            }
            $sql .= ' ORDER BY sort_order ASC, name ASC';

            return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function cities(?int $stateId = null, bool $activeOnly = false): array
    {
        try {
            $sql = 'SELECT c.*, s.name AS state_name
                    FROM directory_cities c
                    LEFT JOIN directory_states s ON s.id = c.state_id
                    WHERE 1=1';
            $params = [];
            if ($stateId !== null && $stateId > 0) {
                $sql .= ' AND c.state_id = :sid';
                $params['sid'] = $stateId;
            }
            if ($activeOnly) {
                $sql .= ' AND c.is_active = 1 AND (c.state_id IS NULL OR s.is_active = 1)';
            }
            $sql .= ' ORDER BY c.sort_order ASC, c.name ASC';

            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{ok: bool, error?: string, id?: int} */
    public static function saveState(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        $name = trim((string) ($post['name'] ?? ''));
        $slug = self::slugify($name);
        if ($name === '' || $slug === '') {
            return ['ok' => false, 'error' => 'missing_fields'];
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'country_code' => 'IN',
            'is_active' => isset($post['is_active']) ? 1 : 0,
            'sort_order' => (int) ($post['sort_order'] ?? 100),
        ];

        try {
            $pdo = Database::connection();
            if ($id > 0) {
                // Slug stays stable after create (used in unique keys / URLs).
                unset($data['slug']);
                $sets = implode(', ', array_map(static fn ($k) => "$k = :$k", array_keys($data)));
                $data['id'] = $id;
                $pdo->prepare("UPDATE directory_states SET $sets WHERE id = :id")->execute($data);
                // Keep denormalized city.state in sync when renaming.
                $pdo->prepare('UPDATE directory_cities SET state = :n WHERE state_id = :id')
                    ->execute(['n' => $name, 'id' => $id]);

                return ['ok' => true, 'id' => $id];
            }

            $cols = implode(', ', array_keys($data));
            $vals = implode(', ', array_map(static fn ($k) => ":$k", array_keys($data)));
            $pdo->prepare("INSERT INTO directory_states ($cols) VALUES ($vals)")->execute($data);

            return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'save_error'];
        }
    }

    /** @return array{ok: bool, error?: string, id?: int} */
    public static function saveCity(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        $stateId = (int) ($post['state_id'] ?? 0);
        $name = trim((string) ($post['name'] ?? ''));
        $slug = self::slugify($name);
        if ($stateId <= 0 || $name === '' || $slug === '') {
            return ['ok' => false, 'error' => 'missing_fields'];
        }

        $state = null;
        try {
            $stmt = Database::connection()->prepare('SELECT * FROM directory_states WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $stateId]);
            $state = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            $state = null;
        }
        if ($state === null) {
            return ['ok' => false, 'error' => 'invalid_state'];
        }

        $data = [
            'state_id' => $stateId,
            'name' => $name,
            'slug' => $slug,
            'state' => (string) $state['name'],
            'country_code' => 'IN',
            'is_active' => isset($post['is_active']) ? 1 : 0,
            'sort_order' => (int) ($post['sort_order'] ?? 100),
        ];

        try {
            $pdo = Database::connection();
            if ($id > 0) {
                unset($data['slug']);
                $sets = implode(', ', array_map(static fn ($k) => "$k = :$k", array_keys($data)));
                $data['id'] = $id;
                $pdo->prepare("UPDATE directory_cities SET $sets WHERE id = :id")->execute($data);

                return ['ok' => true, 'id' => $id];
            }

            $cols = implode(', ', array_keys($data));
            $vals = implode(', ', array_map(static fn ($k) => ":$k", array_keys($data)));
            $pdo->prepare("INSERT INTO directory_cities ($cols) VALUES ($vals)")->execute($data);

            return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'save_error'];
        }
    }

    public static function toggleState(int $id): ?int
    {
        if ($id <= 0) {
            return null;
        }
        try {
            $pdo = Database::connection();
            $pdo->prepare('UPDATE directory_states SET is_active = 1 - is_active WHERE id = ?')
                ->execute([$id]);
            $stmt = $pdo->prepare('SELECT is_active FROM directory_states WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $row === false ? null : (int) $row['is_active'];
        } catch (\Throwable) {
            return null;
        }
    }

    public static function toggleCity(int $id): ?int
    {
        if ($id <= 0) {
            return null;
        }
        try {
            $pdo = Database::connection();
            $pdo->prepare('UPDATE directory_cities SET is_active = 1 - is_active WHERE id = ?')
                ->execute([$id]);
            $stmt = $pdo->prepare('SELECT is_active FROM directory_cities WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $row === false ? null : (int) $row['is_active'];
        } catch (\Throwable) {
            return null;
        }
    }

    public static function tablesReady(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM directory_states LIMIT 1');
            Database::connection()->query('SELECT 1 FROM directory_cities LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Compact payload for doctor listing form (active only).
     *
     * @return array{states: list<array{id:int,name:string}>, citiesByState: array<string, list<array{id:int,name:string}>>}
     */
    public static function pickerPayload(): array
    {
        $states = [];
        $citiesByState = [];
        foreach (self::states(true) as $s) {
            $sid = (int) $s['id'];
            $states[] = ['id' => $sid, 'name' => (string) $s['name']];
            $citiesByState[(string) $sid] = [];
        }
        foreach (self::cities(null, true) as $c) {
            $sid = (int) ($c['state_id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $key = (string) $sid;
            if (!isset($citiesByState[$key])) {
                $citiesByState[$key] = [];
            }
            $citiesByState[$key][] = [
                'id' => (int) $c['id'],
                'name' => (string) $c['name'],
            ];
        }

        return ['states' => $states, 'citiesByState' => $citiesByState];
    }

    private static function slugify(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';

        return trim($s, '-');
    }
}
