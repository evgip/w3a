<?php

namespace App\Modules\Tags\Models;

use App\Core\Model;

class TagFilter extends Model
{
    protected string $table = 'tag_filters';
    
    protected array $fillable = [
        'user_id',
        'tag_id'
    ];

    /**
     * Получить все фильтры пользователя с информацией о тегах
     */
    public function getUserFilters(int $userId): array
    {
        $sql = "SELECT tf.*, t.tag, t.description 
                FROM {$this->table} tf
                JOIN tags t ON t.id = tf.tag_id
                WHERE tf.user_id = :user_id
                ORDER BY t.tag ASC";
        
        $stmt = static::db()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Добавить тег в фильтры
     */
    public function addFilter(int $userId, int $tagId): bool
    {
        // Проверяем, не добавлен ли уже
        $exists = $this->findByUserAndTag($userId, $tagId);
        
        if ($exists) {
            return false;
        }

        $this->create([
            'user_id' => $userId,
            'tag_id'  => $tagId,
        ]);
        
        return true;
    }

    /**
     * Удалить тег из фильтров
     */
    public function removeFilter(int $userId, int $tagId): bool
    {
        $sql = "DELETE FROM {$this->table} 
                WHERE user_id = :user_id AND tag_id = :tag_id";
        
        $stmt = static::db()->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'tag_id'  => $tagId
        ]);
    }

    /**
     * Проверить, отфильтрован ли тег для пользователя
     */
    public function isTagFiltered(int $userId, int $tagId): bool
    {
        $filter = $this->findByUserAndTag($userId, $tagId);
        return (bool)$filter;
    }

    /**
     * Получить ID отфильтрованных тегов для пользователя
     */
    public function getFilteredTagIds(int $userId): array
    {
        $sql = "SELECT tag_id FROM {$this->table} WHERE user_id = :user_id";
        
        $stmt = static::db()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Получить количество фильтров пользователя
     */
    public function getUserFilterCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE user_id = :user_id";
        
        $stmt = static::db()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Найти фильтр по пользователю и тегу
     */
    private function findByUserAndTag(int $userId, int $tagId): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = :user_id AND tag_id = :tag_id 
                LIMIT 1";
        
        $stmt = static::db()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'tag_id'  => $tagId
        ]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
}