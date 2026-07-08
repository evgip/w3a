<?php

namespace App\Core;

class Logger
{
    private string $logFile;
    private string $dateFormat;

    /**
     * Конструктор с инъекцией пути к файлу логов
     * 
     * @param string|null $logFile Путь к файлу логов (по умолчанию: storage/logs/app.log)
     * @param string $dateFormat Формат даты
     */
    public function __construct(?string $logFile = null, string $dateFormat = 'Y-m-d H:i:s')
    {
        if ($logFile === null) {
            $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
        }

        $this->logFile = $logFile;
        $this->dateFormat = $dateFormat;

        // Создаём директорию, если её нет
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Запись лога с указанным уровнем
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date($this->dateFormat);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

        $contextStr = !empty($context)
            ? ' | Контекст: ' . json_encode($context, JSON_UNESCAPED_UNICODE)
            : '';

        $logMessage = "[{$timestamp}] [{$ip}] [{$level}]: {$message}{$contextStr}" . PHP_EOL;

        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Запись лога уровня ERROR
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Запись лога уровня WARNING
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Запись лога уровня INFO
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Запись лога уровня DEBUG
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Получить путь к файлу логов
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Очистить файл логов
     */
    public function clear(): bool
    {
        if (file_exists($this->logFile)) {
            return unlink($this->logFile);
        }
        return false;
    }
}
