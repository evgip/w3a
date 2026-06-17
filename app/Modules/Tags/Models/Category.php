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
     * Получить категорию по ID (без логики soft-delete родительского класса)
     */
    public function getById($id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = static::db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
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