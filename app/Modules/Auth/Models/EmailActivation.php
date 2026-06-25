<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use App\Core\Model;

/**
 * Модель для работы с токенами активации email.
 * 
 * Использует таблицу email_activations:
 * - UNIQUE KEY (token) — быстрый поиск токена
 * - KEY (user_id) — поиск по пользователю
 * - FOREIGN KEY на users(id) с ON DELETE CASCADE
 * 
 * Срок действия проверяется через created_at + 24 часа (без отдельного поля expires_at).
 */
class EmailActivation extends Model
{
    protected string $table = 'email_activations';

    protected array $fillable = [
        'user_id',
        'token'
    ];

    /**
     * Время жизни токена активации в секундах (24 часа).
     */
    private const TOKEN_LIFETIME = 86400;

    /**
     * Создать новый токен активации для пользователя.
     * Старые токены для этого пользователя автоматически удаляются.
     * 
     * @param int $userId ID пользователя
     * @param string $token Уникальный токен (макс. 64 символа)
     * @return bool Успешность создания
     */
    public function createToken(int $userId, string $token): bool
    {
        // Удаляем старые токены для этого пользователя
        $this->deleteByUserId($userId);

        $stmt = static::db()->prepare(
            "INSERT INTO `email_activations` (`user_id`, `token`, `created_at`) 
             VALUES (:user_id, :token, NOW())"
        );

        return $stmt->execute([
            'user_id' => $userId,
            'token' => $token
        ]);
    }

    /**
     * Найти токен в базе данных.
     * 
     * @param string $token Токен для поиска
     * @return array|null Данные токена или null
     */
    public function findByToken(string $token): ?array
    {
        $stmt = static::db()->prepare(
            "SELECT * FROM `email_activations` WHERE `token` = :token LIMIT 1"
        );
        $stmt->execute(['token' => $token]);

        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Удалить токен после использования.
     * 
     * @param string $token Токен для удаления
     * @return bool Успешность удаления
     */
    public function deleteByToken(string $token): bool
    {
        $stmt = static::db()->prepare(
            "DELETE FROM `email_activations` WHERE `token` = :token"
        );

        return $stmt->execute(['token' => $token]);
    }

    /**
     * Удалить все токены для пользователя.
     * 
     * @param int $userId ID пользователя
     * @return bool Успешность удаления
     */
    public function deleteByUserId(int $userId): bool
    {
        $stmt = static::db()->prepare(
            "DELETE FROM `email_activations` WHERE `user_id` = :user_id"
        );

        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Удалить просроченные токены (старше 24 часов).
     * 
     * @return int Количество удалённых записей
     */
    public function cleanupExpired(): int
    {
        $stmt = static::db()->prepare(
            "DELETE FROM `email_activations` 
             WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }
}
