<?php
namespace App\Database\Drivers;

interface DriverInterface {
    public function query(string $sql, array $params = []): array;
    public function exec(string $sql, array $params = []): int;
    public function lastInsertId(): int|string;
}
