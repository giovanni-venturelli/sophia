<?php
namespace App\Database;
use App\Database\Drivers\MysqlDriver;
use App\Database\Drivers\SqliteDriver;
use App\Database\Drivers\PostgresDriver;
use App\Database\Drivers\SqlServerDriver;
use App\Database\Drivers\DriverInterface;
use InvalidArgumentException;

class DriverFactory {
    public static function create(string $driver, array $config): DriverInterface {
        return match($driver) {
            'mysql' => new MysqlDriver($config),
            'sqlite' => new SqliteDriver($config),
            'postgres' => new PostgresDriver($config),
            'sqlserver' => new SqlServerDriver($config),
            default => throw new InvalidArgumentException("Driver {$driver} not supported")
        };
    }
}
