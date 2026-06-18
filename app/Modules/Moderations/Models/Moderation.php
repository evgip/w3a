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
	 * formatActionReason('Изменён', 'comment', $commentId, (int)$comment['story_id'])
     */ 
    public static  function logCommentModeratorAction(
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
	
	/**
	 * Забанить пользователя.
	 *
	 * @param int $userId ID пользователя
	 * @param int $moderatorId ID модератора
	 * @param string $reason Причина бана
	 * @return bool Успешно ли выполнен запрос
	 */
	public function banUser(int $userId, int $moderatorId, string $reason = ''): bool
	{
		$stmt = static::db()->prepare("
			UPDATE users 
			SET is_banned = 1, 
				banned_at = NOW(), 
				banned_by = :mod_id,
				ban_reason = :reason
			WHERE id = :id
		");
		
		return $stmt->execute([
			'mod_id' => $moderatorId,
			'reason' => $reason,
			'id'     => $userId,
		]);
	}

	/**
	 * Разбанить пользователя.
	 *
	 * @param int $userId ID пользователя
	 * @return bool Успешно ли выполнен запрос
	 */
	public function unbanUser(int $userId): bool
	{
		$stmt = static::db()->prepare("
			UPDATE users 
			SET is_banned = 0, 
				banned_at = NULL, 
				banned_by = NULL,
				ban_reason = NULL
			WHERE id = :id
		");
		
		return $stmt->execute(['id' => $userId]);
	}
}