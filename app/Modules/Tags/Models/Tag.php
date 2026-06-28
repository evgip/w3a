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
	
	/**
	 * Получить полную информацию о тегах по их ID.
	 * 
	 * Возвращает ассоциативный массив: id => ['name' => ..., 'tag' => ...]
	 * Используется когда нужны и название, и URL-слаг тега.
	 * 
	 * @param array $tagIds Массив ID тегов
	 * @return array Ассоциативный массив с полной информацией
	 */
	public function getDetailsByIds(array $tagIds): array
	{
		if (empty($tagIds)) {
			return [];
		}
		
		// Убираем дубликаты и приводим к int
		$tagIds = array_unique(array_map('intval', $tagIds));
		
		// Создаем плейсхолдеры для IN clause
		$placeholders = implode(',', array_fill(0, count($tagIds), '?'));
		
		$sql = "SELECT id, name, tag FROM `{$this->table}` WHERE id IN ({$placeholders})";
		$stmt = static::db()->prepare($sql);
		$stmt->execute(array_values($tagIds));
		
		// Создаем ассоциативный массив id => данные
		$result = [];
		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			$result[(int) $row['id']] = [
				'name' => $row['name'],
				'tag' => $row['tag']
			];
		}
		
		return $result;
	}
	
    // =========================================================================
    // МЕТОДЫ ДЛЯ РАБОТЫ С ID (ИСПОЛЬЗУЮТСЯ В SUGGESTION SERVICE)
    // =========================================================================
    
    /**
     * Получить имена тегов по их ID.
     * 
     * Используется для отображения названий тегов вместо ID.
     * Возвращает массив названий в том же порядке, что и входные ID.
     * 
     * @param array $tagIds Массив ID тегов
     * @return array Массив названий тегов (в том же порядке)
     */
    public function getNamesByIds(array $tagIds): array
    {
        if (empty($tagIds)) {
            return [];
        }
        
        // Убираем дубликаты и приводим к int
        $tagIds = array_unique(array_map('intval', $tagIds));
        
        // Создаем плейсхолдеры для IN clause
        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        
        $sql = "SELECT id, name FROM `{$this->table}` WHERE id IN ({$placeholders})";
        $stmt = static::db()->prepare($sql);
        $stmt->execute(array_values($tagIds));
        
        // Создаем ассоциативный массив id => name
        $namesById = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $namesById[(int) $row['id']] = $row['name'];
        }
        
        // Возвращаем названия в том же порядке, что и входные ID
        $result = [];
        foreach ($tagIds as $id) {
            if (isset($namesById[$id])) {
                $result[] = $namesById[$id];
            }
        }
        
        return $result;
    }
}
