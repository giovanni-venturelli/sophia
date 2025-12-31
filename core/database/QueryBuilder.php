<?php

namespace Sophia\DB;

use Sophia\DB\Driver\DriverInterface;

class QueryBuilder
{
    private DriverInterface $driver;
    private string $table;
    private array $selectColumns = ['*'];  // ✅ NUOVO: Colonne da selezionare
    private array $wheres = [];
    private array $joins = [];
    private ?string $orderBy = null;
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(DriverInterface $driver, string $table)
    {
        $this->driver = $driver;
        $this->table = $table;
    }

    // ✅ NUOVO: Specifica colonne da selezionare
    public function select(array|string $columns): self
    {
        $this->selectColumns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    // ✅ NUOVO: Alias per select (compatibilità)
    public function columns(array|string $columns): self
    {
        return $this->select($columns);
    }

    // WHERE
    public function where(string $column, $value, string $operator = '='): self
    {
        $this->wheres[] = [$column, $value, $operator];
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = ['IN', $column, $values];
        return $this;
    }

    // JOIN
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'type' => strtoupper($type)
        ];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    // ORDER / LIMIT
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = $column . ' ' . strtoupper($direction);
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    // ✅ AGGIORNATO: Usa selectColumns
    public function get(): array
    {
        return $this->driver->query($this->buildSelectSql(), $this->buildSelectParams());
    }

    public function first(): ?array
    {
        return $this->limit(1)->get()[0] ?? null;
    }

    // ✅ AGGIORNATO: Usa selectColumns per COUNT
    public function count(): int
    {
        $sql = $this->buildSelectSql(true);
        $params = $this->buildSelectParams();
        $result = $this->driver->query($sql, $params);
        return (int) ($result[0]['count'] ?? 0);
    }

    // INSERT/UPDATE/DELETE (invariati)
    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $this->driver->exec($sql, array_values($data));
        return $this->driver->lastInsertId();
    }

    public function update(array $data): int
    {
        $setParts = [];
        $params = [];
        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = ?";
            $params[] = $value;
        }
        $setClause = implode(', ', $setParts);
        $whereClause = $this->buildWhereClause($params);
        $sql = "UPDATE {$this->table} SET {$setClause} {$whereClause}";
        return $this->driver->exec($sql, $params);
    }

    public function delete(): int
    {
        $whereClause = $this->buildWhereClause();
        $sql = "DELETE FROM {$this->table} {$whereClause}";
        return $this->driver->exec($sql, $this->buildSelectParams());
    }

    // ✅ AGGIORNATO: Supporta selectColumns e count mode
    private function buildSelectSql(bool $count = false): string
    {
        $columns = $count
            ? 'COUNT(*) as count'
            : implode(', ', array_map(fn($col) => is_string($col) ? $col : '*', $this->selectColumns));

        $sql = "SELECT {$columns} FROM {$this->table}";

        // JOINS
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['table']}.{$join['first']} {$join['operator']} {$join['second']}";
        }

        // WHERE
        $whereSql = $this->buildWhereClause();
        if ($whereSql) {
            $sql .= ' ' . $whereSql;
        }

        // ORDER
        if ($this->orderBy) {
            $sql .= ' ORDER BY ' . $this->orderBy;
        }

        // LIMIT/OFFSET
        if ($this->limit) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }

        return $sql;
    }

    private function buildSelectParams(): array
    {
        $params = [];
        foreach ($this->wheres as $where) {
            if ($where[0] === 'IN') {
                $params = array_merge($params, $where[2]);
            } else {
                $params[] = $where[1];
            }
        }
        return $params;
    }

    private function buildWhereClause(?array &$params = null): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $conditions = [];
        foreach ($this->wheres as $where) {
            if ($where[0] === 'IN') {
                $placeholders = implode(',', array_fill(0, count($where[2]), '?'));
                $conditions[] = "{$where[1]} IN ({$placeholders})";
            } else {
                $op = match($where[2]) {
                    '!=' => '<>',
                    '!==' => '<>',
                    '!' => 'NOT',
                    'LIKE' => 'LIKE',
                    default => $where[2]
                };
                $conditions[] = "{$where[0]} {$op} ?";
            }
        }

        return 'WHERE ' . implode(' AND ', $conditions);
    }
}
