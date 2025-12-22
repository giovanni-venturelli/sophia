<?php
namespace App\Database\Drivers;

use PDO;

class PostgresDriver implements DriverInterface {
    private PDO $pdo;

    public function __construct(array $config) {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? '5432';
        $dbname = $config['database'] ?? 'postgres';

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

        $this->pdo = new PDO($dsn,
            $config['username'] ?? 'postgres',
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
        $stmt = $this->pdo->query("SELECT currval(pg_get_serial_sequence('users', 'id'))"); // Esempio
        return $stmt->fetchColumn();
    }
}
