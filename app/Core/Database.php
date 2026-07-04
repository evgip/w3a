<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private ?PDO $connection = null;
    private array $config;

    /**
     * Конструктор с инъекцией конфигурации
     * 
     * @param array $config Конфигурация подключения
     */
    public function __construct(array $config = [])
    {
        if (empty($config)) {
            // Fallback: загружаем из конфига (для обратной совместимости)
            $config = require dirname(__DIR__) . '/Config/config.php';
            $config = $config['database'] ?? [];
        }
        $this->config = $config;
    }

    /**
     * Получить PDO-подключение (ленивая инициализация)
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connection = $this->createConnection();
        }
        return $this->connection;
    }

    /**
     * Алиас для getConnection() — более короткое имя
     */
    public function pdo(): PDO
    {
        return $this->getConnection();
    }

    /**
     * Создание нового PDO-подключения
     */
    private function createConnection(): PDO
    {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $this->config['host'] ?? 'localhost',
            $this->config['port'] ?? '3306',
            $this->config['dbname'] ?? '',
            $this->config['charset'] ?? 'utf8mb4'
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO(
                $dsn,
                $this->config['username'] ?? 'root',
                $this->config['password'] ?? '',
                $options
            );

            // Устанавливаем строгий режим
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

            return $pdo;
        } catch (PDOException $e) {
            // Логируем через контейнер, если доступен
            if (class_exists(Logger::class)) {
                try {
                    $logger = new Logger();
                    $logger->error("Сбой подключения к БД: " . $e->getMessage());
                } catch (\Throwable $logError) {
                    // Игнорируем ошибки логирования
                }
            }
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Выполнить запрос и вернуть PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Выполнить запрос и вернуть количество затронутых строк
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Получить последнюю вставленную ID
     */
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Начать транзакцию
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Зафиксировать транзакцию
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Откатить транзакцию
     */
    public function rollBack(): bool
    {
        return $this->getConnection()->rollBack();
    }

    /**
     * Выполнить запрос и вернуть одну запись
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Выполнить запрос и вернуть все записи
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Выполнить запрос и вернуть одно значение
     */
    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($column);
    }
	
    /**
     * Подготовить SQL-запрос без выполнения.
     * Возвращает PDOStatement для использования с bindValue().
     * 
     * @param string $sql SQL-запрос
     * @return \PDOStatement Подготовленный statement
     */
    public function prepare(string $sql): \PDOStatement
    {
        return $this->getConnection()->prepare($sql);
    }
}