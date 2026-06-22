<?php

declare(strict_types=1);

namespace App\Modules\Stories\Models;

use Exception;
use App\Core\Model;

class Comment extends Model
{
	protected string $table = 'comments';

	// Белый список полей для массового назначения
	protected array $fillable = [
		'deleted_at',
		'story_id',
		'user_id',
		'parent_id',
		'comment',
		'score'     // ← Нужен, так как устанавливается в 1 при создании
	];

	public function saveComment(array $data): int
	{
		try {
			static::db()->beginTransaction();

			// Создаем комментарий и получаем его ID
			$commentId = $this->create([
				'story_id' => $data['story_id'],
				'user_id' => $data['user_id'],
				'parent_id' => $data['parent_id'],
				'comment' => $data['comment'],
				'score' => 1
			]);

			static::db()->commit();

			// Возвращаем ID созданного комментария (0 при ошибке)
			return $commentId;
		} catch (Exception $e) {
			static::db()->rollBack();
			return 0;
		}
	}

	/**
	 * Мягкое удаление комментария.
	 * Счётчик комментариев обновляется автоматически через Event Listener.
	 */
	public function softDeleteComment(int $commentId): bool
	{
		$stmt = static::db()->prepare(
			"UPDATE comments SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL"
		);
		$stmt->execute([$commentId]);
		return $stmt->rowCount() > 0;
	}

	/**
	 * Восстановление удалённого комментария.
	 * Счётчик комментариев обновляется автоматически через Event Listener.
	 */
	public function restoreComment(int $commentId): bool
	{
		$stmt = static::db()->prepare(
			"UPDATE comments SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL"
		);
		$stmt->execute([$commentId]);
		return $stmt->rowCount() > 0;
	}

	/**
	 * Получает полную информацию о комментарии с данными истории.
	 *
	 * @param int $commentId ID комментария
	 * @return array|null Массив с данными или null
	 */
	public function getWithStoryInfo(int $commentId): ?array
	{
		$stmt = static::db()->prepare("
			SELECT 
				c.id,
				c.user_id as author_id,
				c.parent_id,
				c.story_id,
				c.comment,
				s.user_id as story_author_id,
				s.title as story_title,
				s.user_is_following
			FROM comments c
			JOIN stories s ON c.story_id = s.id
			WHERE c.id = :comment_id
		");
		$stmt->execute(['comment_id' => $commentId]);
		$result = $stmt->fetch(\PDO::FETCH_ASSOC);

		return $result ?: null;
	}

	/**
	 * Получает ID автора комментария.
	 *
	 * @param int $commentId ID комментария
	 * @return int|null ID автора или null
	 */
	public function getAuthorId(int $commentId): ?int
	{
		$stmt = static::db()->prepare("
			SELECT user_id FROM comments WHERE id = :id
		");
		$stmt->execute(['id' => $commentId]);
		$result = $stmt->fetch(\PDO::FETCH_ASSOC);

		return $result ? (int)$result['user_id'] : null;
	}
	
	/**
	 * Получить комментарий по ID с данными для вычисления confidence_score
	 */
	public function getCommentById(int $commentId): ?array
	{
		$stmt = static::db()->prepare("
			SELECT id, score, flag_count 
			FROM comments 
			WHERE id = :id AND deleted_at IS NULL
		");
		$stmt->execute(['id' => $commentId]);
		$result = $stmt->fetch(\PDO::FETCH_ASSOC);
		return $result ?: null;
	}

	/**
	 * Обновить confidence_score комментария
	 */
	public function updateConfidenceScore(int $commentId, float $confidenceScore): bool
	{
		$stmt = static::db()->prepare(
			"UPDATE comments SET confidence_score = :score WHERE id = :id"
		);
		return $stmt->execute([
			'score' => $confidenceScore,
			'id' => $commentId,
		]);
	}
}
