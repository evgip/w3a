<?php

declare(strict_types=1);

namespace App\Modules\Stories\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Logger;

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
    public function getFeed(
        int $limit, 
        int $offset, 
        string $tagslug = '', 
        bool $showDeleted = false, 
        ?string $domain = '', 
        array $excludeTagIds = [], 
        string $sort = 'hot',
        string $author = ''
    ): array
    {
        // Возвращаем tag_list как строку для обратной совместимости с шаблоном
        // Добавляем tags_combined для получения пары тег+имя
        $sql = "SELECT s.*, u.username as author_name, up.avatar as author_avatar, 
                GROUP_CONCAT(t.slug ORDER BY t.slug ASC) as tag_list,
                GROUP_CONCAT(CONCAT(t.slug, '||', t.name) ORDER BY t.slug ASC) as tags_combined
                FROM `stories` s
                JOIN `users` u ON s.user_id = u.id
                LEFT JOIN `user_profiles` up ON u.id = up.user_id
                LEFT JOIN `taggings` tg ON s.id = tg.story_id
                LEFT JOIN `tags` t ON tg.tag_id = t.id";

        $where = [];
        $bindings = [];

        if (!$showDeleted) {
            $where[] = "s.deleted_at IS NULL";
        }

        if ($tagslug) {
            $where[] = "t.slug = :slug";
            $bindings[':slug'] = $tagslug;
        }

        if ($domain) {
            $where[] = "s.domain = :domain";
            $bindings[':domain'] = $domain;
        }

        if ($author) {
            $where[] = "u.username = :author";
            $bindings[':author'] = $author;
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

        $orderBy = match ($sort) {
            'new' => 's.created_at DESC',
            'top' => 's.score DESC, s.created_at DESC',
            default => 's.hotness ASC',  // hot — по умолчанию
        };

        $sql .= " GROUP BY s.id ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";

        // Добавляем limit и offset в bindings
        $bindings[':limit'] = $limit;
        $bindings[':offset'] = $offset;

        $stories = $this->db->fetchAll($sql, $bindings);

        // Парсим теги
        foreach ($stories as &$story) {
            parseTagsCombined($story);
        }

        return $stories;
    }

    /**
     * Пересчитать и сохранить hotness для истории.
     */
    public function recalculateHotness(int $storyId): void
    {
        $story = $this->find($storyId);
        if (!$story) return;

        // Получаем модификаторы тегов, привязанных к этой истории
        $tagMods = $this->getTagHotnessMods($storyId);

        $hotness = calculate_hotness((int)$story['score'], $story['created_at'], $tagMods);

        $this->db->query("
            UPDATE `stories`
            SET `hotness` = :hotness
            WHERE `id` = :id
        ", [
            'hotness' => $hotness,
            'id' => $storyId,
        ]);
    }

    /**
     * Получить массив модификаторов hotness_mod для тегов истории.
     */
    private function getTagHotnessMods(int $storyId): array
    {
        $stmt = $this->db->query("
            SELECT COALESCE(t.hotness_mod, 0.0) as hotness_mod
            FROM `taggings` tg
            JOIN `tags` t ON tg.tag_id = t.id
            WHERE tg.story_id = :story_id
        ", ['story_id' => $storyId]);

        // Возвращаем плоский массив чисел для array_sum()
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'hotness_mod');
    }

    /**
     * Get all platform tags with description fields
     */
    public function getAllTags(): array
    {
        return $this->db->fetchAll("SELECT * FROM `tags` ORDER BY `slug` ASC");
    }

    /**
     * Получить общее количество историй с учетом фильтров
     */
    public function getTotalCount(
            string $tagslug = '', 
            ?string $domain = '', 
            array $excludeTagIds = [],
            string $author = ''
        ): int
    {
        $sql = "SELECT COUNT(DISTINCT s.id) FROM `stories` s
                JOIN `users` u ON s.user_id = u.id 
                LEFT JOIN `taggings` tg ON s.id = tg.story_id
                LEFT JOIN `tags` t ON tg.tag_id = t.id";

        $where = ["s.deleted_at IS NULL"];
        $bindings = [];

        if ($tagslug) {
            $where[] = "t.slug = :slug";
            $bindings[':slug'] = $tagslug;
        }

        if ($domain) {
            $where[] = "s.domain = :domain";
            $bindings[':domain'] = $domain;
        }

        if ($author) {
            $where[] = "u.username = :author";
            $bindings[':author'] = $author;
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

        $sql .= " WHERE " . implode(" AND ", $where);

        return (int)$this->db->fetchColumn($sql, $bindings);
    }

    /**
     * Получить одну конкретную историю с именем автора и списком тегов
     * Fetch single story with author metadata and avatar references
     */
    public function getSingleWithAuthor(int $id, bool $showDeleted = false): ?array
    {
        // Возвращаем tag_list как строку для обратной совместимости
        // Добавляем tags_combined для получения пары тег+имя
        $sql = "SELECT s.*, u.username as author_name, up.avatar as author_avatar,
                       GROUP_CONCAT(t.slug ORDER BY t.slug ASC) as tag_list,
                       GROUP_CONCAT(CONCAT(t.slug, '||', t.name) ORDER BY t.slug ASC) as tags_combined
                FROM `stories` s
                    JOIN `users` u ON s.user_id = u.id
                    LEFT JOIN `user_profiles` up ON u.id = up.user_id
                    LEFT JOIN `taggings` tg ON s.id = tg.story_id
                    LEFT JOIN `tags` t ON tg.tag_id = t.id
                    WHERE s.id = :id";

        if (!$showDeleted) {
            $sql .= " AND s.deleted_at IS NULL";
        }

        $sql .= " GROUP BY s.id LIMIT 1";

        $story = $this->db->fetchOne($sql, ['id' => $id]);

        if ($story) {
            // 1. Оставляем tag_list строкой (как было раньше)
            $story['tags'] = !empty($story['tag_list']) ? explode(',', $story['tag_list']) : [];

            // 2. Парсим tags_combined в массив объектов с именами
            $tagsWithNames = [];
            if (!empty($story['tags_combined'])) {
                foreach (explode(',', $story['tags_combined']) as $pair) {
                    list($slug, $name) = explode('||', $pair);
                    $tagsWithNames[] = [
                        'slug' => $slug,
                        'name' => $name
                    ];
                }
            }
            $story['tags_with_names'] = $tagsWithNames;

            // Удаляем служебное поле, чтобы не засорять массив
            unset($story['tags_combined']);

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
        $sql = "SELECT c.*, u.username as author_name, up.avatar as author_avatar 
                FROM `comments` c 
                JOIN `users` u ON c.user_id = u.id 
                LEFT JOIN `user_profiles` up ON u.id = up.user_id 
                WHERE c.story_id = :story_id 
                ORDER BY c.parent_id ASC, c.id ASC";
        
        return $this->db->fetchAll($sql, ['story_id' => $storyId]);
    }

    /**
     * Fetch an array of only the tag IDs currently bound to a specific story
     */
    public function getStoryTagIds(int $storyId): array
    {
        $stmt = $this->db->query("SELECT `tag_id` FROM `taggings` WHERE `story_id` = :id", ['id' => $storyId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Atomically sync and bind tags to a story inside a secure database transaction.
     * 
     * Валидация количества тегов выполняется на уровне сервиса (TagValidator).
     * Модель отвечает только за целостность данных в БД.
     * 
     * @param int   $storyId ID истории
     * @param array $tagIds  Массив ID тегов (должен быть провалидирован вызывающим кодом)
     * @return bool Успешно ли выполнено
     */
    public function syncTags(int $storyId, array $tagIds): bool
    {
        // Дедупликация и приведение к int
        $tagIds = array_unique(array_map('intval', $tagIds));

        // Если после дедупликации пусто — просто очищаем привязки
        if (empty($tagIds)) {
            try {
                return $this->db->execute("DELETE FROM `taggings` WHERE `story_id` = ?", [$storyId]) > 0;
            } catch (\Exception $e) {
                // ✅ Используем $this->logger вместо статического вызова
                if ($this->logger) {
                    $this->logger->error("Failed to clear tags: " . $e->getMessage());
                }
                return false;
            }
        }

        try {
            // ✅ Используем $this->db для транзакций
            $this->db->beginTransaction();

            // 1. Удаляем старые теги
            $this->db->execute("DELETE FROM `taggings` WHERE `story_id` = ?", [$storyId]);

            // 2. Множественный INSERT с позиционными плейсхолдерами
            $placeholders = [];
            $params = [];

            foreach ($tagIds as $tagId) {
                $placeholders[] = "(?, ?)";
                $params[] = $storyId;
                $params[] = (int)$tagId;
            }

            $sql = "INSERT INTO `taggings` (`story_id`, `tag_id`) VALUES " . implode(', ', $placeholders);
            $this->db->execute($sql, $params);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            if ($this->logger) {
                $this->logger->error("Failed to sync tags for story #{$storyId}: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Подписаться на историю (получать уведомления о комментариях)
     */
    public function follow(int $storyId, int $userId): bool
    {
        return $this->db->execute(
            "UPDATE stories SET user_is_following = 1 WHERE id = :id AND user_id = :user_id",
            [
                'id' => $storyId,
                'user_id' => $userId
            ]
        ) > 0;
    }

    /**
     * Отписаться от истории
     */
    public function unfollow(int $storyId, int $userId): bool
    {
        return $this->db->execute(
            "UPDATE stories SET user_is_following = 0 WHERE id = :id AND user_id = :user_id",
            [
                'id' => $storyId,
                'user_id' => $userId
            ]
        ) > 0;
    }

    /**
     * Переключить подписку
     */
    public function toggleFollow(int $storyId, int $userId): bool
    {
        return $this->db->execute(
            "UPDATE stories SET user_is_following = NOT user_is_following 
             WHERE id = :id AND user_id = :user_id",
            [
                'id' => $storyId,
                'user_id' => $userId
            ]
        ) > 0;
    }

    /**
     * Проверить, подписан ли пользователь на историю
     */
    public function isFollowing(int $storyId, int $userId): bool
    {
        return (bool)$this->db->fetchColumn(
            "SELECT user_is_following FROM stories WHERE id = :id AND user_id = :user_id",
            [
                'id' => $storyId,
                'user_id' => $userId
            ]
        );
    }

    /**
     * Получить ленту историй с учётом фильтров тегов
     */
    public function getFeedWithFilters(int $limit, int $offset, array $excludeTagIds = [], ?string $tagslug = null): array
    {
        $sql = "SELECT s.*, u.username as author_name, up.avatar as author_avatar,
                GROUP_CONCAT(t.slug ORDER BY t.slug ASC) as tag_list
                FROM `stories` s
                JOIN `users` u ON s.user_id = u.id
                LEFT JOIN `user_profiles` up ON u.id = up.user_id 
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

        if ($tagslug) {
            $where[] = "t.slug = :slug";
            $bindings['slug'] = $tagslug;
        }

        $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->query($sql);

        // Привязываем параметры
        $paramIndex = 1;
        foreach ($excludeTagIds as $tagId) {
            $stmt->bindValue($paramIndex++, $tagId, \PDO::PARAM_INT);
        }

        if (isset($bindings['slug'])) {
            $stmt->bindValue(':slug', $bindings['slug']);
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
    public function getTotalCountWithFilters(array $excludeTagIds = [], ?string $tagslug = null): int
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

        if ($tagslug) {
            $sql .= " AND t.slug = :slug";
            $bindings['slug'] = $tagslug;
        }

        $stmt = $this->db->query($sql);

        $paramIndex = 1;
        foreach ($excludeTagIds as $tagId) {
            $stmt->bindValue($paramIndex++, $tagId, \PDO::PARAM_INT);
        }

        if (isset($bindings['slug'])) {
            $stmt->bindValue(':slug', $bindings['slug']);
        }

        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /**
     * Атомарно изменяет счётчик комментариев.
     */
    public function incrementCommentsCount(int $storyId, int $delta): void
    {
        $this->db->execute(
            "UPDATE stories SET comments_count = GREATEST(0, comments_count + ?) WHERE id = ?",
            [$delta, $storyId]
        );
    }

    /**
     * Пересчитывает счётчик комментариев с нуля (для синхронизации).
     */
    public function recalculateCommentsCount(int $storyId): void
    {
        $this->db->execute("
            UPDATE stories s 
            SET comments_count = (
                SELECT COUNT(*) FROM comments c 
                WHERE c.story_id = s.id AND c.deleted_at IS NULL
            )
            WHERE s.id = ?
        ", [$storyId]);
    }
}