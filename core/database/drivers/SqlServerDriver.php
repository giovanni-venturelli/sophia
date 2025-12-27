<?php
namespace Sophia\Database\Drivers;

use PDO;

class SqlServerDriver implements DriverInterface {
    private PDO $pdo;

    public function __construct(array $config) {
        $host = $config['host'] ?? 'localhost';
        $dbname = $config['database'] ?? 'master';

        $dsn = "sqlsrv:Server={$host};Database={$dbname}";

        $this->pdo = new PDO($dsn,
            $config['username'] ?? 'sa',
            $config['password'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
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
        $stmt = $this->pdo->query("SELECT SCOPE_IDENTITY()");
        return $stmt->fetchColumn();
    }
}
