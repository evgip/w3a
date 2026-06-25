<?php

declare(strict_types=1);

namespace App\Core;

class Audit
{
    /**
     * Записать действие в журнал аудита.
     */
    public static function log(
        string $action,
        string $description,
        string $category = 'general',
        array $payload = []
    ): void {
        // Защита от рекурсии
        static $isLogging = false;
        if ($isLogging) {
            return;
        }
        $isLogging = true;
        
        try {

            $db = Database::getConnection();

            // Берём данные из сессии напрямую
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            
            $userId    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            $username  = $_SESSION['user_name'] ?? 'Guest';
            $role      = $_SESSION['user_role'] ?? 'guest';
            $ipAddress = IpResolver::getClientIp();

            $stmt = $db->prepare(
                "INSERT INTO audit_logs 
                    (user_id, username, role, ip_address, action, description, category, payload, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            
            $stmt->execute([
                $userId,
                $username,
                $role,
                $ipAddress,
                $action,
                $description,
                $category,
                !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } finally {
            $isLogging = false;
        }
    }

    /**
     * Получить записи по категории.
     */
    public static function getByCategory(string $category, int $limit = 50, int $offset = 0): array
    {
        $db = Database::getConnection();  // ✅ getConnection()
        
        $stmt = $db->prepare(
            "SELECT * FROM audit_logs 
             WHERE category = ?
             ORDER BY id DESC 
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$category, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Получить все записи с опциональным фильтром.
     */
    public static function getAll(int $limit = 100, int $offset = 0, ?string $category = null): array
    {
        $db = Database::getConnection();  // ✅ getConnection()

        if ($category && $category !== '') {
            $stmt = $db->prepare(
                "SELECT * FROM audit_logs 
                 WHERE category = ?
                 ORDER BY id DESC 
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute([$category, $limit, $offset]);
            return $stmt->fetchAll();
        }

        $stmt = $db->prepare(
            "SELECT * FROM audit_logs 
             ORDER BY id DESC 
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Подсчёт записей по категории.
     */
    public static function countByCategory(string $category): int
    {
        $db = Database::getConnection();  // ✅ getConnection()
        $stmt = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE category = ?");
        $stmt->execute([$category]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Подсчёт всех записей.
     */
    public static function countAll(): int
    {
        $db = Database::getConnection();  // ✅ getConnection()
        $stmt = $db->query("SELECT COUNT(*) FROM audit_logs");
        return (int)$stmt->fetchColumn();
    }
}