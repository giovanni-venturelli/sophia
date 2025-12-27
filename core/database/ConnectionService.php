<?php
namespace Sophia\Database;
use Sophia\Database\Drivers\DriverInterface;
use Sophia\Injector\Injectable;


#[Injectable(providedIn: "root")]
class ConnectionService {
    private ?DriverInterface $driver = null;
    private ?array $config = null;

    // NO costruttore! Config lazy da index.php

    public function configure(array $config): void {
        $this->config = $config;
        $this->driver = DriverFactory::create(
            $config['driver'],
            $config['credentials']
        );
    }

    public function table(string $table): QueryBuilder {
        $this->ensureConnected();
        return new QueryBuilder($this->driver, $table);
    }

    public function raw(string $sql, array $params = []): array {
        $this->ensureConnected();
        return $this->driver->query($sql, $params);
    }

    public function exec(string $sql, array $params = []): int {
        $this->ensureConnected();
        return $this->driver->exec($sql, $params);
    }

    private function ensureConnected(): void {
        if (!$this->driver) {
            throw new \RuntimeException(
                'ConnectionService::configure() must be called before use. ' .
                'See index.php example.'
            );
        }
    }

    public function isConnected(): bool {
        return $this->driver !== null;
    }
}
