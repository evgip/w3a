<?php

declare(strict_types=1);

namespace App\Core\Cache;

use App\Core\Database;

class DatabaseCache
{
    private Database $db;
    private FileCache $cache;
    private bool $enabled;
    private int $defaultTtl;

    public function __construct(Database $db, FileCache $cache, bool $enabled = true, int $defaultTtl = 3600)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->enabled = $enabled;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Выполнить SELECT запрос с кэшированием
     */
    public function cachedQuery(string $sql, array $params = [], int $ttl = null): array
    {
        if (!$this->enabled) {
            return $this->db->fetchAll($sql, $params);
        }

        $ttl = $ttl ?? $this->defaultTtl;
        $cacheKey = $this->buildCacheKey($sql, $params);

        // Пытаемся получить из кэша
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Выполняем запрос
        $result = $this->db->fetchAll($sql, $params);

        // Сохраняем в кэш
        $this->cache->set($cacheKey, $result, $ttl);

        return $result;
    }

    /**
     * Выполнить SELECT один результат с кэшированием
     */
    public function cachedQueryOne(string $sql, array $params = [], int $ttl = null): ?array
    {
        if (!$this->enabled) {
            return $this->db->fetchOne($sql, $params);
        }

        $ttl = $ttl ?? $this->defaultTtl;
        $cacheKey = $this->buildCacheKey($sql, $params);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->db->fetchOne($sql, $params);

        if ($result !== null) {
            $this->cache->set($cacheKey, $result, $ttl);
        }

        return $result;
    }

    /**
     * Очистить кэш для конкретного запроса
     */
    public function invalidate(string $sql, array $params = []): void
    {
        $cacheKey = $this->buildCacheKey($sql, $params);
        $this->cache->delete($cacheKey);
    }

    /**
     * Очистить весь кэш запросов
     */
    public function clearAll(): void
    {
        $this->cache->clear();
    }

    private function buildCacheKey(string $sql, array $params): string
    {
        return 'db_' . md5($sql . serialize($params));
    }
}