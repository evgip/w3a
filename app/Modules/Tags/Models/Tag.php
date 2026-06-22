<?php

namespace App\Modules\Tags\Models;

use App\Core\Model;

class Tag extends Model
{
    protected string $table = 'tags';
	
    protected array $fillable = [
        'name',
        'tag', 
        'description',
        'is_media',
        'category_id',
		'hotness_mod'
    ];

    /**
     * Получить все теги, отсортированные по категориям Lobsters
     */
	public function getAllTags(bool $withDeleted = false): array
	{
		// Если $withDeleted = true, условие WHERE будет пустым. 
		// Если false (по умолчанию), добавится фильтрация удаленных записей.
		$whereClause = $withDeleted ? '' : 'WHERE t.deleted_at IS NULL';

		$sql = "SELECT t.id, t.name, t.tag, t.description, t.hotness_mod, t.category_id, t.is_media, t.deleted_at,
					   c.name as category_name, c.slug as category_slug,
					   COUNT(tg.story_id) as stories_count
				FROM {$this->table} t
				LEFT JOIN categories c ON t.category_id = c.id
				LEFT JOIN taggings tg ON t.id = tg.tag_id
				$whereClause
				GROUP BY t.id, t.tag, t.description, t.category_id, 
						 c.name, c.slug, t.is_media
				ORDER BY t.tag ASC";
	 
		$stmt = static::db()->query($sql);
		return $stmt->fetchAll();
	}

    /**
     * Check if a tag string slug already exists matching another ID (for safe edits)
     */
    public function exists(string $tagName, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `tags` WHERE `tag` = :tag";
        $params = ['tag' => trim($tagName)];

        if ($excludeId !== null) {
            $sql .= " AND `id` != :id";
            $params['id'] = $excludeId;
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}
