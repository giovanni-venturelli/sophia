<?php
namespace App\Database\Drivers;

use PDO;

class SqliteDriver implements DriverInterface {
    private \PDO $pdo;

    public function __construct(array $config) {
        $database = $config['database'] ?? 'app.db';
        $dsn = "sqlite:" . realpath($database);

        $this->pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    public function query(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function exec(string $sql, array $params = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function lastInsertId(): int|string {
        return $this->pdo->lastInsertId();
    }
}
