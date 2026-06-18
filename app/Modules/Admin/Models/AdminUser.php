<?php

namespace App\Modules\Admin\Models;

use App\Core\Model;

class AdminUser extends Model
{
    // Указываем базовому ядру, что эта админ-модель оперирует таблицей пользователей
    protected string $table = 'users';

	protected array $fillable = [
		'is_active',
		'type'
	];

    /**
     * Выборка реестра учетных записей для панели модератора
     * 
     * @param int $limit
     * @return array
     */
    public function getAdminUsersList(int $limit = 100): array
    {
        $stmt = static::db()->prepare("SELECT id, name, email, role, is_active, created_at FROM `users` ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Инвертирование флага активации аккаунта на уровне СУБД
     * 
     * @param int $userId
     * @return int Установленный статус (1 или 0) либо -1 в случае ошибки
     */
    public function toggleActivationStatus(int $userId): int
    {
        $user = $this->find($userId);
        if (!$user) {
            return -1; 
        }

        $newStatus = ((int)$user['is_active'] === 1) ? 0 : 1;
        
        // Используем встроенный метод обновления записи из абстракции ядра Model
        $this->update($userId, [
            'is_active' => $newStatus
        ]);

        return $newStatus;
    }
}
