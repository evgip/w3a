<?php

namespace App\Modules\Tags\Models;

use App\Core\Model;
use App\Core\Database;

class Tag extends Model
{
    protected string $table = 'tags';

	protected array $fillable = [
		'name',
		'slug',
		'tag',
		'description',
		'is_media',
		'category'
	];

    /**
     * Получить все теги, отсортированные по категориям Lobsters
     */
   public function getAllTags(): array
    {
        $db = Database::getConnection();
        
        // LEFT JOIN гарантирует, что даже теги с 0 историй будут в списке
        $sql = "SELECT t.id, t.tag, t.description, t.category, t.is_media, 
                       COUNT(tg.story_id) as stories_count
                FROM {$this->table} t
                LEFT JOIN taggings tg ON t.id = tg.tag_id
                GROUP BY t.id, t.tag, t.description, t.category
                ORDER BY t.tag ASC";
                
        $stmt = $db->query($sql);
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
