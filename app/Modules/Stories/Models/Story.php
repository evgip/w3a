<?php

declare(strict_types=1);

namespace App\Modules\Stories\Models;

use App\Core\Model;

class Story extends Model
{
	protected string $table = 'stories';

	protected array $fillable = [
		'user_id',
		'title',
		'url',
		'text',
		'description',
		'rejected_fields',
		'user_is_following',
		'domain', 
		'score',
		'comments_count',
		'deleted_at'
	];

	/**
	 * Fetch active stories joined with authors, tags, and avatars (Admin reads thrashed rows)
	 * Получить ленту историй с пагинацией и учетом фильтров
	 */
	public function getFeed(int $limit, int $offset, string $tagname = '', bool $showDeleted = false, ?string $domain = '', array $excludeTagIds = []): array
	{
		$sql = "SELECT s.*, u.username as author_name, u.avatar as author_avatar,
				GROUP_CONCAT(t.tag ORDER BY t.tag ASC) as tag_list
				FROM `stories` s
				JOIN `users` u ON s.user_id = u.id
				LEFT JOIN `taggings` tg ON s.id = tg.story_id
				LEFT JOIN `tags` t ON tg.tag_id = t.id";

		$where = [];
		$bindings = [];

		if (!$showDeleted) {
			$where[] = "s.deleted_at IS NULL";
		}

		if ($tagname) {
			$where[] = "t.tag = :tag";
			$bindings[':tag'] = $tagname;
		}

		if ($domain) {
			$where[] = "s.domain = :domain";
			$bindings[':domain'] = $domain;
		}

		// Генерируем именованные параметры для каждого исключаемого тега
		if (!empty($excludeTagIds)) {
			$namedPlaceholders = [];
			foreach ($excludeTagIds as $index => $tagId) {
				$paramName = ":exclude_tag_{$index}";
				$namedPlaceholders[] = $paramName;
				$bindings[$paramName] = (int)$tagId;
			}

			$placeholdersStr = implode(',', $namedPlaceholders);
			$where[] = "s.id NOT IN (
				SELECT DISTINCT story_id FROM taggings 
				WHERE tag_id IN ($placeholdersStr)
			)";
		}

		if (!empty($where)) {
			$sql .= " WHERE " . implode(" AND ", $where);
		}

		$sql .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";

		// Добавляем limit и offset в bindings
		$bindings[':limit'] = $limit;
		$bindings[':offset'] = $offset;

		$stmt = static::db()->prepare($sql);

		$stmt->execute($bindings);
		$stories = $stmt->fetchAll();

		// Парсим теги
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
		$stmt = static::db()->query("SELECT * FROM `tags` ORDER BY `tag` ASC");
		return $stmt->fetchAll();
	}

	/**
	 * Получить общее количество историй с учетом фильтров
	 */
	public function getTotalCount(string $tagname = '', ?string $domain = '', array $excludeTagIds = []): int
	{
		$sql = "SELECT COUNT(DISTINCT s.id) FROM `stories` s
				LEFT JOIN `taggings` tg ON s.id = tg.story_id
				LEFT JOIN `tags` t ON tg.tag_id = t.id";

		$where = ["s.deleted_at IS NULL"];
		$bindings = [];

		if ($tagname) {
			$where[] = "t.tag = :tag";
			$bindings[':tag'] = $tagname;
		}

		if ($domain) {
			$where[] = "s.domain = :domain";
			$bindings[':domain'] = $domain;
		}

		// ✅ ИСПРАВЛЕНИЕ: Генерируем именованные параметры для каждого исключаемого тега
		if (!empty($excludeTagIds)) {
			$namedPlaceholders = [];
			foreach ($excludeTagIds as $index => $tagId) {
				$paramName = ":exclude_tag_{$index}";
				$namedPlaceholders[] = $paramName;
				$bindings[$paramName] = (int)$tagId;
			}

			$placeholdersStr = implode(',', $namedPlaceholders);
			$where[] = "s.id NOT IN (
				SELECT DISTINCT story_id FROM taggings 
				WHERE tag_id IN ($placeholdersStr)
			)";
		}

		$sql .= " WHERE " . implode(" AND ", $where);

		$stmt = static::db()->prepare($sql);
		$stmt->execute($bindings);

		return (int)$stmt->fetchColumn();
	}

	/**
	 * Получить одну конкретную историю с именем автора и списком тегов
	 * Fetch single story with author metadata and avatar references
	 */
	public function getSingleWithAuthor(int $id, bool $showDeleted = false): ?array
	{
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

		$stmt = static::db()->prepare($sql);
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
		// Мы НЕ фильтруем тут deleted_at IS NULL, чтобы не ломать дерево (обработаем в шаблоне)
		$sql = "SELECT c.*, u.username as author_name, u.avatar as author_avatar  
                FROM `comments` c 
                JOIN `users` u ON c.user_id = u.id 
                WHERE c.story_id = :story_id 
                ORDER BY c.parent_id ASC, c.id ASC";
		$stmt = static::db()->prepare($sql);
		$stmt->execute(['story_id' => $storyId]);
		return $stmt->fetchAll();
	}

	/**
	 * Fetch an array of only the tag IDs currently bound to a specific story
	 */
	public function getStoryTagIds(int $storyId): array
	{
		$stmt = static::db()->prepare("SELECT `tag_id` FROM `taggings` WHERE `story_id` = :id");
		$stmt->execute(['id' => $storyId]);
		return $stmt->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * Atomically sync and bind tags to a story inside a secure database transaction
	 */
	public function syncTags(int $storyId, array $tagIds): bool
	{
		try {
			static::db()->beginTransaction();

			// 1. Flush any stale existing tags bound to this story row
			$stmt = static::db()->prepare("DELETE FROM `taggings` WHERE `story_id` = :id");
			$stmt->execute(['id' => $storyId]);

			// 2. Insert the new checkbox parameters mapping safely
			if (!empty($tagIds)) {
				$stmt = static::db()->prepare("INSERT INTO `taggings` (`story_id`, `tag_id`) VALUES (:sid, :tid)");
				foreach ($tagIds as $tagId) {
					$stmt->execute([
						'sid' => $storyId,
						'tid' => (int)$tagId
					]);
				}
			}

			static::db()->commit();
			return true;
		} catch (\Exception $e) {
			static::db()->rollBack();
			\App\Core\Logger::error("Failed to execute tag synchronization mapping transaction: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Подписаться на историю (получать уведомления о комментариях)
	 */
	public function follow(int $storyId, int $userId): bool
	{
		$stmt = static::db()->prepare(
			"UPDATE stories SET user_is_following = 1 WHERE id = :id AND user_id = :user_id"
		);
		return $stmt->execute([
			'id' => $storyId,
			'user_id' => $userId
		]);
	}

	/**
	 * Отписаться от истории
	 */
	public function unfollow(int $storyId, int $userId): bool
	{
		$stmt = static::db()->prepare(
			"UPDATE stories SET user_is_following = 0 WHERE id = :id AND user_id = :user_id"
		);
		return $stmt->execute([
			'id' => $storyId,
			'user_id' => $userId
		]);
	}

	/**
	 * Переключить подписку
	 */
	public function toggleFollow(int $storyId, int $userId): bool
	{
		$stmt = static::db()->prepare(
			"UPDATE stories SET user_is_following = NOT user_is_following 
			 WHERE id = :id AND user_id = :user_id"
		);
		return $stmt->execute([
			'id' => $storyId,
			'user_id' => $userId
		]);
	}

	/**
	 * Проверить, подписан ли пользователь на историю
	 */
	public function isFollowing(int $storyId, int $userId): bool
	{
		$stmt = static::db()->prepare(
			"SELECT user_is_following FROM stories WHERE id = :id AND user_id = :user_id"
		);
		$stmt->execute([
			'id' => $storyId,
			'user_id' => $userId
		]);
		return (bool)$stmt->fetchColumn();
	}


	/**
	 * Получить ленту историй с учётом фильтров тегов
	 */
	public function getFeedWithFilters(int $limit, int $offset, array $excludeTagIds = [], ?string $tagname = null): array
	{
		$sql = "SELECT s.*, u.username as author_name, u.avatar as author_avatar,
				GROUP_CONCAT(t.tag ORDER BY t.tag ASC) as tag_list
				FROM `stories` s
				JOIN `users` u ON s.user_id = u.id
				LEFT JOIN `taggings` tg ON s.id = tg.story_id
				LEFT JOIN `tags` t ON tg.tag_id = t.id";

		$bindings = [];
		$where = ["s.deleted_at IS NULL"];

		// Исключаем истории с отфильтрованными тегами
		if (!empty($excludeTagIds)) {
			$placeholders = implode(',', array_fill(0, count($excludeTagIds), '?'));
			$where[] = "s.id NOT IN (
				SELECT DISTINCT story_id FROM taggings 
				WHERE tag_id IN ($placeholders)
			)";
			$bindings = array_merge($bindings, $excludeTagIds);
		}

		if ($tagname) {
			$where[] = "t.tag = :tag";
			$bindings['tag'] = $tagname;
		}

		$sql .= " WHERE " . implode(" AND ", $where);
		$sql .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";

		$stmt = static::db()->prepare($sql);

		// Привязываем параметры
		$paramIndex = 1;
		foreach ($excludeTagIds as $tagId) {
			$stmt->bindValue($paramIndex++, $tagId, \PDO::PARAM_INT);
		}

		if (isset($bindings['tag'])) {
			$stmt->bindValue(':tag', $bindings['tag']);
		}

		$stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

		$stmt->execute();
		$stories = $stmt->fetchAll();

		foreach ($stories as &$story) {
			$story['tags'] = !empty($story['tag_list']) ? explode(',', $story['tag_list']) : [];
		}

		return $stories;
	}

	/**
	 * Получить общее количество историй с учётом фильтров
	 */
	public function getTotalCountWithFilters(array $excludeTagIds = [], ?string $tagname = null): int
	{
		$sql = "SELECT COUNT(DISTINCT s.id) FROM `stories` s
				LEFT JOIN `taggings` tg ON s.id = tg.story_id
				LEFT JOIN `tags` t ON tg.tag_id = t.id
				WHERE s.deleted_at IS NULL";

		$bindings = [];

		if (!empty($excludeTagIds)) {
			$placeholders = implode(',', array_fill(0, count($excludeTagIds), '?'));
			$sql .= " AND s.id NOT IN (
				SELECT DISTINCT story_id FROM taggings 
				WHERE tag_id IN ($placeholders)
			)";
			$bindings = array_merge($bindings, $excludeTagIds);
		}

		if ($tagname) {
			$sql .= " AND t.tag = :tag";
			$bindings['tag'] = $tagname;
		}

		$stmt = static::db()->prepare($sql);

		$paramIndex = 1;
		foreach ($excludeTagIds as $tagId) {
			$stmt->bindValue($paramIndex++, $tagId, \PDO::PARAM_INT);
		}

		if (isset($bindings['tag'])) {
			$stmt->bindValue(':tag', $bindings['tag']);
		}

		$stmt->execute();

		return (int)$stmt->fetchColumn();
	}
}
