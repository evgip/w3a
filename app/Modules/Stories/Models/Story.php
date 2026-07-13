<?php

declare(strict_types=1);

namespace App\Modules\Stories\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Logger;
use App\Modules\Stories\Services\RankingService; 

class Story extends Model
{
    protected string $table = 'stories';
    private RankingService $rankingService;

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
     * Конструктор с инъекцией RankingService
     */
    public function __construct(
        Database $db, 
        Logger $logger, 
        ?RankingService $rankingService = null
    ) {
        parent::__construct($db, $logger);
        $this->rankingService = $rankingService ?? new RankingService();
    }

    /**
     * Построить общие WHERE условия и биндинги для выборки историй (лента, счетчики).
     * 
     * @param array<int, int|string> $excludeTagIds
     * @param array<int, int|string> $mutedUserIds
     * @return array{sql: string, bindings: array<string, mixed>}
     */
    private function buildFeedConditions(
        string $tagslug = '', 
        ?string $domain = '', 
        array $excludeTagIds = [],
        string $author = '',
        array $mutedUserIds = [],
        bool $showDeleted = false
    ): array {
        $where = [];
        $bindings = [];

        if (!$showDeleted) {
            $where[] = "s.deleted_at IS NULL";
        }

        if ($tagslug !== '') {
            $where[] = "t.slug = :slug";
            $bindings[':slug'] = $tagslug;
        }

        if ($domain !== null && $domain !== '') {
            $where[] = "s.domain = :domain";
            $bindings[':domain'] = $domain;
        }

        if ($author !== '') {
            $where[] = "u.username = :author";
            $bindings[':author'] = $author;
        }

        if (!empty($mutedUserIds)) {
            $mutedPlaceholders = [];
            foreach ($mutedUserIds as $index => $mutedId) {
                $paramName = ":muted_user_{$index}";
                $mutedPlaceholders[] = $paramName;
                $bindings[$paramName] = (int)$mutedId;
            }

            $mutedPlaceholdersStr = implode(',', $mutedPlaceholders);
            $where[] = "s.user_id NOT IN ($mutedPlaceholdersStr)";
        }

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

        $sql = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        return [
            'sql' => $sql,
            'bindings' => $bindings,
        ];
    }

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
        string $author = '',
        array $mutedUserIds = []
    ): array
    {
        $sql = "SELECT s.*, u.username as author_name, up.avatar as author_avatar, 
                GROUP_CONCAT(t.slug ORDER BY t.slug ASC) as tag_list,
                GROUP_CONCAT(CONCAT(t.slug, '||', t.name) ORDER BY t.slug ASC) as tags_combined
                FROM `stories` s
                JOIN `users` u ON s.user_id = u.id
                LEFT JOIN `user_profiles` up ON u.id = up.user_id
                LEFT JOIN `taggings` tg ON s.id = tg.story_id
                LEFT JOIN `tags` t ON tg.tag_id = t.id";

        $conditions = $this->buildFeedConditions($tagslug, $domain, $excludeTagIds, $author, $mutedUserIds, $showDeleted);
        $sql .= $conditions['sql'];
        $bindings = $conditions['bindings'];

        $orderBy = match ($sort) {
            'new' => 's.created_at DESC',
            'top' => 's.score DESC, s.created_at DESC',
            default => 's.hotness DESC',
        };

        $sql .= " GROUP BY s.id ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";

        $bindings[':limit'] = $limit;
        $bindings[':offset'] = $offset;

        $stories = $this->db->fetchAll($sql, $bindings);

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

        $tagMods = $this->getTagHotnessMods($storyId);

        // Используем сервис вместо глобальной функции
        $hotness = $this->rankingService->calculateHotness(
            (int)$story['score'], 
            $story['created_at'], 
            $tagMods
        );

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
            string $author = '',
            array $mutedUserIds = []
        ): int
    {
        $sql = "SELECT COUNT(DISTINCT s.id) FROM `stories` s
                JOIN `users` u ON s.user_id = u.id 
                LEFT JOIN `taggings` tg ON s.id = tg.story_id
                LEFT JOIN `tags` t ON tg.tag_id = t.id";

        // showDeleted по умолчанию false, что совпадает с изначальной логикой getTotalCount
        $conditions = $this->buildFeedConditions($tagslug, $domain, $excludeTagIds, $author, $mutedUserIds);
        $sql .= $conditions['sql'];
        
        return (int)$this->db->fetchColumn($sql, $conditions['bindings']);
    }

    /**
     * Получить одну конкретную историю с именем автора и списком тегов
     */
    public function getSingleWithAuthor(int $id, bool $showDeleted = false): ?array
    {
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
            parseTagsCombined($story);
            return $story;
        }
        return null;
    }

    /**
     * Получить комментарии для истории с фильтрацией игнорируемых и сортировкой
     */
    public function getCommentsForStory(int $storyId, array $mutedUserIds = []): array
    {
        $sql = "SELECT 
                    c.*,
                    u.username as author_name,
                    up.avatar as author_avatar,
                    CASE 
                        WHEN c.confidence_score > 0 THEN c.confidence_score
                        ELSE 0
                    END as calculated_confidence
                FROM comments c
                JOIN users u ON c.user_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id
                WHERE c.story_id = :story_id";
        
        $params = ['story_id' => $storyId];
        
        if (!empty($mutedUserIds)) {
            $placeholders = [];
            foreach ($mutedUserIds as $index => $mutedId) {
                $key = 'muted_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = (int)$mutedId;
            }
            $sql .= " AND c.user_id NOT IN (" . implode(',', $placeholders) . ")";
        }
        
        $sql .= " ORDER BY c.parent_id ASC, calculated_confidence DESC, c.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
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
     */
    public function syncTags(int $storyId, array $tagIds): bool
    {
        $tagIds = array_unique(array_map('intval', $tagIds));

        if (empty($tagIds)) {
            try {
                return $this->db->execute("DELETE FROM `taggings` WHERE `story_id` = ?", [$storyId]) > 0;
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->error("Failed to clear tags: " . $e->getMessage());
                }
                return false;
            }
        }

        try {
            $this->db->beginTransaction();

            $this->db->execute("DELETE FROM `taggings` WHERE `story_id` = ?", [$storyId]);

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