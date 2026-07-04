<?php

declare(strict_types=1);

namespace App\Core\Cache;

class FileCache
{
    private string $cacheDir;
    private string $prefix;

    public function __construct(string $cacheDir, string $prefix = 'app_')
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->prefix = $prefix;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Получить значение из кэша
     */
    public function get(string $key): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $data = require $file;

        // Проверяем срок действия
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    /**
     * Сохранить значение в кэш
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);
        $data = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'created' => time(),
        ];

        $content = "<?php\nreturn " . var_export($data, true) . ";\n";

        return file_put_contents($file, $content, LOCK_EX) !== false;
    }

    /**
     * Удалить значение из кэша
     */
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * Очистить весь кэш
     */
    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/' . $this->prefix . '*.php');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Проверить существование ключа
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    private function getFilePath(string $key): string
    {
        return $this->cacheDir . '/' . $this->prefix . md5($key) . '.php';
    }
}