<?php
namespace Sophia\Database;
use Sophia\Database\Drivers\MysqlDriver;
use Sophia\Database\Drivers\SqliteDriver;
use Sophia\Database\Drivers\PostgresDriver;
use Sophia\Database\Drivers\SqlServerDriver;
use Sophia\Database\Drivers\DriverInterface;
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
