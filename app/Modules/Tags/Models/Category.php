<?php

namespace App\Modules\Tags\Models;

use App\Core\Model;

class Category extends Model
{
    protected string $table = 'categories';

    protected array $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
    ];

   /**
     * Получить все категории с количеством тегов (для публичной страницы /categories)
     */
    public function getAllWithTagsCount(): array
    {
        $sql = "SELECT c.id, c.name, c.slug, c.description, c.sort_order,
                       COUNT(t.id) as tags_count
                FROM {$this->table} c
                LEFT JOIN tags t ON t.category_id = c.id AND t.deleted_at IS NULL
                GROUP BY c.id, c.name, c.slug, c.description, c.sort_order
                ORDER BY c.sort_order ASC, c.name ASC";

        $stmt = static::db()->query($sql);
        return $stmt->fetchAll();
    }

	/**
	 * Получить истории, связанные с тегами конкретной категории по slug
	 */
	public function getStoriesBySlug(string $slug, int $limit, int $offset, array $excludeTagIds = []): ?array
	{
		// Получаем категорию
		$sql = "SELECT * FROM {$this->table} WHERE slug = :slug LIMIT 1";
		$stmt = static::db()->prepare($sql);
		$stmt->execute(['slug' => $slug]);
		$category = $stmt->fetch();

		if (!$category) {
			return null;
		}

		// Получаем истории с тегами из этой категории
		$sql = "SELECT DISTINCT s.*, u.username as author_name, up.avatar as author_avatar,
					   GROUP_CONCAT(t.tag ORDER BY t.tag ASC) as tag_list,
					   GROUP_CONCAT(CONCAT(t.tag, '||', t.name) ORDER BY t.tag ASC) as tags_combined
				FROM stories s
				JOIN users u ON s.user_id = u.id
				LEFT JOIN `user_profiles` up ON u.id = up.user_id
				JOIN taggings tg ON s.id = tg.story_id
				JOIN tags t ON tg.tag_id = t.id
				WHERE t.category_id = :category_id 
				  AND s.deleted_at IS NULL
				  AND t.deleted_at IS NULL";

		$bindings = [':category_id' => $category['id']];

		// Исключаем истории с тегами из черного списка пользователя
		if (!empty($excludeTagIds)) {
			$namedPlaceholders = [];
			foreach ($excludeTagIds as $index => $tagId) {
				$paramName = ":exclude_tag_{$index}";
				$namedPlaceholders[] = $paramName;
				$bindings[$paramName] = (int)$tagId;
			}
			
			$placeholdersStr = implode(',', $namedPlaceholders);

			$sql .= " LEFT JOIN taggings tg_exclude ON s.id = tg_exclude.story_id 
				  AND tg_exclude.tag_id IN ($placeholdersStr)
				  WHERE tg_exclude.story_id IS NULL";
		}

		$sql .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";

		$bindings[':limit'] = $limit;
		$bindings[':offset'] = $offset;

		$stmt = static::db()->prepare($sql);
		$stmt->execute($bindings);
		$stories = $stmt->fetchAll();

		// Парсим теги
		foreach ($stories as &$story) {
			parseTagsCombined($story);
		}

		$category['stories'] = $stories;
		return $category;
	}

	/**
	 * Получить общее количество историй в категории
	 */
	public function getStoriesCountBySlug(string $slug, array $excludeTagIds = []): int
	{
		// Получаем категорию
		$sql = "SELECT id FROM {$this->table} WHERE slug = :slug LIMIT 1";
		$stmt = static::db()->prepare($sql);
		$stmt->execute(['slug' => $slug]);
		$category = $stmt->fetch();

		if (!$category) {
			return 0;
		}

		$sql = "SELECT COUNT(DISTINCT s.id) 
				FROM stories s
				JOIN taggings tg ON s.id = tg.story_id
				JOIN tags t ON tg.tag_id = t.id
				WHERE t.category_id = :category_id 
				  AND s.deleted_at IS NULL
				  AND t.deleted_at IS NULL";

		$bindings = [':category_id' => $category['id']];

		if (!empty($excludeTagIds)) {
			$namedPlaceholders = [];
			foreach ($excludeTagIds as $index => $tagId) {
				$paramName = ":exclude_tag_{$index}";
				$namedPlaceholders[] = $paramName;
				$bindings[$paramName] = (int)$tagId;
			}
			
			$placeholdersStr = implode(',', $namedPlaceholders);
			$sql .= " AND s.id NOT IN (
				SELECT DISTINCT story_id FROM taggings 
				WHERE tag_id IN ($placeholdersStr)
			)";
		}

		$stmt = static::db()->prepare($sql);
		$stmt->execute($bindings);

		return (int)$stmt->fetchColumn();
	}

    /**
     * Получить все категории с количеством тегов для админки
     */
    public function getAdminCategoriesList(): array
    {
        $sql = "SELECT c.*, 
                       COUNT(t.id) as tags_count
                FROM {$this->table} c
                LEFT JOIN tags t ON t.category_id = c.id AND t.deleted_at IS NULL
                GROUP BY c.id
                ORDER BY c.sort_order ASC, c.name ASC";

        $stmt = static::db()->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Получить все категории (простой список для select)
     */
    public function getAllOrdered(): array
    {
        $sql = "SELECT id, name, slug FROM {$this->table} 
                ORDER BY sort_order ASC, name ASC";
        $stmt = static::db()->query($sql);
        return $stmt->fetchAll();
    }

    // Метод find() удалён, так как он уже есть в родительском классе App\Core\Model
    // и будет автоматически унаследован.

    /**
     * Проверить уникальность slug
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE slug = :slug";
        $params = ['slug' => $slug];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Создать новую категорию
     */
    public function createCategory(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (name, slug, description, sort_order) 
                VALUES (:name, :slug, :description, :sort_order)";

        $stmt = static::db()->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return (int)static::db()->lastInsertId();
    }

    /**
     * Обновить категорию
     */
    public function updateCategory(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table} 
                SET name = :name, slug = :slug, description = :description, sort_order = :sort_order
                WHERE id = :id";

        $stmt = static::db()->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * Удалить категорию
     */
    public function deleteCategory(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = static::db()->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Проверить, есть ли теги в категории (для защиты от удаления)
     */
    public function hasTags(int $categoryId): bool
    {
        $sql = "SELECT COUNT(*) FROM tags WHERE category_id = :id AND deleted_at IS NULL";
        $stmt = static::db()->prepare($sql);
        $stmt->execute(['id' => $categoryId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}