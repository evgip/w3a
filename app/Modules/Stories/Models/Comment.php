<?php

namespace App\Modules\Stories\Models;

use App\Core\Model;
use App\Core\Database;

class Comment extends Model
{
    protected string $table = 'comments';

    public function saveComment(array $data): bool
    {
        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            $this->create([
                'story_id'  => $data['story_id'],
                'user_id'   => $data['user_id'],
                'parent_id' => $data['parent_id'],
                'comment'   => $data['comment'],
                'score'     => 1
            ]);
            $stmt = $db->prepare("UPDATE `stories` SET `comments_count` = `comments_count` + 1 WHERE `id` = :story_id");
            $stmt->execute(['story_id' => $data['story_id']]);
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Мягкое удаление комментария с декрементом счетчика истории
     */
    public function softDeleteComment(int $id, int $storyId): bool
    {
        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            
            // Вызываем стандартное мягкое удаление из Core/Model
            $this->delete($id);

            // Уменьшаем счетчик комментариев у истории
            $stmt = $db->prepare("UPDATE `stories` SET `comments_count` = `comments_count` - 1 WHERE `id` = :story_id AND `comments_count` > 0");
            $stmt->execute(['story_id' => $storyId]);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Восстановление комментария с инкрементом счетчика истории
     */
    public function restoreComment(int $id, int $storyId): bool
    {
        $db = Database::getConnection();
        try {
            $db->beginTransaction();
            
            // Вызываем восстановление из Core/Model
            $this->restore($id);

            // Увеличиваем счетчик комментариев обратно
            $stmt = $db->prepare("UPDATE `stories` SET `comments_count` = `comments_count` + 1 WHERE `id` = :story_id");
            $stmt->execute(['story_id' => $storyId]);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }
}

