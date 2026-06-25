<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use App\Core\Model;

/**
 * Модель для работы с токенами восстановления пароля.
 * 
 * Использует таблицу password_resets с оптимизированной структурой:
 * - UNIQUE KEY (token) — быстрый поиск токена, гарантия уникальности
 * - KEY (email, token) — составной индекс для удаления по email
 */
class PasswordResetToken extends Model
{
    protected string $table = 'password_resets';

    protected array $fillable = [
        'email',
        'token'
    ];

    /**
     * Создать новый токен для email.
     * Старые токены для этого email автоматически удаляются.
     * 
     * Метод переименован в createToken(), чтобы не конфликтовать
     * с базовым методом Model::create(array $data): int
     * 
     * @param string $email Email пользователя
     * @param string $token Уникальный токен (макс. 64 символа)
     * @return bool Успешность создания
     */
    public function createToken(string $email, string $token): bool
    {
        // Удаляем старые токены для этого email
        $this->deleteByEmail($email);

        $stmt = static::db()->prepare(
            "INSERT INTO `password_resets` (`email`, `token`, `created_at`) 
             VALUES (:email, :token, NOW())"
        );

        return $stmt->execute([
            'email' => $email,
            'token' => $token
        ]);
    }

    /**
     * Найти токен в базе данных.
     */
    public function findByToken(string $token): ?array
    {
        $stmt = static::db()->prepare(
            "SELECT * FROM `password_resets` WHERE `token` = :token LIMIT 1"
        );
        $stmt->execute(['token' => $token]);

        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Удалить токен после использования.
     */
    public function deleteByToken(string $token): bool
    {
        $stmt = static::db()->prepare(
            "DELETE FROM `password_resets` WHERE `token` = :token"
        );

        return $stmt->execute(['token' => $token]);
    }

    /**
     * Удалить все токены для email.
     */
    public function deleteByEmail(string $email): bool
    {
        $stmt = static::db()->prepare(
            "DELETE FROM `password_resets` WHERE `email` = :email"
        );

        return $stmt->execute(['email' => $email]);
    }

    /**
     * Удалить просроченные токены (старше 1 часа).
     */
    public function cleanupExpired(): int
    {
        $stmt = static::db()->prepare(
            "DELETE FROM `password_resets` 
             WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }
}
