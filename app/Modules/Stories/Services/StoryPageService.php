<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use App\Core\Container;
use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Models\ReadRibbon;
use App\Modules\Tags\Models\Tag;
use App\Modules\Votes\Models\Vote;
use App\Modules\Suggestions\Services\SuggestionService;
use App\Modules\Saved\Models\SavedStory;
use App\Core\Exceptions\NotFoundException;

/**
 * Сервис для сборки данных страницы просмотра истории.
 * Объединяет множество запросов к БД и сервисам в один orchestration-слой.
 */
class StoryPageService
{
    private Container $container;
    private StoryFilterService $storyFilterService;
    private ReadRibbonService $readRibbonService;
    private SuggestionService $suggestionService;

    public function __construct(
        Container $container,
        StoryFilterService $storyFilterService,
        ReadRibbonService $readRibbonService,
        SuggestionService $suggestionService
    ) {
        $this->container = $container;
        $this->storyFilterService = $storyFilterService;
        $this->readRibbonService = $readRibbonService;
        $this->suggestionService = $suggestionService;
    }

    /**
     * Собирает все данные для страницы просмотра истории.
     *
     * @param int $storyId ID истории
     * @param array $userContext Контекст пользователя (id, isLoggedIn, isAdmin, isModerator, isAuthor)
     * @return array Массив данных для рендеринга страницы
     * @throws NotFoundException Если история не найдена
     */
    public function buildShowPageData(int $storyId, array $userContext): array
    {
        // 1. Получаем историю
        $story = $this->storyFilterService->getStoryWithAuthor($storyId);
        if (!$story) {
            throw new NotFoundException("История не найдена.");
        }

        // 2. Получаем дерево комментариев
        $commentsTree = $this->storyFilterService->getCommentsTree($storyId);

        // 3. Read Ribbon (лента прочтения)
        $readRibbonModel = $this->container->get(ReadRibbon::class);
        $ribbonData = $readRibbonModel->getForStories($userContext['id'], [$storyId]);
        $lastReadCommentId = $ribbonData[$storyId] ?? 0;

        // Обновляем счетчик новых комментариев
        $newCount = $this->readRibbonService->handleStoryView($storyId);

        // 4. Suggestions (предложения по улучшению)
        $activeSuggestions = $this->suggestionService->getActiveSuggestions('Story', $storyId);
        $changeLog = $this->suggestionService->getChangeLog('Story', $storyId, 10);

        // 5. Теги
        $tagModel = $this->container->get(Tag::class);
        $allTags = $tagModel->getAllTags();

        $storyModel = $this->container->get(Story::class);
        $currentTagIds = $storyModel->getStoryTagIds($storyId);

        // 6. Данные, зависящие от авторизации
        $currentStoryVote = null;
        $currentCommentVotes = [];
        $userSuggestionsCount = 0;
        $isAuthor = false;
        $isStorySaved = false;

        if ($userContext['isLoggedIn']) {
            $userId = $userContext['id'];
            $voteModel = $this->container->get(Vote::class);

            // Получаем голос пользователя за историю
            $currentStoryVote = $voteModel->getUserVote($userId, 'story', $storyId);

            // Собираем ID всех комментариев для получения голосов
            $allCommentIds = [];
            foreach ($commentsTree as $parentId => $comments) {
                foreach ($comments as $comment) {
                    $allCommentIds[] = (int)$comment['id'];
                }
            }

            // Batch-запрос голосов за комментарии
            if (!empty($allCommentIds)) {
                $currentCommentVotes = $voteModel->getUserVotesForComments($userId, $allCommentIds);
            }

            // Проверяем, является ли пользователь автором истории
            $isAuthor = $userContext['isAuthor']((int)$story['user_id']);

            // Получаем количество активных предложений от пользователя (если не модератор/админ)
            if (!$userContext['isModerator'] && !$userContext['isAdmin']) {
                $userSuggestionsCount = $this->suggestionService->getUserActiveSuggestionsCount('Story', $storyId, $userId);
            }

            // Проверяем, сохранена ли история
            $savedModel = $this->container->get(SavedStory::class);
            $isStorySaved = $savedModel->isSaved($userId, $storyId);
        }

        // 7. OpenGraph данные
        $ogData = $this->storyFilterService->getStoryOpenGraphData($story);

        return [
            'story' => $story,
            'commentsTree' => $commentsTree,
            'newCount' => $newCount,
            'lastReadCommentId' => $lastReadCommentId,
            'activeSuggestions' => $activeSuggestions,
            'changeLog' => $changeLog,
            'allTags' => $allTags,
            'currentTagIds' => $currentTagIds,
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'isModerator' => $userContext['isModerator'],
            'isAuthor' => $isAuthor,
            'currentStoryVote' => $currentStoryVote,
            'currentCommentVotes' => $currentCommentVotes,
            'userSuggestionsCount' => $userSuggestionsCount,
            'isStorySaved' => $isStorySaved,
            'ogData' => $ogData,
        ];
    }
}
