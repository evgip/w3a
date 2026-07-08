<?php

namespace App\Core;

use App\Modules\Errors\Controllers\ErrorsController;

class Firewall
{
    private Database $db;
    private Container $container;
    private IpResolver $ipResolver;

    /**
     * Конструктор с инъекцией зависимостей
     */
    public function __construct(Database $db, Container $container, IpResolver $ipResolver)
    {
        $this->db = $db;
        $this->container = $container;
        $this->ipResolver = $ipResolver;
    }

    /**
     * Проверка IP-адреса клиента против чёрного списка
     */
    public function check(): void
    {
        // ✅ Используем объект IpResolver вместо статического вызова
        $ip = $this->ipResolver->getClientIp();

        // Используем новый метод query() с параметрами
        $stmt = $this->db->query(
            "SELECT `reason` FROM `banned_ips` WHERE `ip_address` = :ip LIMIT 1",
            ['ip' => $ip]
        );

        $reason = $stmt->fetchColumn();

        if ($reason !== false) {
            // Создаём контроллер через контейнер
            $controller = $this->container->make(ErrorsController::class);
            $controller->forbidden("Ваш IP-адрес заблокирован. Причина: " . $reason);
            exit;
        }
    }
}
