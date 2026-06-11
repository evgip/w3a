<?php

namespace App\Core;

class Logger
{
    private static ?string $logFile = null;

    /**
     * Инициализация пути к файлу логов во внешнем хранилище
     */
    private static function init(): void
    {
        if (self::$logFile === null) {
            // ИСПРАВЛЕНО: Поднимаемся на 2 уровня вверх от Core, чтобы выйти в корень проекта
            self::$logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
            
            // Если папки storage/logs еще нет, создаем её в корне
            $logDir = dirname(self::$logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
        }
    }

    /**
     * Запись произвольного лога с определенным уровнем (ERROR, INFO, DEBUG)
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        self::init();

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        
        $contextStr = !empty($context) ? ' | Контекст: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[{$timestamp}] [{$ip}] [{$level}]: {$message}{$contextStr}" . PHP_EOL;

        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }
}
