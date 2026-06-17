<?php

namespace App\Core;

use PDO;
use Exception;

class Audit
{
    private static ?string $auditLogFile = null;

    private static function initFile(): void
    {
        if (self::$auditLogFile === null) {
            self::$auditLogFile = dirname(__DIR__, 2) . '/storage/logs/audit.log';
            $logDir = dirname(self::$auditLogFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
        }
    }

    /**
     * Логирование действия: дублирование в файл + запись в Базу Данных
     */
    public static function log(string $action, string $description, array $payload = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $actorId = $_SESSION['user_id'] ?? null;
        $actorName = $_SESSION['user_name'] ?? 'Guest';
        $actorRole = $_SESSION['user_role'] ?? 'guest';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $payloadJson = !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;

        // 1. НАДЕЖНОСТЬ: Пишем в файл (Защита от стирания логов при взломе БД)
        /* try {
            self::initFile();
            $fileRecord = [
                'timestamp' => date('Y-m-d H:i:s'), 'ip_address' => $ip_address, 'user_id' => $actorId,
                'username' => $actorName, 'role' => $actorRole, 'action' => $action,
                'description' => $description, 'payload' => $payload
            ];
            file_put_contents(self::$auditLogFile, json_encode($fileRecord, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        } catch (Exception $e) {
            // Если запись в файл сорвалась, не останавливаем приложение
        } */

        // 2. УДОБСТВО: Пишем в Базу Данных для вывода в админке
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO `audit_logs` (`user_id`, `username`, `role`, `ip_address`, `action`, `description`, `payload`) 
                VALUES (:user_id, :username, :role, :ip_address, :action, :description, :payload)
            ");
            
            $stmt->execute([
                'user_id'     => $actorId,
                'username'    => $actorName,
                'role'        => $actorRole,
                'ip_address'  => $ip_address,
                'action'      => $action,
                'description' => $description,
                'payload'     => $payloadJson
            ]);
        } catch (Exception $e) {
            // На продакшене ошибку записи лога лучше отправить в основной PHP error_log,
            // чтобы из-за сбоя логгера у пользователя не падал весь сайт
            error_log("Сбой записи аудита в БД: " . $e->getMessage());
        }
    }
}
