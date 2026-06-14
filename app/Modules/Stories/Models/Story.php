<?php

namespace App\Modules\Stories\Models;

use App\Core\Model;
use App\Core\Database;

class Story extends Model
{
    protected string $table = 'stories';

    protected array $fillable = [
        'user_id',
        'title',
        'url',
        'text',
		'description'
    ];

    /**
     * Fetch active stories joined with authors, tags, and avatars (Admin reads thrashed rows)
     */
	public function getFeed(int $limit, int $offset, ?string $tagname = null, bool $showDeleted = false, ?string $domain = null): array
	{
		$db = Database::getConnection();
		$sql = "SELECT s.*, u.username as author_name, u.avatar as author_avatar,
				GROUP_CONCAT(t.tag ORDER BY t.tag ASC) as tag_list
				FROM `stories` s
				JOIN `users` u ON s.user_id = u.id
				LEFT JOIN `taggings` tg ON s.id = tg.story_id
				LEFT JOIN `tags` t ON tg.tag_id = t.id";
		
		$bindings = [];
		$where = [];

		if (!$showDeleted) {
			$where[] = "s.deleted_at IS NULL";
		}

		if ($tagname) {
			$where[] = "t.tag = :tag";
			$bindings['tag'] = $tagname;
		}

		// Добавляем условие для домена
		if ($domain) {
			$where[] = "s.url LIKE :domain";
			$bindings['domain'] = '%' . $domain . '%';
		}

		if (!empty($where)) {
			$sql .= " WHERE " . implode(" AND ", $where);
		}

		$sql .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";

		$stmt = $db->prepare($sql);
		$stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
		if (isset($bindings['tag'])) $stmt->bindValue(':tag', $bindings['tag']);
		if (isset($bindings['domain'])) $stmt->bindValue(':domain', $bindings['domain']);

		$stmt->execute();
		$stories = $stmt->fetchAll();

		foreach ($stories as &$story) {
			$story['tags'] = !empty($story['tag_list']) ? explode(',', $story['tag_list']) : [];
		}
		return $stories;
	}

    /**
     * Get all platform tags with description fields
     */
    public function getAllTags(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM `tags` ORDER BY `tag` ASC");
        return $stmt->fetchAll();
    }

    /**
     * Получить общее количество активных постов для расчета страниц
     */
	public function getTotalCount(?string $tagname = null, ?string $domain = null): int
	{
		$db = Database::getConnection();
		
		// Используем COUNT(DISTINCT s.id), чтобы избежать дублирования при LEFT JOIN тегов
		$sql = "SELECT COUNT(DISTINCT s.id) FROM `stories` s 
				LEFT JOIN `taggings` tg ON s.id = tg.story_id
				LEFT JOIN `tags` t ON tg.tag_id = t.id
				WHERE s.deleted_at IS NULL";
		
		$bindings = [];
		if ($tagname) {
			$sql .= " AND t.tag = :tag";
			$bindings['tag'] = $tagname;
		}
		if ($domain) {
			$sql .= " AND s.url LIKE :domain";
			$bindings['domain'] = '%' . $domain . '%';
		}
		
		$stmt = $db->prepare($sql);
		if (isset($bindings['tag'])) $stmt->bindValue(':tag', $bindings['tag']);
		if (isset($bindings['domain'])) $stmt->bindValue(':domain', $bindings['domain']);
		
		$stmt->execute();
		return (int)$stmt->fetchColumn();
	}

   /**
     * Получить одну конкретную историю с именем автора и списком тегов
     * Fetch single story with author metadata and avatar references
     */
 public function getSingleWithAuthor(int $id, bool $showDeleted = false): ?array
    {
        $db = Database::getConnection();
        
        $sql = "SELECT s.*, u.username as author_name, u.avatar as author_avatar,
                       GROUP_CONCAT(t.tag ORDER BY t.tag ASC) as tag_list
                FROM `stories` s
                JOIN `users` u ON s.user_id = u.id
                LEFT JOIN `taggings` tg ON s.id = tg.story_id
                LEFT JOIN `tags` t ON tg.tag_id = t.id
                WHERE s.id = :id";

        if (!$showDeleted) {
            $sql .= " AND s.deleted_at IS NULL";
        }

        $sql .= " GROUP BY s.id LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $story = $stmt->fetch();

        if ($story) {
            $story['tags'] = !empty($story['tag_list']) ? explode(',', $story['tag_list']) : [];
            return $story;
        }
        return null;
    }
    /**
     * Выгрузить ВСЕ комментарии к истории за ОДИН запрос
     */
    public function getCommentsForStory(int $storyId): array
    {
        $db = Database::getConnection();
        // Мы НЕ фильтруем тут deleted_at IS NULL, чтобы не ломать дерево (обработаем в шаблоне)
        $sql = "SELECT c.*, u.username as author_name, u.avatar as author_avatar  
                FROM `comments` c 
                JOIN `users` u ON c.user_id = u.id 
                WHERE c.story_id = :story_id 
                ORDER BY c.parent_id ASC, c.id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute(['story_id' => $storyId]);
        return $stmt->fetchAll();
    }
	
    /**
     * Fetch an array of only the tag IDs currently bound to a specific story
     */
    public function getStoryTagIds(int $storyId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT `tag_id` FROM `taggings` WHERE `story_id` = :id");
        $stmt->execute(['id' => $storyId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Atomically sync and bind tags to a story inside a secure database transaction
     */
    public function syncTags(int $storyId, array $tagIds): bool
    {
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // 1. Flush any stale existing tags bound to this story row
            $stmt = $db->prepare("DELETE FROM `taggings` WHERE `story_id` = :id");
            $stmt->execute(['id' => $storyId]);

            // 2. Insert the new checkbox parameters mapping safely
            if (!empty($tagIds)) {
                $stmt = $db->prepare("INSERT INTO `taggings` (`story_id`, `tag_id`) VALUES (:sid, :tid)");
                foreach ($tagIds as $tagId) {
                    $stmt->execute([
                        'sid' => $storyId,
                        'tid' => (int)$tagId
                    ]);
                }
            }

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            \App\Core\Logger::error("Failed to execute tag synchronization mapping transaction: " . $e->getMessage());
            return false;
        }
    }
}

