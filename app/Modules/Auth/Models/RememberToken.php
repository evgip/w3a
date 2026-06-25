<?php

namespace App\Modules\Auth\Models;

use App\Core\Model;
use App\Core\Database;

class RememberToken extends Model
{
    protected string $table = 'remember_tokens';

    /**
     * Создать новый remember token для пользователя
     *
     * @param int $userId ID пользователя
     * @param int $days Количество дней действия токена (по умолчанию 30)
     * @param string|null $userAgent User-Agent браузера
     * @param string|null $ipAddress IP-адрес пользователя
     * @return array Массив с ['selector', 'validator', 'token'] для cookie
     */
    public function createToken(
        int $userId,
        int $days = 30,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): array {
        // Генерируем случайные значения
        $selector = bin2hex(random_bytes(6)); // 12 символов
        $validator = bin2hex(random_bytes(32)); // 64 символа

        // Хэшируем валидатор перед сохранением в БД
        $hashedValidator = hash('sha256', $validator);

        // Токен для cookie = selector + validator
        $token = $selector . ':' . $validator;

        // Время истечения
        $expiresAt = date('Y-m-d H:i:s', time() + ($days * 86400));

        // Удаляем старые токены этого пользователя
        $this->deleteByUserId($userId);

        // Сохраняем в БД (БЕЗ поля token!)
        $db = Database::getConnection();
        $stmt = $db->prepare("
			INSERT INTO `{$this->table}` 
			(`user_id`, `selector`, `hashed_validator`, `user_agent`, `ip_address`, `expires_at`)
			VALUES (:user_id, :selector, :hashed_validator, :user_agent, :ip_address, :expires_at)
		");

        $stmt->execute([
            'user_id' => $userId,
            'selector' => $selector,
            'hashed_validator' => $hashedValidator,
            'user_agent' => $userAgent,
            'ip_address' => $ipAddress,
            'expires_at' => $expiresAt
        ]);

        return [
            'selector' => $selector,
            'validator' => $validator,
            'token' => $token
        ];
    }

    /**
     * Найти токен по селектору
     *
     * @param string $selector Публичный идентификатор
     * @return array|null Запись из БД или null
     */
    public function findBySelector(string $selector): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM `{$this->table}`
            WHERE `selector` = :selector 
            AND `expires_at` > NOW()
            LIMIT 1
        ");
        $stmt->execute(['selector' => $selector]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Проверить валидность токена
     *
     * @param string $selector Селектор из cookie
     * @param string $validator Валидатор из cookie
     * @return array|null Данные пользователя или null
     */
    public function validateToken(string $selector, string $validator): ?array
    {
        $record = $this->findBySelector($selector);

        if (!$record) {
            return null;
        }

        // Проверяем хэш валидатора
        if (!hash_equals($record['hashed_validator'], hash('sha256', $validator))) {
            return null;
        }

        // Проверяем, не истек ли токен
        if (strtotime($record['expires_at']) < time()) {
            $this->deleteBySelector($selector);
            return null;
        }

        return $record;
    }

    /**
     * Удалить токен по селектору
     *
     * @param string $selector Селектор
     * @return bool
     */
    public function deleteBySelector(string $selector): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM `{$this->table}` WHERE `selector` = :selector");
        return $stmt->execute(['selector' => $selector]);
    }

    /**
     * Удалить все токены пользователя
     *
     * @param int $userId ID пользователя
     * @return bool
     */
    public function deleteByUserId(int $userId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM `{$this->table}` WHERE `user_id` = :user_id");
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Очистить истекшие токены (для cron)
     *
     * @return int Количество удаленных записей
     */
    public function cleanupExpired(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM `{$this->table}` WHERE `expires_at` < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
