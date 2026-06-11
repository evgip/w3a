<?php

namespace App\Core;

class Benchmark
{
    private static float $startTime;
    private static int $startMemory;

    /**
     * Фиксирует точку старта (вызывается в самом начале index.php)
     */
    public static function start(): void
    {
        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage();
    }

    /**
     * Возвращает время генерации страницы в миллисекундах (ms)
     */
    public static function getExecutionTime(): float
    {
        $endTime = microtime(true);
        // Переводим в миллисекунды и округляем до 2 знаков после запятой
        return round(($endTime - self::$startTime) * 1000, 2);
    }

    /**
     * Возвращает пиковое потребление памяти в мегабайтах (MB)
     */
    public static function getMemoryUsage(): float
    {
        $memory = memory_get_peak_usage(true);
        return round($memory / 1024 / 1024, 2);
    }

    /**
     * Возвращает количество подключенных PHP-файлов в рамках этого запроса
     */
    public static function getIncludedFilesCount(): int
    {
        return count(get_included_files());
    }

    /**
     * Возвращает готовый HTML-блок со статистикой для футера сайта
     */
    public static function renderStats(): string
    {
        $time = self::getExecutionTime();
        $memory = self::getMemoryUsage();
        $files = self::getIncludedFilesCount();

        return "
        <div class='benchmark-panel'>
            ⏱️ Generation time: <span class='benchmark-value'>{$time} ms</span> | 
            💾 Peak memory: <span class='benchmark-value'>{$memory} MB</span> | 
            📂 Included files: <span class='benchmark-value'>{$files}</span>
        </div>
        ";
    }
}
