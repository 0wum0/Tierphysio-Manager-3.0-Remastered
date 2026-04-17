<?php

declare(strict_types=1);

namespace Saas\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class Database
{
    private PDO $pdo;

    public function __construct(private Config $config)
    {
        $this->connect();
    }

    private function connect(): void
    {
        $host     = $this->config->get('db.host');
        $port     = $this->config->get('db.port');
        $database = $this->config->get('db.database');
        $username = $this->config->get('db.username');
        $password = $this->config->get('db.password');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES         => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::MYSQL_ATTR_INIT_COMMAND       => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('SaaS-Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Wrap an already-open PDO connection in a Database instance.
     * Useful in CLI scripts / cron runners that bootstrap PDO themselves.
     */
    public static function fromPdo(PDO $pdo): self
    {
        $obj  = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $prop = (new \ReflectionClass(self::class))->getProperty('pdo');
        $prop->setValue($obj, $pdo);
        return $obj;
    }

    public static function createBare(string $host, int $port, string $username, string $password): self
    {
        $tmp = new \stdClass();
        $instance = new \ReflectionClass(self::class);
        $obj = $instance->newInstanceWithoutConstructor();

        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES         => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]);

        $prop = $instance->getProperty('pdo');
        $prop->setValue($obj, $pdo);

        return $obj;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function insert(string $sql, array $params = []): string
    {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void           { $this->pdo->commit(); }
    public function rollback(): void         { $this->pdo->rollBack(); }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function tableExists(string $table): bool
    {
        try {
            $result = $this->fetchColumn(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                [$table]
            );
            return (int)$result > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
