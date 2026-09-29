<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use W3a\Core\Database\Database;
use App\Modules\Stories\Models\StoryView;
use App\Modules\Subscriptions\Services\SubscriptionService;
use App\Modules\Tags\Models\TagFilter;

class RecommendationService
{
    private Database $db;
    private StoryView $storyView;
    private SubscriptionService $subscriptionService;
    private TagFilter $tagFilter;
    private TagAttachmentService $tagAttachment;

    public function __construct(
        Database $db,
        StoryView $storyView,
        SubscriptionService $subscriptionService,
        TagFilter $tagFilter,
        TagAttachmentService $tagAttachment
    ) {
        $this->db = $db;
        $this->storyView = $storyView;
        $this->subscriptionService = $subscriptionService;
        $this->tagFilter = $tagFilter;
        $this->tagAttachment = $tagAttachment;
    }

    public function getForYouFeed(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) {
            return $this->getPopularFallback($limit);
        }

        $followedUserIds = $this->subscriptionService->getFollowedUserIds($userId);
        $followedTagIds = $this->subscriptionService->getFollowedTagIds($userId);
        $topTags = $this->storyView->getUserTopTags($userId, 5);
        $viewedStoryIds = $this->storyView->getViewedStoryIds($userId, 50);
        $excludedTagIds = $this->tagFilter->getFilteredTagIds($userId);

        if (empty($followedUserIds) && empty($followedTagIds) && empty($topTags)) {
            return $this->getPopularFallback($limit, [], $excludedTagIds, $userId);
        }

        $readTagIds = array_column($topTags, 'tag_id');
        $allTagIds = array_unique(array_merge($followedTagIds, $readTagIds));

        $stories = $this->getRecommendedStories(
            $userId,
            $followedUserIds,
            $allTagIds,
            $viewedStoryIds,
            $limit,
            $excludedTagIds
        );

        if (count($stories) < $limit) {
            $excludeIds = array_column($stories, 'id');
            $popular = $this->getPopularFallback($limit - count($stories), $excludeIds, $excludedTagIds, $userId);
            $stories = array_merge($stories, $popular);
        }

        return $stories;
    }

    /**
     * Похожие статьи: сначала с общими тегами (чем больше пересечений — тем релевантнее),
     * при нехватке добиваем популярными за неделю.
     */
    public function getSimilarStories(int $storyId, int $limit = 6): array
    {
        $stories = $this->getStoriesBySharedTags($storyId, $limit);

        if (count($stories) < $limit) {
            $excludeIds = array_merge([$storyId], array_column($stories, 'id'));
            $excludeIds = array_values(array_unique(array_map('intval', $excludeIds)));
            $popular = $this->getPopularFallback($limit - count($stories), $excludeIds);
            $stories = array_merge($stories, $popular);
        }

        return $stories;
    }

    private function getStoriesBySharedTags(int $storyId, int $limit): array
    {
        $tagStmt = $this->db->query(
            "SELECT `tag_id` FROM `taggings` WHERE `story_id` = ?",
            [$storyId]
        );
        $tagIds = array_map('intval', array_column($tagStmt->fetchAll(\PDO::FETCH_ASSOC), 'tag_id'));

        if (empty($tagIds)) {
            return [];
        }

        $tagPlaceholders = implode(',', array_fill(0, count($tagIds), '?'));
        $stmt = $this->db->query(
            "SELECT
                s.*,
                u.username AS author_name,
                up.avatar AS author_avatar,
                (SELECT COUNT(DISTINCT tc.tag_id)
                   FROM `taggings` tc
                  WHERE tc.story_id = s.id
                    AND tc.tag_id IN ($tagPlaceholders)
                ) AS shared_tags
             FROM `stories` s
             JOIN `users` u ON s.user_id = u.id
             LEFT JOIN `user_profiles` up ON u.id = up.user_id
             WHERE s.id != ?
               AND s.status = 'published'
               AND s.deleted_at IS NULL
               AND EXISTS (
                   SELECT 1 FROM `taggings` te
                   WHERE te.story_id = s.id AND te.tag_id IN ($tagPlaceholders)
               )
             ORDER BY shared_tags DESC, s.hotness DESC, s.created_at DESC
             LIMIT " . (int)$limit,
            array_merge($tagIds, [$storyId], $tagIds)
        );

        $stories = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $this->tagAttachment->attach($stories);
    }

    private function getRecommendedStories(
        int $userId,
        array $followedUserIds,
        array $tagIds,
        array $excludeStoryIds,
        int $limit,
        array $excludedTagIds = []
    ): array {
        $where = ['s.deleted_at IS NULL', "s.status = 'published'"];
        $bindings = [];

        $where[] = 's.user_id != ?';
        $bindings[] = $userId;

        if (!empty($excludeStoryIds)) {
            $excludeStoryIds = array_map('intval', $excludeStoryIds);
            $placeholders = implode(',', array_fill(0, count($excludeStoryIds), '?'));
            $where[] = "s.id NOT IN ($placeholders)";
            $bindings = array_merge($bindings, $excludeStoryIds);
        }

        if (!empty($excludedTagIds)) {
            $excludedTagIds = array_map('intval', $excludedTagIds);
            $placeholders = implode(',', array_fill(0, count($excludedTagIds), '?'));
            $where[] = "NOT EXISTS (
                SELECT 1 FROM taggings tg_ex
                WHERE tg_ex.story_id = s.id AND tg_ex.tag_id IN ($placeholders)
            )";
            $bindings = array_merge($bindings, $excludedTagIds);
        }

        $interestConditions = [];

        if (!empty($followedUserIds)) {
            $userPlaceholders = implode(',', array_fill(0, count($followedUserIds), '?'));
            $interestConditions[] = "s.user_id IN ($userPlaceholders)";
            $bindings = array_merge($bindings, $followedUserIds);
        }

        if (!empty($tagIds)) {
            $tagPlaceholders = implode(',', array_fill(0, count($tagIds), '?'));
            $interestConditions[] = "EXISTS (
                SELECT 1 FROM taggings tg 
                WHERE tg.story_id = s.id AND tg.tag_id IN ($tagPlaceholders)
            )";
            $bindings = array_merge($bindings, $tagIds);
        }

        if (!empty($interestConditions)) {
            $where[] = '(' . implode(' OR ', $interestConditions) . ')';
        }

        $whereClause = implode(' AND ', $where);
        $bindings[] = $limit;

        $sql = "
            SELECT 
                s.*,
                u.username as author_name,
                up.avatar as author_avatar
            FROM `stories` s
            JOIN `users` u ON s.user_id = u.id
            LEFT JOIN `user_profiles` up ON u.id = up.user_id
            WHERE $whereClause
            ORDER BY s.hotness DESC, s.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->db->query($sql, $bindings);
        $stories = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $this->tagAttachment->attach($stories);
    }

    private function getPopularFallback(
        int $limit,
        array $excludeIds = [],
        array $excludedTagIds = [],
        int $userId = 0
    ): array {
        $where = [
            's.deleted_at IS NULL',
            "s.status = 'published'",
            's.created_at >= NOW() - INTERVAL 7 DAY'
        ];
        $bindings = [];

        if ($userId > 0) {
            $where[] = 's.user_id != ?';
            $bindings[] = $userId;
        }

        if (!empty($excludeIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $where[] = "s.id NOT IN ($placeholders)";
            $bindings = array_merge($bindings, $excludeIds);
        }

        if (!empty($excludedTagIds)) {
            $excludedTagIds = array_map('intval', $excludedTagIds);
            $placeholders = implode(',', array_fill(0, count($excludedTagIds), '?'));
            $where[] = "NOT EXISTS (
                SELECT 1 FROM taggings tg_ex
                WHERE tg_ex.story_id = s.id AND tg_ex.tag_id IN ($placeholders)
            )";
            $bindings = array_merge($bindings, $excludedTagIds);
        }

        $whereClause = implode(' AND ', $where);
        $bindings[] = $limit;

        $sql = "
            SELECT 
                s.*,
                u.username as author_name,
                up.avatar as author_avatar
            FROM `stories` s
            JOIN `users` u ON s.user_id = u.id
            LEFT JOIN `user_profiles` up ON u.id = up.user_id
            WHERE $whereClause
            ORDER BY s.hotness DESC
            LIMIT ?
        ";

        $stmt = $this->db->query($sql, $bindings);
        $stories = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $this->tagAttachment->attach($stories);
    }
}