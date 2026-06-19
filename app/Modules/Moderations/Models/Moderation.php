<?php

namespace App\Modules\Moderations\Models;

use App\Core\Model;

class Moderation extends Model
{
    protected string $table = 'moderations';

    protected array $fillable = [
        'moderator_id',
        'action',
        'target_type',
        'target_id',
        'reason',
    ];

    // У moderations нет deleted_at — это неизменяемый лог
    protected bool $includeTrashed = true;

    /**
     * Переопределяем soft-delete constraint, т.к. в таблице нет deleted_at
     */
    protected function applySoftDeleteConstraint(string $sql): string
    {
        return $sql;
    }

    /**
     * Получить публичный лог модерации с пагинацией
     */
    public function getPublicLog(int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;

        // Получаем записи
        $sql = "SELECT m.*, u.username AS moderator_name
                FROM `moderations` m
                LEFT JOIN `users` u ON u.id = m.moderator_id
                ORDER BY m.`created_at` DESC
                LIMIT :limit OFFSET :offset";
        $stmt = static::db()->prepare($sql);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        // Получаем общее количество
        $countSql = "SELECT COUNT(*) FROM `moderations`";
        $total = (int) static::db()->query($countSql)->fetchColumn();

        return [
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil($total / $perPage),
            'current_page' => $page,
        ];
    }

    /**
     * Записать действие в лог + обновить статистику активности
     */
    public function logAction(int $moderatorId, string $action, string $targetType, int $targetId, ?string $reason = null): int
    {
        $id = $this->create([
            'moderator_id' => $moderatorId,
            'action'       => $action,
            'target_type'  => $targetType,
            'target_id'    => $targetId,
            'reason'       => $reason,
        ]);

        // Обновляем счётчик активности за сегодня
        $activity = new ModActivity();
        $activity->incrementToday($moderatorId, $action);

        return $id;
    }
	
	/**
	 * Универсальное форматирование ссылки для лога модерации
	 * 
	 * @param string $verb       Глагол действия
	 * @param string $targetType Тип объекта: 'comment', 'story', 'user'
	 * @param int    $targetId   ID объекта
	 * @param int    $storyId    ID связанной истории (для comment)
	 * @return string HTML-строка для поля reason
	 */
	public static function formatActionReason(
		string $verb, 
		string $targetType, 
		int $targetId, 
		?int $storyId = null
	): string {
		return match ($targetType) {
			'comment' => $verb 
				. ' <a href="/story/' . $storyId . '#comment-block-' . $targetId . '">комментарий #' . $targetId . '</a>'
				. ' в <a href="/story/' . $storyId . '">истории #' . $storyId . '</a>',
				
			'story'   => $verb 
				. ' <a href="/story/' . $targetId . '">историю #' . $targetId . '</a>',
				
			'user'    => $verb 
				. ' <a href="/user/' . $targetId . '">пользователя #' . $targetId . '</a>',
				
			default   => $verb . ' объект #' . $targetId,
		};
	}
	
    /**
     * Логирование действие модератора
     */ 
    public static function logCommentModeratorAction(
		string $verb, 
		int $isAuthor,
		array $comment
	): bool
    {
		if (!$isAuthor && \App\Core\Auth::isModerator()) {
			try {
				$history = self::formatActionReason($verb, 'comment', (int)$comment['id'], (int)$comment['story_id']);
				
				$modLog = new \App\Modules\Moderations\Models\Moderation();
				$modLog->logAction(
					(int) $_SESSION['user_id'],
					'edit_comment',
					'comment',
					(int)$comment['id'],
					$history,
				);
			} catch (\Exception $e) {
				error_log("Moderation log failed: " . $e->getMessage());
			}
		}

		return true;
    }
	
	// ==========================================
	// МЕТОДЫ РАБОТЫ С БАНАМИ (user_bans)
	// ==========================================

	/**
	 * Забанить пользователя.
	 * Создаёт новую запись в таблице user_bans.
	 *
	 * @param int         $userId      ID пользователя
	 * @param int         $moderatorId ID модератора
	 * @param string      $reason      Причина бана
	 * @param string|null $expiresAt   Дата окончания бана (NULL = перманентный бан)
	 *                                 Формат: 'Y-m-d H:i:s' или null
	 * @return bool Успешно ли выполнен запрос
	 */
	public function banUser(int $userId, int $moderatorId, string $reason = '', ?string $expiresAt = null): bool
	{
		$stmt = static::db()->prepare("
			INSERT INTO `user_bans` (`user_id`, `banned_by`, `reason`, `created_at`, `expires_at`)
			VALUES (:user_id, :mod_id, :reason, NOW(), :expires_at)
		");
		
		return $stmt->execute([
			'user_id'    => $userId,
			'mod_id'     => $moderatorId,
			'reason'     => $reason,
			'expires_at' => $expiresAt,
		]);
	}

	/**
	 * Досрочно разбанить пользователя.
	 * Помечает активный бан как снятый (устанавливает unbanned_at и unbanned_by).
	 *
	 * @param int      $userId      ID пользователя
	 * @param int|null $unbannedBy  ID модератора, снявшего бан (null если авто-снятие)
	 * @return bool Успешно ли выполнен запрос
	 */
	public function unbanUser(int $userId, ?int $unbannedBy = null): bool
	{
		$stmt = static::db()->prepare("
			UPDATE `user_bans`
			SET `unbanned_at` = NOW(),
			    `unbanned_by` = :unbanned_by
			WHERE `user_id` = :user_id
			  AND `unbanned_at` IS NULL
			  AND (`expires_at` IS NULL OR `expires_at` > NOW())
		");
		
		return $stmt->execute([
			'user_id'      => $userId,
			'unbanned_by'  => $unbannedBy,
		]);
	}

	/**
	 * Проверить, забанен ли пользователь прямо сейчас.
	 * Учитывает как перманентные, так и временные баны.
	 *
	 * @param int $userId ID пользователя
	 * @return bool true если пользователь забанен
	 */
	public function isUserBanned(int $userId): bool
	{
		$stmt = static::db()->prepare("
			SELECT COUNT(*) FROM `user_bans`
			WHERE `user_id` = :user_id
			  AND `unbanned_at` IS NULL
			  AND (`expires_at` IS NULL OR `expires_at` > NOW())
		");
		$stmt->execute(['user_id' => $userId]);
		
		return (int)$stmt->fetchColumn() > 0;
	}

	/**
	 * Получить информацию об активном бане пользователя.
	 *
	 * @param int $userId ID пользователя
	 * @return array|null Данные бана или null если пользователь не забанен
	 */
	public function getActiveBan(int $userId): ?array
	{
		$stmt = static::db()->prepare("
			SELECT b.*, 
			       u.username AS banned_by_name
			FROM `user_bans` b
			LEFT JOIN `users` u ON u.id = b.banned_by
			WHERE b.`user_id` = :user_id
			  AND b.`unbanned_at` IS NULL
			  AND (b.`expires_at` IS NULL OR b.`expires_at` > NOW())
			ORDER BY b.`created_at` DESC
			LIMIT 1
		");
		$stmt->execute(['user_id' => $userId]);
		$result = $stmt->fetch(\PDO::FETCH_ASSOC);
		
		return $result ?: null;
	}

	/**
	 * Получить полную историю банов пользователя (включая снятые и истёкшие).
	 *
	 * @param int $userId ID пользователя
	 * @return array Массив записей банов
	 */
	public function getBanHistory(int $userId): array
	{
		$stmt = static::db()->prepare("
			SELECT b.*, 
			       u1.username AS banned_by_name,
			       u2.username AS unbanned_by_name
			FROM `user_bans` b
			LEFT JOIN `users` u1 ON u1.id = b.banned_by
			LEFT JOIN `users` u2 ON u2.id = b.unbanned_by
			WHERE b.`user_id` = :user_id
			ORDER BY b.`created_at` DESC
		");
		$stmt->execute(['user_id' => $userId]);
		
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Получить список всех активных банов (для админ-панели).
	 *
	 * @param int $page    Номер страницы
	 * @param int $perPage Количество записей на странице
	 * @return array Массив банов с пагинацией
	 */
	public function getActiveBans(int $page = 1, int $perPage = 30): array
	{
		$offset = ($page - 1) * $perPage;

		$stmt = static::db()->prepare("
			SELECT b.*, 
			       u1.username AS user_name,
			       u2.username AS banned_by_name
			FROM `user_bans` b
			JOIN `users` u1 ON u1.id = b.user_id
			LEFT JOIN `users` u2 ON u2.id = b.banned_by
			WHERE b.`unbanned_at` IS NULL
			  AND (b.`expires_at` IS NULL OR b.`expires_at` > NOW())
			ORDER BY b.`created_at` DESC
			LIMIT :limit OFFSET :offset
		");
		$stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
		$stmt->execute();
		$items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$countSql = "SELECT COUNT(*) FROM `user_bans`
		             WHERE `unbanned_at` IS NULL
		               AND (`expires_at` IS NULL OR `expires_at` > NOW())";
		$total = (int) static::db()->query($countSql)->fetchColumn();

		return [
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil($total / $perPage),
			'current_page' => $page,
		];
	}

	/**
	 * Автоматически снять истёкшие баны (для cron-задачи).
	 * Находит баны, у которых expires_at < NOW() и unbanned_at IS NULL,
	 * и помечает их как истёкшие.
	 *
	 * @return int Количество снятых банов
	 */
	public function expireOldBans(): int
	{
		$stmt = static::db()->prepare("
			UPDATE `user_bans`
			SET `unbanned_at` = NOW(),
			    `unbanned_by` = NULL
			WHERE `unbanned_at` IS NULL
			  AND `expires_at` IS NOT NULL
			  AND `expires_at` <= NOW()
		");
		$stmt->execute();
		
		return $stmt->rowCount();
	}
}