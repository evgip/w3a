<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    // Закрываем конструктор для реализации паттерна Singleton
    private function __construct() {}
    private function __clone() {}

    /**
     * Возвращает единственный экземпляр подключения PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            // Загружаем конфиг относительно текущего файла (Core -> app -> Config)
            $config = require dirname(__DIR__) . '/Config/config.php';
            $dbConfig = $config['database'];

            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
				
				$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
				
				// ← Устанавливаем sql_mode через локальную переменную
				$pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
				
				// ← Присваиваем только после успешного выполнения
				self::$instance = $pdo;
				
				//self::$instance->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

               // self::$instance = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
            } catch (PDOException $e) {
				\App\Core\Logger::error("Сбой подключения к БД: " . $e->getMessage());
				http_response_code(500);
				die("Database error.");
			}
        }

        return self::$instance;
    }
}
