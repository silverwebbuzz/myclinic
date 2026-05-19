<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class QueryBuilder
{
    private string $table;

    private ?int $clinicId = null;

    /** @var array<int, array{0: string, 1: string, 2: mixed}> */
    private array $wheres = [];

    /** @var array<string, mixed> */
    private array $bindings = [];

    private ?int $limitValue = null;

    private ?int $offsetValue = null;

    private ?string $orderByColumn = null;

    private string $orderByDirection = 'ASC';

    private function __construct(string $table)
    {
        $this->table = $table;
    }

    public static function table(string $table): self
    {
        return new self($table);
    }

    public function forClinic(int $clinicId): self
    {
        $this->clinicId = $clinicId;

        return $this->where('clinic_id', '=', $clinicId);
    }

    public function where(string $column, string $operator, mixed $value = null): self
    {
        if ($value === null && !in_array(strtoupper($operator), ['IS', 'IS NOT'], true)) {
            $value = $operator;
            $operator = '=';
        }

        $param = 'p' . count($this->bindings);
        $this->wheres[] = [$column, strtoupper($operator), $value];
        $this->bindings[$param] = $value;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limitValue = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offsetValue = max(0, $offset);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderByColumn = $column;
        $this->orderByDirection = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        return $this;
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->table . $this->buildWhereSql();
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($this->bindings());

        return $stmt->rowCount();
    }

    /** @return array<int, array<string, mixed>> */
    public function get(): array
    {
        $sql = $this->buildSelect();
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($this->bindings());

        return $stmt->fetchAll();
    }

    public function first(): ?array
    {
        $this->limitValue = 1;
        $rows = $this->get();

        return $rows[0] ?? null;
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM ' . $this->table . $this->buildWhereSql();
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($this->bindings());

        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        if ($this->clinicId !== null && !isset($data['clinic_id'])) {
            $data['clinic_id'] = $this->clinicId;
        }

        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c) => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($data);

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): int
    {
        $sets = [];
        $updateBindings = [];
        foreach ($data as $column => $value) {
            $sets[] = "{$column} = :u_{$column}";
            $updateBindings["u_{$column}"] = $value;
        }

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets) . $this->buildWhereSql();
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_merge($updateBindings, $this->bindings()));

        return $stmt->rowCount();
    }

    public function getAppliedClinicId(): ?int
    {
        return $this->clinicId;
    }

    private function buildSelect(): string
    {
        $sql = 'SELECT * FROM ' . $this->table . $this->buildWhereSql();
        if ($this->orderByColumn !== null) {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $this->orderByColumn);
            $sql .= ' ORDER BY ' . $col . ' ' . $this->orderByDirection;
        }
        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . (int) $this->limitValue;
            if ($this->offsetValue !== null) {
                $sql .= ' OFFSET ' . (int) $this->offsetValue;
            }
        }

        return $sql;
    }

    private function buildWhereSql(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $parts = [];
        $i = 0;
        foreach ($this->wheres as [$column, $operator, $value]) {
            $param = 'p' . $i;
            if (in_array($operator, ['IS', 'IS NOT'], true)) {
                $parts[] = "{$column} {$operator} NULL";
            } else {
                $parts[] = "{$column} {$operator} :{$param}";
            }
            $i++;
        }

        return ' WHERE ' . implode(' AND ', $parts);
    }

    /** @return array<string, mixed> */
    private function bindings(): array
    {
        $out = [];
        $i = 0;
        foreach ($this->wheres as [, $operator, $value]) {
            if (!in_array($operator, ['IS', 'IS NOT'], true)) {
                $out['p' . $i] = $value;
            }
            $i++;
        }

        return $out;
    }
}
