<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Models\ReadRibbon;
use App\Modules\Origins\Models\Domain;
use App\Modules\Tags\Models\TagFilter;
use App\Modules\Auth\Services\Auth;

/**
 * Сервис для фильтрации и получения списков историй.
 */
class StoryFilterService
{
    private Story $storyModel;
    private Domain $domainModel;

    public function __construct(Story $storyModel, Domain $domainModel)
    {
        $this->storyModel = $storyModel;
        $this->domainModel = $domainModel;
    }

    /**
     * Получает ленту историй с учётом всех фильтров.
     *
     * @param int $perPage Количество на страницу
     * @param int $offset Смещение
     * @param string $tagname Фильтр по тегу
     * @param string $domain Фильтр по домену
     * @return array Массив историй
     */
    public function getFilteredStories(int $perPage, int $offset, string $tagname = '', string $domain = '', string $sort = 'hot'): array
    {
        $showDeleted = Auth::isAdmin();
        $excludeTagIds = $this->getUserExcludedTags();

        return $this->storyModel->getFeed($perPage, $offset, $tagname, $showDeleted, $domain, $excludeTagIds, $sort);
    }

    /**
     * Получает общее количество историй с учётом фильтров.
     */
    public function getTotalCount(string $tagname = '', string $domain = ''): int
    {
        $excludeTagIds = $this->getUserExcludedTags();
        return $this->storyModel->getTotalCount($tagname, $domain, $excludeTagIds);
    }


    /**
     * Получает комментарии для истории в виде дерева с сортировкой по Вильсону
     */
    public function getCommentsTree(int $storyId): array
    {
        $flatComments = $this->storyModel->getCommentsForStory($storyId);

        // Добавляем confidence_score к каждому комментарию, если его нет
        foreach ($flatComments as &$comment) {
            if (!isset($comment['confidence_score']) || $comment['confidence_score'] == 0) {
                $comment['confidence_score'] = wilson_score(
                    (int)$comment['score'],
                    (int)$comment['flag_count']
                );
            }
        }
        unset($comment);

        // Строим дерево комментариев
        $commentsTree = [];
        foreach ($flatComments as $comment) {
            $parentId = $comment['parent_id'] ?? 0;
            $commentsTree[$parentId][] = $comment;
        }

        // Сортируем дочерние комментарии по confidence_score (убывание)
        foreach ($commentsTree as $parentId => &$children) {
            usort($children, function ($a, $b) {
                $scoreDiff = $b['confidence_score'] <=> $a['confidence_score'];
                if ($scoreDiff !== 0) {
                    return $scoreDiff;
                }
                return strtotime($b['created_at']) <=> strtotime($a['created_at']);
            });
        }
        unset($children);

        return $commentsTree;
    }

    /**
     * Получает список забаненных доменов (в нижнем регистре).
     *
     * @return array Массив забаненных доменов
     */
    public function getBannedDomains(): array
    {
        $bannedDomains = $this->domainModel->getBannedDomains();
        $domains = array_column($bannedDomains, 'domain');
        return array_map('strtolower', $domains);
    }

    /**
     * Получает ID тегов, которые пользователь исключил из ленты.
     *
     * @return array Массив ID тегов
     */
    public function getUserExcludedTags(): array
    {
        if (!Auth::check()) {
            return [];
        }

        $filterModel = new TagFilter();
        return $filterModel->getFilteredTagIds(Auth::id());
    }

    /**
     * Получает количество новых комментариев для списка историй.
     *
     * @param array $storyIds Массив ID историй
     * @return array Массив [story_id => count]
     */
    public function getNewCommentsCounts(array $storyIds): array
    {
        if (!Auth::check() || empty($storyIds)) {
            return [];
        }

        $readRibbon = new ReadRibbon();
        return $readRibbon->getNewCommentsCounts(Auth::id(), array_map('intval', $storyIds));
    }

    /**
     * Получает одну историю с информацией об авторе.
     */
    public function getStoryWithAuthor(int $storyId): ?array
    {
        $showDeleted = Auth::isAdmin();
        return $this->storyModel->getSingleWithAuthor($storyId, $showDeleted);
    }
}
