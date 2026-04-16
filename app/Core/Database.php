<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class Database
{
    private PDO $pdo;
    private Config $config;
    private string $tablePrefix = '';

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->connect();
    }

    public function setPrefix(string $prefix): void
    {
        $this->tablePrefix = $prefix;
    }

    public function getPrefix(): string
    {
        return $this->tablePrefix;
    }

    public function prefix(string $table): string
    {
        return $this->tablePrefix . $table;
    }

    /**
     * Returns the tenant-specific storage base path.
     * If a table prefix is set (e.g. "t_abc123_"), the storage is isolated under
     * STORAGE_PATH/tenants/{prefix_without_trailing_underscore}/
     * Falls back to plain STORAGE_PATH when no prefix is set (single-tenant / dev).
     *
     * Feature #4: Storage path is auto-created if missing (self-healing).
     */
    public function storagePath(string $subPath = ''): string
    {
        $base = defined('STORAGE_PATH') ? STORAGE_PATH : (dirname(__DIR__, 2) . '/storage');

        if ($this->tablePrefix !== '') {
            /* Strip trailing underscore: "t_abc123_" → "t_abc123" */
            $slug = rtrim($this->tablePrefix, '_');
            $base = $base . '/tenants/' . $slug;

            /* Auto-recreate the base tenant directory if it was deleted externally.
             * Feature #4: Tenant storage auto-creation – self-healing on every access.
             * Individual sub-directories (patients/, uploads/, …) are created by
             * the respective upload handlers via mkdir($path, 0755, true).
             * This single @mkdir here ensures the root is always present so that
             * the recursive mkdir in upload handlers cannot fail due to a missing
             * parent directory on a tenant whose storage was wiped externally. */
            if (!is_dir($base)) {
                @mkdir($base, 0755, true);
            }
        }

        if ($subPath !== '') {
            $fullPath = $base . '/' . ltrim($subPath, '/');

            /* Feature #4: Also auto-create sub-directories on access. */
            if (!is_dir($fullPath)) {
                @mkdir($fullPath, 0755, true);
            }

            return $fullPath;
        }

        return $base;
    }

    private function connect(): void
    {
        $host     = $this->config->get('db.host');
        $port     = $this->config->get('db.port');
        $database = $this->config->get('db.database');
        $username = $this->config->get('db.username');
        $password = $this->config->get('db.password');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
            if (!defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function createFromCredentials(string $host, int $port, string $database, string $username, string $password): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Connect directly to the existing database.
        // On shared hosting (Hostinger etc.) CREATE DATABASE is not permitted;
        // the database must already exist and be created via the hosting panel.
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, $options);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

        return $pdo;
    }

    /* ──────────────────────────────────────────────────────────
       Standard DB access (throw on error – existing behaviour)
    ────────────────────────────────────────────────────────── */

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
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function insert(string $sql, array $params = []): string
    {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

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

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /* ──────────────────────────────────────────────────────────
       Feature #10 – Safe DB Access Layer
       These methods wrap the standard access methods with try-catch.
       They return safe defaults instead of throwing, making them
       ideal for resilient background tasks and health-check code.
       All exceptions are silently swallowed here; callers that need
       to inspect the error should use the standard throwing methods.
    ────────────────────────────────────────────────────────── */

    /**
     * Like fetch() but returns null on any exception instead of throwing.
     */
    public function safeFetch(string $sql, array $params = []): ?array
    {
        try {
            $result = $this->fetch($sql, $params);
            return $result !== false ? $result : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Like fetchAll() but returns [] on any exception instead of throwing.
     */
    public function safeFetchAll(string $sql, array $params = []): array
    {
        try {
            return $this->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Like fetchColumn() but returns null on any exception instead of throwing.
     */
    public function safeFetchColumn(string $sql, array $params = []): mixed
    {
        try {
            return $this->fetchColumn($sql, $params);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Like execute() but returns false on any exception instead of throwing.
     * Returns the row count on success, false on failure.
     */
    public function safeExecute(string $sql, array $params = []): int|false
    {
        try {
            return $this->execute($sql, $params);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Like insert() but returns null on any exception instead of throwing.
     */
    public function safeInsert(string $sql, array $params = []): ?string
    {
        try {
            return $this->insert($sql, $params);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Like query() but returns null on any exception instead of throwing.
     */
    public function safeQuery(string $sql, array $params = []): ?PDOStatement
    {
        try {
            return $this->query($sql, $params);
        } catch (\Throwable) {
            return null;
        }
    }
}
