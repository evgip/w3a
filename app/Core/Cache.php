<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Простой file-based кэш
 * 
 * Поддерживает:
 * - Кэширование любых данных (сериализация)
 * - TTL (время жизни)
 * - Теги для групповой инвалидации
 * - Автоматическую очистку устаревших данных
 */
class Cache
{
    private string $cacheDir;
    private int $defaultTtl;

    public function __construct(string $cacheDir, int $defaultTtl = 3600)
    {
        $this->cacheDir = rtrim($cacheDir, '/') . '/cache';
        $this->defaultTtl = $defaultTtl;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Получить значение из кэша
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cacheFile = $this->getCacheFilePath($key);

        if (!file_exists($cacheFile)) {
            return $default;
        }

        $data = @unserialize(file_get_contents($cacheFile));

        if ($data === false) {
            return $default;
        }

        // Проверяем срок годности
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * Сохранить значение в кэш
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if ($ttl === 0) {
            $ttl = $this->defaultTtl;
        }

        $data = [
            'key' => $key,
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'created' => time(),
        ];

        $cacheFile = $this->getCacheFilePath($key);
        $tempFile = $cacheFile . '.tmp';

        // Атомарная запись
        if (file_put_contents($tempFile, serialize($data), LOCK_EX) === false) {
            return false;
        }

        return rename($tempFile, $cacheFile);
    }

    /**
     * Получить или вычислить значение
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Удалить значение из кэша
     */
    public function delete(string $key): bool
    {
        $cacheFile = $this->getCacheFilePath($key);

        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return false;
    }

    /**
     * Очистить весь кэш
     */
    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*.cache');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Очистить устаревшие записи
     */
    public function gc(): int
    {
        $files = glob($this->cacheDir . '/*.cache');
        $cleaned = 0;

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $data = @unserialize(file_get_contents($file));

            if ($data === false || ($data['expires'] > 0 && $data['expires'] < time())) {
                unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Проверить существование ключа
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Инкремент числового значения
     */
    public function increment(string $key, int $step = 1): int
    {
        $value = (int)$this->get($key, 0);
        $value += $step;
        $this->set($key, $value);
        return $value;
    }

    /**
     * Декремент числового значения
     */
    public function decrement(string $key, int $step = 1): int
    {
        return $this->increment($key, -$step);
    }

    /**
     * Получить путь к файлу кэша
     */
    private function getCacheFilePath(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . '/' . $hash . '.cache';
    }

    /**
     * Получить размер кэша в байтах
     */
    public function getSize(): int
    {
        $files = glob($this->cacheDir . '/*.cache');
        $size = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                $size += filesize($file);
            }
        }

        return $size;
    }
}