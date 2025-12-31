<?php

namespace Sophia\Database;

use ReflectionClass;
use Sophia\Injector\Injector;
use Throwable;

abstract class Entity implements \JsonSerializable
{
    protected static ?ConnectionService $db = null;
    protected static ?string $table = null;
    protected static string $primaryKey = 'id';

    protected array $attributes = [];
    protected array $original = [];
    protected static array $fillable = [];
    protected static array $hidden = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->original = $this->attributes;
    }

    public static function getDb(): ConnectionService
    {
        if (static::$db === null) {
            static::$db = Injector::inject(ConnectionService::class);
        }
        return static::$db;
    }

    // ✅ CORRETTO: array|string|array $columns = ['*']
    public static function query(array|string $columns = ['*']): QueryBuilder
    {
        $table = static::getTableName();
        $qb = static::getDb()->table($table);

        if ($columns !== ['*']) {
            $qb->select($columns);
        }

        return $qb;
    }

    public static function getTableName(): string
    {
        return static::$table !== ''
            ? static::$table
            : strtolower((new \ReflectionClass(static::class))->getShortName()) . 's';
    }

    public static function where(string $column, $operatorOrValue = null, $value = null): QueryBuilder
    {
        $builder = static::query();

        if ($value === null) {
            return $builder->where($column, $operatorOrValue);
        }

        return $builder->where($column, $operatorOrValue, $value);
    }

    public static function whereIn(string $column, array $values): QueryBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    public static function find($id, array $columns = ['*']): ?static
    {
        $row = static::query($columns)
            ->where(static::$primaryKey, $id)
            ->first();

        return $row ? new static($row) : null;
    }

    public static function findOrFail($id, array $columns = ['*']): static
    {
        $model = static::find($id, $columns);
        if (!$model) {
            throw new \RuntimeException(static::class . " with ID {$id} not found");
        }
        return $model;
    }

    public static function all(array $columns = ['*']): array
    {
        $rows = static::query($columns)->get();
        return static::hydrate($rows);
    }

    public static function create(array $data): static
    {
        $fillableData = static::filterFillable($data);
        $id = static::query()->create($fillableData);

        $model = new static($fillableData);
        $model->{static::$primaryKey} = $id;
        $model->original = $model->attributes;
        return $model;
    }

    public static function createMany(array $data): array
    {
        $models = [];
        foreach ($data as $item) {
            $models[] = static::create($item);
        }
        return $models;
    }

    public function save(): bool
    {
        $pk = static::$primaryKey;
        if (!isset($this->attributes[$pk]) || empty($this->attributes[$pk])) {
            $fillableData = static::filterFillable($this->attributes);
            $id = static::query()->create($fillableData);
            $this->attributes[$pk] = $id;
        } else {
            $changes = $this->getDirty();
            if (empty($changes)) {
                return true;
            }

            $fillableChanges = static::filterFillable($changes);
            static::query()
                ->where($pk, $this->attributes[$pk])
                ->update($fillableChanges);

            $this->original = $this->attributes;
        }
        return true;
    }

    public function update(array $data): bool
    {
        $fillableData = static::filterFillable($data);
        foreach ($fillableData as $key => $value) {
            $this->attributes[$key] = $value;
        }
        return $this->save();
    }

    public function delete(): bool
    {
        $pk = static::$primaryKey;
        if (!isset($this->attributes[$pk])) {
            return false;
        }

        static::query()
            ->where($pk, $this->attributes[$pk])
            ->delete();

        return true;
    }

    protected static function filterFillable(array $data): array
    {
        if (empty(static::$fillable)) {
            return $data;
        }

        $filtered = [];
        foreach (static::$fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = $data[$field];
            }
        }
        return $filtered;
    }

    public function fill(array $attributes): void
    {
        $filtered = static::filterFillable($attributes);
        foreach ($filtered as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) ||
                $this->original[$key] != $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function isDirty(): bool
    {
        return !empty($this->getDirty());
    }

    public function toArray(): array
    {
        $array = $this->attributes;
        foreach (static::$hidden as $field) {
            unset($array[$field]);
        }
        return $array;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public static function hydrate(array $rows): array
    {
        return array_map(fn($row) => new static($row), $rows);
    }

    public function refresh(): static
    {
        $pk = static::$primaryKey;
        $fresh = static::find($this->attributes[$pk]);
        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->original = $this->attributes;
        }
        return $this;
    }
}
