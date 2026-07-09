<?php

namespace App\Core;

class Benchmark
{
    private static float $startTime;
    private static int $startMemory;

    public static function start(): void
    {
        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage();
    }

    public static function getExecutionTime(): float
    {
        $endTime = microtime(true);
        return round(($endTime - self::$startTime) * 1000, 2);
    }

    public static function getMemoryUsage(): float
    {
        $memory = memory_get_peak_usage(true);
        return round($memory / 1024 / 1024, 2);
    }

    public static function getQueryCount(): int
    {
        return Database::getQueryCount();
    }

    public static function renderStats(): string
    {
        $time = self::getExecutionTime();
        $memory = self::getMemoryUsage();
        $queries = self::getQueryCount();

        return "
        <div class='hint right'>
            ⏱️ {$time} ms | 
            💾 {$memory} MB | 
            🗄️ {$queries} SQL
        </div>
        ";
    }
}
