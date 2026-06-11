<?php

namespace App\Modules\Tags\Models;

use App\Core\Model;
use App\Core\Database;

class Tag extends Model
{
    protected string $table = 'tags';

    /**
     * Получить все теги, отсортированные по категориям Lobsters
     */
    public function getAllTags(): array
    {
        $db = \App\Core\Database::getConnection();
        // Сортируем сначала по категориям, затем по имени тега
        $stmt = $db->query("SELECT * FROM `tags` WHERE `deleted_at` IS NULL ORDER BY `category` ASC, `tag` ASC");
        return $stmt->fetchAll();
    }

    /**
     * Check if a tag string slug already exists matching another ID (for safe edits)
     */
    public function exists(string $tagName, ?int $excludeId = null): bool
    {
        $db = Database::getConnection();
        $sql = "SELECT COUNT(*) FROM `tags` WHERE `tag` = :tag";
        $params = ['tag' => trim($tagName)];

        if ($excludeId !== null) {
            $sql .= " AND `id` != :id";
            $params['id'] = $excludeId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}
