<?php

namespace App\Modules\Users\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';

    // Разрешаем изменять только эти поля при регистрации/редактировании
    // Поля 'id',  'karma', 'created_at' защищены от изменения через массив $data
	protected array $fillable = [
		'username',
		'email',
		'password',
		'avatar',
		'status',
		'bio',
		'role' // Будем осторожны с role, лучше менять его через отдельный метод, но для полноты оставим
	];


    /**
     * Получить полную статистику активности пользователя по его ID
     * 
     * @param int $userId
     * @return array Массив с ключами 'stories_count' и 'comments_count'
     */
    public function getProfileStats(int $userId): array
    {
        // 1. Считаем активные истории автора
        $storiesStmt = static::db()->prepare("SELECT COUNT(*) FROM `stories` WHERE `user_id` = :uid AND `deleted_at` IS NULL");
        $storiesStmt->execute(['uid' => $userId]);
        $storiesCount = (int)$storiesStmt->fetchColumn();

        // 2. Считаем активные комментарии автора
        $commentsStmt = static::db()->prepare("SELECT COUNT(*) FROM `comments` WHERE `user_id` = :uid AND `deleted_at` IS NULL");
        $commentsStmt->execute(['uid' => $userId]);
        $commentsCount = (int)$commentsStmt->fetchColumn();

        return [
            'stories_count'  => $storiesCount,
            'comments_count' => $commentsCount
        ];
    }
	
     /**
     * Вычислить суммарную карму пользователя (рейтинг всех его постов + комментов)
     */
    public function getUserKarma(int $userId): int
    {
        // 1. Считаем сумму score всех активных историй автора
        $storyStmt = static::db()->prepare("SELECT SUM(`score`) FROM `stories` WHERE `user_id` = :uid AND `deleted_at` IS NULL");
        $storyStmt->execute(['uid' => $userId]);
        $storyKarma = (int)$storyStmt->fetchColumn();

        // 2. Считаем сумму score всех активных комментариев автора
        $commentStmt = static::db()->prepare("SELECT SUM(`score`) FROM `comments` WHERE `user_id` = :uid AND `deleted_at` IS NULL");
        $commentStmt->execute(['uid' => $userId]);
        $commentKarma = (int)$commentStmt->fetchColumn();

        // Итоговая карма — это сумма двух показателей
        return $storyKarma + $commentKarma;
    }
	
	/**
	 * Находит пользователя по имени (username).
	 *
	 * @param string $username Имя пользователя
	 * @return array|null Данные пользователя или null
	 */
	public function findByName(string $username): ?array
	{
		$stmt = $this->db()->prepare("
			SELECT id, name, username FROM users 
			WHERE name = :username AND deleted_at IS NULL
		");
		$stmt->execute(['username' => $username]);
		$result = $stmt->fetch(\PDO::FETCH_ASSOC);
		
		return $result ?: null;
	}

	/**
	 * Проверяет, забанен ли пользователь.
	 *
	 * @param int $userId ID пользователя
	 * @return bool true если забанен
	 */
	public function isBanned(int $userId): bool
	{
		$stmt = static::db()->prepare("
			SELECT is_banned FROM `{$this->table}` 
			WHERE id = :id LIMIT 1
		");
		$stmt->execute(['id' => $userId]);
		$result = $stmt->fetch(\PDO::FETCH_ASSOC);
		
		return $result && (int)$result['is_banned'] === 1;
	}

	/**
	 * Получает информацию о бане пользователя.
	 *
	 * @param int $userId ID пользователя
	 * @return array|null Массив с данными бана или null
	 */
	public function getBanInfo(int $userId): ?array
	{
		$stmt = static::db()->prepare("
			SELECT is_banned, banned_at, banned_by, ban_reason 
			FROM `{$this->table}` 
			WHERE id = :id LIMIT 1
		");
		$stmt->execute(['id' => $userId]);
		$result = $stmt->fetch(\PDO::FETCH_ASSOC);
		
		if (!$result || (int)$result['is_banned'] !== 1) {
			return null;
		}
		
		return $result;
	}
}
