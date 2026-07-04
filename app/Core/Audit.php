<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Сервис аудита для записи действий в журнал.
 * 
 * ✅ ПЕРЕПИСАНО: Из статического класса в объект с инъекцией зависимостей.
 * Теперь принимает Database, Session и IpResolver через конструктор.
 */
class Audit
{
    /**
     * @var Database Экземпляр Database для работы с БД
     */
    private Database $db;

    /**
     * @var Session Экземпляр Session для получения данных пользователя
     */
    private Session $session;

    /**
     * @var IpResolver Сервис для определения IP-адреса клиента
     */
    private IpResolver $ipResolver;

    /**
     * @var bool Защита от рекурсии при логировании
     */
    private bool $isLogging = false;

    /**
     * Конструктор с инъекцией зависимостей.
     *
     * @param Database $db Экземпляр Database
     * @param Session $session Экземпляр Session
     * @param IpResolver $ipResolver Сервис для определения IP
     */
    public function __construct(
        Database $db,
        Session $session,
        IpResolver $ipResolver
    ) {
        $this->db = $db;
        $this->session = $session;
        $this->ipResolver = $ipResolver;
    }

    /**
     * Записать действие в журнал аудита.
     *
     * @param string $action Название действия (например, 'user.login')
     * @param string $description Описание действия
     * @param string $category Категория действия (по умолчанию 'general')
     * @param array $payload Дополнительные данные (по умолчанию [])
     *
     * @return void
     */
    public function log(
        string $action,
        string $description,
        string $category = 'general',
        array $payload = []
    ): void {
        // Защита от рекурсии
        if ($this->isLogging) {
            return;
        }
        $this->isLogging = true;
        
        try {
            // Получаем данные пользователя из сессии
            $userId    = (int)$this->session->get('user_id', 0);
            $username  = $this->session->get('user_name', 'Guest');
            $role      = $this->session->get('user_role', 'guest');
            $ipAddress = $this->ipResolver->getClientIp();

            // Используем новый метод query() из Database
            $this->db->query(
                "INSERT INTO audit_logs 
                    (user_id, username, role, ip_address, action, description, category, payload, created_at) 
                 VALUES (:user_id, :username, :role, :ip_address, :action, :description, :category, :payload, NOW())",
                [
                    'user_id'     => $userId,
                    'username'    => $username,
                    'role'        => $role,
                    'ip_address'  => $ipAddress,
                    'action'      => $action,
                    'description' => $description,
                    'category'    => $category,
                    'payload'     => !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
                ]
            );
        } finally {
            $this->isLogging = false;
        }
    }

    /**
     * Получить записи по категории.
     *
     * @param string $category Категория записей
     * @param int $limit Максимальное количество записей (по умолчанию 50)
     * @param int $offset Смещение (по умолчанию 0)
     *
     * @return array Массив записей аудита
     */
    public function getByCategory(string $category, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM audit_logs 
             WHERE category = :category
             ORDER BY id DESC 
             LIMIT :limit OFFSET :offset",
            [
                'category' => $category,
                'limit'    => $limit,
                'offset'   => $offset,
            ]
        );
    }

    /**
     * Получить все записи с опциональным фильтром.
     *
     * @param int $limit Максимальное количество записей (по умолчанию 100)
     * @param int $offset Смещение (по умолчанию 0)
     * @param string|null $category Опциональная категория для фильтрации
     *
     * @return array Массив записей аудита
     */
    public function getAll(int $limit = 100, int $offset = 0, ?string $category = null): array
    {
        if ($category && $category !== '') {
            return $this->db->fetchAll(
                "SELECT * FROM audit_logs 
                 WHERE category = :category
                 ORDER BY id DESC 
                 LIMIT :limit OFFSET :offset",
                [
                    'category' => $category,
                    'limit'    => $limit,
                    'offset'   => $offset,
                ]
            );
        }

        return $this->db->fetchAll(
            "SELECT * FROM audit_logs 
             ORDER BY id DESC 
             LIMIT :limit OFFSET :offset",
            [
                'limit'  => $limit,
                'offset' => $offset,
            ]
        );
    }

    /**
     * Подсчёт записей по категории.
     *
     * @param string $category Категория для подсчёта
     *
     * @return int Количество записей
     */
    public function countByCategory(string $category): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs WHERE category = :category",
            ['category' => $category]
        );
    }

    /**
     * Подсчёт всех записей.
     *
     * @return int Общее количество записей
     */
    public function countAll(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM audit_logs"
        );
    }

    /**
     * Удалить старые записи аудита.
     *
     * @param int $days Количество дней для хранения (по умолчанию 90)
     *
     * @return int Количество удалённых записей
     */
    public function cleanup(int $days = 90): int
    {
        return $this->db->execute(
            "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)",
            ['days' => $days]
        );
    }
}