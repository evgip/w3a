<?php
// app/Modules/Saved/Models/SavedStory.php

declare(strict_types=1);

namespace App\Modules\Saved\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Logger;

class SavedStory extends Model
{
    protected string $table = 'saved_stories';

    protected array $fillable = ['user_id', 'story_id'];

    /**
     * Сохранить историю в закладки
     */
    public function save(int $userId, int $storyId): bool
    {
        try {
            $sql = "INSERT INTO `saved_stories` (`user_id`, `story_id`) 
                    VALUES (:user_id, :story_id)
                    ON DUPLICATE KEY UPDATE `created_at` = CURRENT_TIMESTAMP";
            
            return $this->db->execute($sql, [
                'user_id' => $userId,
                'story_id' => $storyId,
            ]) > 0;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("SavedStory::save failed: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Удалить историю из закладок
     */
    public function unsave(int $userId, int $storyId): bool
    {
        return $this->db->execute(
            "DELETE FROM `saved_stories` WHERE `user_id` = ? AND `story_id` = ?",
            [$userId, $storyId]
        ) > 0;
    }

    /**
     * Проверить, сохранена ли история
     */
    public function isSaved(int $userId, int $storyId): bool
    {
        return (bool)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `saved_stories` WHERE `user_id` = ? AND `story_id` = ?",
            [$userId, $storyId]
        );
    }

    /**
     * Получить список сохранённых историй пользователя с пагинацией
     */
    public function getUserSaved(int $userId, int $limit, int $offset): array
    {
        $sql = "SELECT s.*, 
                       u.username as author_name, 
                       up.avatar as author_avatar,
                       GROUP_CONCAT(t.slug ORDER BY t.slug ASC) as tag_list,
                       GROUP_CONCAT(CONCAT(t.slug, '||', t.name) ORDER BY t.slug ASC) as tags_combined,
                       ss.created_at as saved_at
                FROM `saved_stories` ss
                JOIN `stories` s ON ss.story_id = s.id
                JOIN `users` u ON s.user_id = u.id
                LEFT JOIN `user_profiles` up ON u.id = up.user_id
                LEFT JOIN `taggings` tg ON s.id = tg.story_id
                LEFT JOIN `tags` t ON tg.tag_id = t.id
                WHERE ss.user_id = :user_id AND s.deleted_at IS NULL
                GROUP BY s.id
                ORDER BY ss.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stories = $this->db->fetchAll($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ]);
        
        foreach ($stories as &$story) {
            parseTagsCombined($story);
        }
        
        return $stories;
    }

    /**
     * Получить общее количество сохранённых историй
     */
    public function getUserSavedCount(int $userId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `saved_stories` ss
             JOIN `stories` s ON ss.story_id = s.id
             WHERE ss.user_id = ? AND s.deleted_at IS NULL",
            [$userId]
        );
    }

    /**
     * Получить массив сохранённых story_id для пользователя (для отметки в ленте)
     */
    public function getUserSavedStoryIds(int $userId): array
    {
        $result = $this->db->fetchAll(
            "SELECT `story_id` FROM `saved_stories` WHERE `user_id` = ?",
            [$userId]
        );
        return array_column($result, 'story_id');
    }
}