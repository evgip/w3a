<?php

namespace App\Modules\Stories\Models;

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
			
			$stmt = static::db()->prepare("UPDATE `stories` SET `comments_count` = `comments_count` + 1 WHERE `id` = :story_id");
			$stmt->execute(['story_id' => $data['story_id']]);
			
			static::db()->commit();
			
			// Возвращаем ID созданного комментария (0 при ошибке)
			return $commentId;
			
		} catch (Exception $e) {
			static::db()->rollBack();
			return 0;
		}
	}

    /**
     * Мягкое удаление комментария с декрементом счетчика истории
     */
    public function softDeleteComment(int $id, int $storyId): bool
    {
        try {
            static::db()->beginTransaction();
            
            // Вызываем стандартное мягкое удаление из Core/Model
            $this->delete($id);

            // Уменьшаем счетчик комментариев у истории
            $stmt = static::db()->prepare("UPDATE `stories` SET `comments_count` = `comments_count` - 1 WHERE `id` = :story_id AND `comments_count` > 0");
            $stmt->execute(['story_id' => $storyId]);

            static::db()->commit();
            return true;
        } catch (\Exception $e) {
            static::db()->rollBack();
            return false;
        }
    }

    /**
     * Восстановление комментария с инкрементом счетчика истории
     */
    public function restoreComment(int $id, int $storyId): bool
    {
        try {
            static::db()->beginTransaction();
            
            // Вызываем восстановление из Core/Model
            $this->restore($id);

            // Увеличиваем счетчик комментариев обратно
            $stmt = static::db()->prepare("UPDATE `stories` SET `comments_count` = `comments_count` + 1 WHERE `id` = :story_id");
            $stmt->execute(['story_id' => $storyId]);

            static::db()->commit();
            return true;
        } catch (\Exception $e) {
            static::db()->rollBack();
            return false;
        }
    }
}

