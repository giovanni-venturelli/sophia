<?php

namespace App\Database;

use App\Injector\Injectable;
use App\Injector\Inject;
use App\Injector\Injector;
use Throwable;

#[Injectable(providedIn: "root")]
abstract class Entity
{
    /**
     * ✅ LAZY CONNECTION - Risolve il problema static::$db
     */
    protected static ?ConnectionService $db = null;

    protected static string $table = '';
    protected static string $primaryKey = 'id';

    protected array $attributes = [];
    protected array $original = [];
    protected array $fillable = [];
    protected array $hidden = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->original = $this->attributes;
    }

    /**
     * ✅ GETTER LAZY - Inizializza ConnectionService solo quando serve
     */
    public static function getDb(): ConnectionService
    {
        if (static::$db === null) {
            static::$db = Injector::inject(ConnectionService::class);
        }
        return static::$db;
    }

    public static function query(): QueryBuilder
    {
        $table = static::getTableName();
        return static::getDb()->table($table);
    }

    public static function getTableName(): string
    {
        return static::$table !== ''
            ? static::$table
            : strtolower((new \ReflectionClass(static::class))->getShortName()) . 's';
    }

    /**
     * Query Scopes (Laravel-style)
     */
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

    public static function find($id): ?static
    {
        $row = static::query()
            ->where(static::$primaryKey, $id)
            ->first();

        return $row ? new static($row) : null;
    }

    public static function findOrFail($id): static
    {
        $model = static::find($id);
        if (!$model) {
            throw new \RuntimeException(static::class . " with ID {$id} not found");
        }
        return $model;
    }

    public static function all(): array
    {
        $rows = static::query()->get();
        return static::hydrate($rows);
    }

    public static function create(array $data): static
    {
        $fillableData = static::filterFillable($data);
        $id = static::query()->create($fillableData);

        $model = new static($fillableData);
        $model->attributes[static::$primaryKey] = $id;
        $model->original = $model->attributes;

        return $model;
    }

    /**
     * Batch operations
     */
    public static function createMany(array $data): array
    {
        $models = [];
        foreach ($data as $item) {
            $models[] = static::create($item);
        }
        return $models;
    }

    /**
     * Instance Methods
     */
    public function save(): bool
    {
        $pk = static::$primaryKey;

        if (!isset($this->attributes[$pk]) || empty($this->attributes[$pk])) {
            // INSERT
            $fillableData = static::filterFillable($this->attributes);
            $id = static::query()->create($fillableData);
            $this->attributes[$pk] = $id;
        } else {
            // UPDATE
            $changes = $this->getDirty();
            if (empty($changes)) {
                return true;
            }

            $fillableChanges = static::filterFillable($changes);
            static::query()
                ->where($pk, $this->attributes[$pk])
                ->update($fillableChanges);
        }

        $this->original = $this->attributes;
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

    /**
     * Mass Assignment Protection
     */
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
            if (
                !array_key_exists($key, $this->original) ||
                $this->original[$key] !== $value
            ) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function isDirty(): bool
    {
        return !empty($this->getDirty());
    }

    /**
     * Array/JSON conversion
     */
    public function toArray(): array
    {
        $array = $this->attributes;

        // Rimuovi hidden fields
        foreach (static::$hidden as $field) {
            unset($array[$field]);
        }

        return $array;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Magic Methods
     */
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

    /**
     * Hydration Helper
     */
    public static function hydrate(array $rows): array
    {
        return array_map(fn($row) => new static($row), $rows);
    }

    /**
     * Refresh from database
     */
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