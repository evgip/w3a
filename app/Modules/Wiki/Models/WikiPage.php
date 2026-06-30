<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Models;

use App\Core\Model;

/**
 * Модель Wiki страницы.
 *
 * Отвечает за работу с таблицей wiki_pages.
 */
class WikiPage extends Model
{
    protected string $table = 'wiki_pages';

    protected array $fillable = [
        'tag_id',
        'title',
        'slug',
        'content',
        'rendered_content',
        'author_id',
        'is_primary',
        'status',
        'view_count',
		'deleted_at'
    ];

    /**
     * Получить все опубликованные wiki страницы
     */
    public function getAllPublished(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT wp.*, 
                       u.username as author_name,
                       t.name as tag_name,
                       t.tag as tag_slug
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                LEFT JOIN tags t ON wp.tag_id = t.id
                WHERE wp.status = 'published' 
                  AND wp.deleted_at IS NULL
                ORDER BY wp.updated_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = static::db()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Получить страницу по slug (опционально в контексте тега)
     */
    public function getBySlug(string $slug, ?int $tagId = null): ?array
    {
        $sql = "SELECT wp.*, 
                       u.username as author_name,
                       t.name as tag_name,
                       t.tag as tag_slug
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                LEFT JOIN tags t ON wp.tag_id = t.id
                WHERE wp.slug = :slug 
                  AND wp.deleted_at IS NULL";

        $params = ['slug' => $slug];

        if ($tagId !== null) {
            $sql .= " AND wp.tag_id = :tag_id";
            $params['tag_id'] = $tagId;
        }

        $sql .= " LIMIT 1";

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);

        $page = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $page ?: null;
    }

    /**
     * Получить wiki страницы для тега
     */
    public function getForTag(int $tagId): array
    {
        $sql = "SELECT wp.*, u.username as author_name
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                WHERE wp.tag_id = :tag_id 
                  AND wp.status = 'published'
                  AND wp.deleted_at IS NULL
                ORDER BY wp.is_primary DESC, wp.title ASC";

        $stmt = static::db()->prepare($sql);
        $stmt->execute(['tag_id' => $tagId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Получить основную страницу тега
     */
    public function getPrimaryForTag(int $tagId): ?array
    {
        $sql = "SELECT wp.*, u.username as author_name
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                WHERE wp.tag_id = :tag_id 
                  AND wp.is_primary = 1
                  AND wp.status = 'published'
                  AND wp.deleted_at IS NULL
                LIMIT 1";

        $stmt = static::db()->prepare($sql);
        $stmt->execute(['tag_id' => $tagId]);

        $page = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $page ?: null;
    }

    /**
     * Увеличить счетчик просмотров
     */
    public function incrementViewCount(int $pageId): void
    {
        $sql = "UPDATE {$this->table} SET view_count = view_count + 1 WHERE id = :id";
        $stmt = static::db()->prepare($sql);
        $stmt->execute(['id' => $pageId]);
    }

    /**
     * Проверить существование slug
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
     * Поиск по wiki страницам (глобальный)
     */
    public function search(string $query, int $limit = 20): array
    {
        $sql = "SELECT wp.*, u.username as author_name
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                WHERE wp.status = 'published'
                  AND wp.deleted_at IS NULL
                  AND (wp.title LIKE :query OR wp.content LIKE :query)
                ORDER BY wp.updated_at DESC
                LIMIT :limit";

        $stmt = static::db()->prepare($sql);
        $stmt->bindValue(':query', '%' . $query . '%', \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Поиск по wiki страницам тега
     */
    public function searchInTag(int $tagId, string $query, int $limit = 20): array
    {
        $sql = "SELECT wp.*, u.username as author_name
                FROM {$this->table} wp
                LEFT JOIN users u ON wp.author_id = u.id
                WHERE wp.tag_id = :tag_id
                  AND wp.status = 'published'
                  AND wp.deleted_at IS NULL
                  AND (wp.title LIKE :query OR wp.content LIKE :query)
                ORDER BY wp.updated_at DESC
                LIMIT :limit";

        $stmt = static::db()->prepare($sql);
        $stmt->bindValue(':tag_id', $tagId, \PDO::PARAM_INT);
        $stmt->bindValue(':query', '%' . $query . '%', \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Сбросить флаг is_primary для всех страниц тега
     */
    public function resetPrimaryFlag(int $tagId): void
    {
        $sql = "UPDATE {$this->table} SET is_primary = 0 WHERE tag_id = :tag_id";
        $stmt = static::db()->prepare($sql);
        $stmt->execute(['tag_id' => $tagId]);
    }

    /**
     * Получить количество страниц для тега
     */
    public function getCountForTag(int $tagId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE tag_id = :tag_id 
                  AND status = 'published'
                  AND deleted_at IS NULL";

        $stmt = static::db()->prepare($sql);
        $stmt->execute(['tag_id' => $tagId]);

        return (int)$stmt->fetchColumn();
    }
}
