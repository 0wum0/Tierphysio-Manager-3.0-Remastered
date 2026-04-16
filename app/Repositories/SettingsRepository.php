<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

/**
 * Settings repository with per-request in-memory cache.
 *
 * Changes from original:
 *  - Feature #1:  Per-request cache avoids repeated DB round-trips for the same key.
 *                 Cache auto-rebuilds on miss (self-healing).
 *  - Feature #9:  initDefaults() inserts missing settings without overwriting existing ones.
 */
class SettingsRepository extends Repository
{
    protected string $table      = 'settings';
    protected string $primaryKey = 'key';

    /**
     * Per-request in-memory cache.
     * Keys that don't exist in DB are stored as null (so we don't re-query them).
     *
     * @var array<string, mixed>
     */
    private array $cache = [];

    /** True once the entire table has been loaded into $cache via all(). */
    private bool $allCached = false;

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /* ──────────────────────────────────────────────────────────
       Core CRUD
    ────────────────────────────────────────────────────────── */

    /**
     * Get a single setting value.
     *
     * Self-healing cache: on miss the DB is queried once and the result
     * (even null) is cached for the remainder of the request.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Cache hit (null means "confirmed absent" – still avoids a DB call)
        if (array_key_exists($key, $this->cache)) {
            $cached = $this->cache[$key];
            return ($cached === null) ? $default : $cached;
        }

        // Cache miss → query DB, warm the cache
        try {
            $row = $this->db->fetch(
                "SELECT `value` FROM `{$this->t()}` WHERE `key` = ?",
                [$key]
            );
        } catch (\Throwable) {
            // DB error: return default without caching so the next call retries
            return $default;
        }

        $value = ($row !== false) ? $row['value'] : null;
        $this->cache[$key] = $value;

        return $value ?? $default;
    }

    /**
     * Persist a setting. Updates the in-memory cache immediately so
     * subsequent get() calls within the same request see the new value.
     */
    public function set(string $key, string $value): void
    {
        $this->db->execute(
            "INSERT INTO `{$this->t()}` (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [$key, $value]
        );

        // Keep cache consistent
        $this->cache[$key] = $value;
    }

    /**
     * Load all settings as a key→value map.
     *
     * On first call the entire table is fetched and cached; subsequent calls
     * within the same request are served entirely from memory.
     */
    public function all(): array
    {
        if ($this->allCached) {
            // Return non-null cache entries
            return array_filter(
                $this->cache,
                static fn($v) => $v !== null
            );
        }

        try {
            $rows = $this->db->fetchAll("SELECT `key`, `value` FROM `{$this->t()}`");
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $this->cache[$row['key']] = $row['value'];
            $result[$row['key']]      = $row['value'];
        }
        $this->allCached = true;

        return $result;
    }

    /* ──────────────────────────────────────────────────────────
       Feature #9 – Auto Settings Initialization
    ────────────────────────────────────────────────────────── */

    /**
     * Insert default settings for any key that is currently absent or empty.
     * NEVER overwrites an existing non-empty value.
     *
     * Usage example (call once after tenant prefix is set):
     *   $settings->initDefaults([
     *       'timezone'       => 'Europe/Berlin',
     *       'currency'       => 'EUR',
     *       'invoice_prefix' => 'RE-',
     *   ]);
     *
     * @param array<string, string> $defaults  Key → default value map
     */
    public function initDefaults(array $defaults): void
    {
        foreach ($defaults as $key => $value) {
            $existing = $this->get($key);
            if ($existing === null || $existing === '') {
                $this->set($key, (string)$value);
            }
        }
    }

    /* ──────────────────────────────────────────────────────────
       Cache control
    ────────────────────────────────────────────────────────── */

    /**
     * Invalidate the entire cache (e.g., after an external bulk update).
     * The next get() / all() call will re-query the database.
     */
    public function flushCache(): void
    {
        $this->cache     = [];
        $this->allCached = false;
    }

    /**
     * Invalidate a single cache entry.
     */
    public function invalidate(string $key): void
    {
        unset($this->cache[$key]);
    }
}
