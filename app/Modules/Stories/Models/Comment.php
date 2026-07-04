<?php

declare(strict_types=1);

namespace App\Modules\Stories\Models;

use Exception;
use App\Core\Model;
use App\Core\Database;
use App\Core\Logger;

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

    /**
     * Сохранить комментарий с транзакцией
     */
    public function saveComment(array $data): int
    {
        try {
            $this->db->beginTransaction();

            // Создаем комментарий и получаем его ID
            $commentId = $this->create([
                'story_id' => $data['story_id'],
                'user_id' => $data['user_id'],
                'parent_id' => $data['parent_id'],
                'comment' => $data['comment'],
                'score' => 1
            ]);

            $this->db->commit();

            // Возвращаем ID созданного комментария (0 при ошибке)
            return $commentId;
        } catch (Exception $e) {
            $this->db->rollBack();
            if ($this->logger) {
                $this->logger->error("Comment::saveComment failed: " . $e->getMessage());
            }
            return 0;
        }
    }

    /**
     * Мягкое удаление комментария.
     * Счётчик комментариев обновляется автоматически через Event Listener.
     */
    public function softDeleteComment(int $commentId): bool
    {
        return $this->db->execute(
            "UPDATE comments SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL",
            [$commentId]
        ) > 0;
    }

    /**
     * Восстановление удалённого комментария.
     * Счётчик комментариев обновляется автоматически через Event Listener.
     */
    public function restoreComment(int $commentId): bool
    {
        return $this->db->execute(
            "UPDATE comments SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL",
            [$commentId]
        ) > 0;
    }

    /**
     * Получает полную информацию о комментарии с данными истории.
     *
     * @param int $commentId ID комментария
     * @return array|null Массив с данными или null
     */
    public function getWithStoryInfo(int $commentId): ?array
    {
        $sql = "SELECT 
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
                WHERE c.id = :comment_id";

        return $this->db->fetchOne($sql, ['comment_id' => $commentId]);
    }

    /**
     * Получает ID автора комментария.
     *
     * @param int $commentId ID комментария
     * @return int|null ID автора или null
     */
    public function getAuthorId(int $commentId): ?int
    {
        $result = $this->db->fetchOne(
            "SELECT user_id FROM comments WHERE id = :id",
            ['id' => $commentId]
        );

        return $result ? (int)$result['user_id'] : null;
    }

    /**
     * Получить комментарий по ID с данными для вычисления confidence_score
     */
    public function getCommentById(int $commentId): ?array
    {
        return $this->db->fetchOne(
            "SELECT id, score, flag_count 
             FROM comments 
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $commentId]
        );
    }

    /**
     * Обновить confidence_score комментария
     */
    public function updateConfidenceScore(int $commentId, float $confidenceScore): bool
    {
        return $this->db->execute(
            "UPDATE comments SET confidence_score = :score WHERE id = :id",
            [
                'score' => $confidenceScore,
                'id' => $commentId,
            ]
        ) > 0;
    }

    /**
     * Получить общее количество не удаленных комментариев
     */
    public function getCommentsCount(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM comments WHERE deleted_at IS NULL"
        );
    }

    /**
     * Получить пакет комментариев для пересчета
     */
    public function getCommentsBatch(int $offset, int $limit): array
    {
        $sql = "SELECT id, score, flag_count 
                FROM comments 
                WHERE deleted_at IS NULL
                ORDER BY id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->query($sql, [
            'limit' => $limit,
            'offset' => $offset
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}