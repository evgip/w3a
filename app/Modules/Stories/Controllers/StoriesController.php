<?php

declare(strict_types=1);

namespace App\Modules\Stories\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Exceptions\NotFoundException;
use App\Modules\Stories\Services\StoryService;
use App\Modules\Stories\Services\StoryFilterService;
use App\Modules\Stories\Services\ReadRibbonService;
use App\Modules\Stories\Services\UrlFetcherService;
use App\Modules\Stories\Models\Story;
use App\Modules\Tags\Services\TagFilterService;
use App\Modules\Tags\Models\Tag;
use App\Modules\Auth\Services\Auth;
use App\Modules\Votes\Models\Vote;
use App\Modules\Users\Models\User;
use App\Modules\Content\Core\Markdown;
use App\Modules\Wiki\Services\WikiService;
use App\Modules\Suggestions\Services\SuggestionService;

class StoriesController extends Controller
{
    // =========================================================================
    // ЛЕНТА ИСТОРИЙ
    // =========================================================================

    public function index(string $tagslug = '', string $domain = ''): void
    {
        $currentPage = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.stories_per_page', 15, 'int');
        $offset = ($currentPage - 1) * $perPage;

        $sort = $this->request->getParams('sort', 'hot');
        if (!in_array($sort, ['hot', 'new', 'top'], true)) {
            $sort = 'hot';
        }

        $filterService = $this->service(StoryFilterService::class);
        $stories = $filterService->getFilteredStories($perPage, $offset, $tagslug, $domain, $sort);
        $totalStories = $filterService->getTotalCount($tagslug, $domain);
        $totalPages = (int)ceil($totalStories / $perPage);

        $bannedDomainsCache = $filterService->getBannedDomains();
        $storyIds = array_column($stories, 'id');
        $newCommentsMap = $filterService->getNewCommentsCounts($storyIds);

        // Получаем данные для голосования через общий метод
        $currentUserId = Auth::check() ? Auth::id() : 0;
        $votingContext = $this->getVotingContext($currentUserId);

        // Получаем голоса для всех историй
        $currentVotes = [];
        if ($currentUserId > 0) {
            $voteModel = $this->container->get(Vote::class);
            foreach ($storyIds as $storyId) {
                $currentVotes[$storyId] = $voteModel->getUserVote($currentUserId, 'story', (int)$storyId);
            }
        }

        // Формируем заголовок и OG-данные
        $pageData = $this->buildIndexPageData($tagslug, $domain);

        $rssFeed = [
            'title' => 'Новые истории',
            'url' => '/rss',
        ];

        // Если есть фильтр по тегу
        if ($tagslug) {
            $rssFeed = [
                'title' => 'Тег #' . e($tagInfo['name'] ?? $tagslug),
                'url' => '/t/' . e($tagslug) . '/rss',
            ];
        }

        $this->render('index', array_merge([
            'stories' => $stories,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'newCommentsMap' => $newCommentsMap,
            'bannedDomainsCache' => $bannedDomainsCache,
            'sort' => $sort,
            'domain' => $domain,
            'currentUserId' => $currentUserId,
            'isAdmin' => Auth::isAdmin(),
            'canUserDownvote' => $votingContext['canDownvote'],
            'currentVotes' => $currentVotes,
            'rssFeed' => $rssFeed,
        ], $pageData));
    }

    // =========================================================================
    // ПРОСМОТР ОДНОЙ ИСТОРИИ
    // =========================================================================

    public function show(string $id): void
    {
        $storyId = (int)$id;

        $story = $this->service(StoryFilterService::class)->getStoryWithAuthor($storyId);

        if (!$story) {
            throw new NotFoundException("История не найдена.");
        }

        $commentsTree = $this->service(StoryFilterService::class)->getCommentsTree($storyId);

        // Получаем ТЕКУЩУЮ отметку прочтения (до обновления)
        $currentUserId = Auth::check() ? Auth::id() : 0;
        $readRibbonModel = $this->container->get(\App\Modules\Stories\Models\ReadRibbon::class);
        $ribbonData = $readRibbonModel->getForStories($currentUserId, [$storyId]);
        $lastReadCommentId = $ribbonData[$storyId] ?? 0;

        // Теперь обновляем ленту (сдвигает отметку до последнего комментария)
        $newCount = $this->service(ReadRibbonService::class)->handleStoryView($storyId);

        $suggestionService = $this->service(SuggestionService::class);
        $activeSuggestions = $suggestionService->getActiveSuggestions('Story', $storyId);
        $changeLog = $suggestionService->getChangeLog('Story', $storyId, 10);

        $tagModel = $this->container->get(Tag::class);
        $allTags = $tagModel->getAllTags();

        $storyModel = $this->container->get(Story::class);
        $currentTagIds = $storyModel->getStoryTagIds($storyId);

        // Получаем данные для голосования через общий метод
        $currentUserId = Auth::check() ? Auth::id() : 0;
        $votingContext = $this->getVotingContext($currentUserId);

        // Получаем голос за историю и комментарии
        $currentStoryVote = null;
        $currentCommentVotes = [];
        $userSuggestionsCount = 0;

        if ($currentUserId > 0) {
            $voteModel = $this->container->get(Vote::class);
            $currentStoryVote = $voteModel->getUserVote($currentUserId, 'story', $storyId);

            foreach ($commentsTree as $parentId => $comments) {
                foreach ($comments as $comment) {
                    $commentId = (int)$comment['id'];
                    $currentCommentVotes[$commentId] = $voteModel->getUserVote($currentUserId, 'comment', $commentId);
                }
            }

            $isAdmin = Auth::isAdmin();
            $isModerator = Auth::isModerator();
            if (!$isModerator && !$isAdmin) {
                $userSuggestionsCount = $suggestionService->getUserActiveSuggestionsCount('Story', $storyId, $currentUserId);
            }
        }

        // OG-данные
        $ogData = $this->service(StoryFilterService::class)->getStoryOpenGraphData($storyId);
        $this->setOpenGraph([
            'type' => 'article',
            'title' => $ogData['title'],
            'description' => $ogData['description'],
            'image' => $ogData['image'],
            'article:author' => $ogData['author_url'],
        ]);

        $savedModel = $this->container->get(\App\Modules\Saved\Models\SavedStory::class);
        $isStorySaved = $currentUserId > 0 ? $savedModel->isSaved($currentUserId, $storyId) : false;


        $this->render('show', [
            'title' => $story['title'],
            'story' => $story,
            'commentsTree' => $commentsTree,
            'newCount' => $newCount,
            'lastReadCommentId' => $lastReadCommentId,
            'activeSuggestions' => $activeSuggestions,
            'changeLog' => $changeLog,
            'allTags' => $allTags,
            'currentTagIds' => $currentTagIds,
            'currentUserId' => $currentUserId,
            'isAdmin' => Auth::isAdmin(),
            'isModerator' => Auth::isModerator(),
            'isAuthor' => $currentUserId > 0 && (int)$story['user_id'] === $currentUserId,
            'canUserDownvote' => $votingContext['canDownvote'],
            'currentStoryVote' => $currentStoryVote,
            'currentCommentVotes' => $currentCommentVotes,
            'userSuggestionsCount' => $userSuggestionsCount,
            'isStorySaved' => $isStorySaved,
        ]);
    }

    // =========================================================================
    // СОЗДАНИЕ ИСТОРИИ
    // =========================================================================

    public function showCreateForm(): void
    {
        $tagModel = $this->container->get(Tag::class);
        $availableTags = $tagModel->getAllTags(false);

        $this->render('create', [
            'title' => 'Поделиться интересным',
            'availableTags' => $availableTags,
            'request' => $this->request
        ]);
    }

    public function create(): void
    {
        $user_is_following = is_numeric($this->request->getParams('user_is_following'));

        $data = [
            'title' => $this->request->getParams('title'),
            'url' => $this->request->getParams('url') ?: null,
            'description' => $this->request->getParams('description') ?: null,
            'tags' => $this->request->getParams('tags') ?? [],
            'user_is_following' => $user_is_following ? 1 : 0,
        ];

        $userId = Auth::id();
        $storyId = $this->service(StoryService::class)->createStory($data, $userId);

        if ($storyId > 0) {
            $this->container->get(Session::class)->flash('success', 'Ваша история успешно опубликована!');
            $this->redirectBack('/');
        }

        $this->redirectBack('/story/' . $storyId);
    }

    // =========================================================================
    // РЕДАКТИРОВАНИЕ ИСТОРИИ
    // =========================================================================

    public function showEditForm(string $id): void
    {
        $storyId = (int)$id;

        $storyModel = $this->container->get(Story::class);
        $story = $storyModel->find($storyId);

        $userId = Auth::id();
        if (!$story || !$this->service(StoryService::class)->canEditStory($story, $userId)) {
            $this->container->get(Session::class)->flash('error', 'У вас нет прав для изменения этой публикации.');
            $this->redirectBack('/');
            return;
        }

        $tagModel = $this->container->get(Tag::class);

        $this->render('edit', [
            'title' => 'Редактирование публикации',
            'story' => $story,
            'availableTags' => $tagModel->getAllTags(),
            'activeTagIds' => $storyModel->getStoryTagIds($storyId),
            'request' => $this->request
        ]);
    }

    public function update(string $id): void
    {
        $storyId = (int)$id;

        $storyModel = $this->container->get(Story::class);
        $story = $storyModel->find($storyId);
        $userId = Auth::id();

        if (!$story || !$this->service(StoryService::class)->canEditStory($story, $userId)) {
            $this->redirectBack('/');
            return;
        }

        $data = [
            'title' => $this->request->getParams('title'),
            'url' => $this->request->getParams('url') ?: null,
            'description' => $this->request->getParams('description') ?: null,
            'tags' => $this->request->post('tags', []),
            'user_is_following' => $this->request->post('user_is_following') !== null ? 1 : 0,
        ];

        $this->service(StoryService::class)->updateStory($storyId, $data);

        $this->container->get(Session::class)->flash('success', 'Публикация успешно отредактирована.');

        $this->redirectBack('/story/' . $storyId);
    }

    // =========================================================================
    // АДМИНИСТРИРОВАНИЕ ИСТОРИЙ
    // =========================================================================

    public function adminDelete(string $id): void
    {
        $this->service(StoryService::class)->deleteStory((int)$id, Auth::id());
        $this->redirectBack();
    }

    public function adminRestore(string $id): void
    {
        $this->service(StoryService::class)->restoreStory((int)$id, Auth::id());
        $this->redirectBack();
    }


    // =========================================================================
    // ПОДПИСКА И ПРОЧТЕНИЕ
    // =========================================================================

    public function toggleFollow(string $id): void
    {
        $storyId = (int)$id;
        $userId = Auth::id();

        $storyModel = $this->container->get(Story::class);
        $storyModel->toggleFollow($storyId, $userId);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $isFollowing = $storyModel->isFollowing($storyId, $userId);
            $this->json([
                'success' => true,
                'is_following' => $isFollowing,
            ]);
            return;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '/story/' . $storyId;
        $this->redirectBack($referer);
    }

    public function markRead(string $id): void
    {
        $storyId = (int)$id;
        $this->service(ReadRibbonService::class)->markAsRead($storyId);

        $referer = $_SERVER['HTTP_REFERER'] ?? '/story/' . $storyId;
        $this->redirectBack($referer);
    }

    // =========================================================================
    // AJAX ENDPOINTS
    // =========================================================================

    /**
     * AJAX endpoint для извлечения заголовка из URL
     */
    public function fetchUrlTitle(): void
    {
        $url = $this->request->getParams('url');

        if (empty($url)) {
            $this->json(['title' => '', 'url' => '']);
            return;
        }

        $fetcher = $this->container->get(UrlFetcherService::class);
        $attributes = $fetcher->fetchAttributes($url);

        $this->json($attributes);
    }

    /**
     * AJAX endpoint для предпросмотра Markdown
     */
    public function preview(): void
    {
        if (!$this->request->isCsrfValid()) {
            $this->json(['error' => 'Неверный CSRF токен'], 419);
            return;
        }

        $text = $this->request->post('text', '');
        $allowImages = (bool)$this->request->post('allow_images', true);

        $markdown = $this->container->get(Markdown::class);
        $html = $markdown->parse($text, $allowImages);

        $this->json([
            'html' => $html,
            'success' => true
        ]);
    }
    
    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Общий метод для получения контекста голосования
     * Устраняет дублирование между index() и show()
     */
    private function getVotingContext(int $userId): array
    {
        if ($userId === 0) {
            return [
                'canDownvote' => false,
                'karma' => 0,
            ];
        }

        $userModel = $this->container->get(User::class);
        $karma = $userModel->getUserKarma($userId);
        $minKarma = (int)config('config.app.min_karma_for_downvote', 10);

        return [
            'canDownvote' => $karma >= $minKarma,
            'karma' => $karma,
        ];
    }

    /**
     * Формирование данных для index страницы (заголовок, OG, wiki)
     * Устраняет перегруженность метода index()
     */
    private function buildIndexPageData(string $tagslug, string $domain): array
    {
        $data = [
            'title' => 'Лента историй',
            'tagInfo' => '',
            'wikiPages' => false,
            'primaryWikiPage' => false,
            'wikiPagesCount' => false,
        ];

        if ($tagslug) {
            $data['title'] = "Публикации с тегом # " . e($tagslug);

            $tagFilterService = $this->service(TagFilterService::class);
            $ogData = $tagFilterService->getTagOpenGraphData($tagslug);
            $this->setOpenGraph([
                'type' => 'article',
                'title' => $ogData['title'],
                'description' => $ogData['description'],
                'image' => config('config.app.url') . '/',
            ]);

            $data['tagInfo'] = $tagFilterService->getByInfoSlug($tagslug);

            if (!empty($data['tagInfo']['id'])) {
                $wikiService = $this->service(WikiService::class);
                $wikiPages = $wikiService->getPagesForTag($data['tagInfo']['id']);
                $data['wikiPages'] = $wikiPages;
                $data['primaryWikiPage'] = $wikiService->getPrimaryPageForTag($data['tagInfo']['id']);
                $data['wikiPagesCount'] = count($wikiPages);
            }
        } elseif ($domain) {
            $data['title'] = "Публикации с домена " . e($domain);
            $this->setOpenGraph([
                'type' => 'article',
                'title' => $data['title'],
                'description' => null,
                'image' => config('config.app.url') . '/',
            ]);
        }

        return $data;
    }

    private function validateAuthor(string $username): string
    {
        $username = trim($username);

        if ($username === '') {
            return '';
        }

        $validator = $this->container->get(\App\Core\Validator::class);
        $validator->validate(
            ['username' => $username],
            ['username' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/']
        );

        if (!$validator->isValid()) {
            return '';
        }

        $userModel = $this->container->get(User::class);
        $user = $userModel->findByName($username);

        return $user ? $username : '';
    }

    /**
     * Публикации конкретного пользователя
     */
    public function userStories(string $username): void
    {
        // Валидация username
        $validator = $this->container->get(\App\Core\Validator::class);
        $validator->validate(
            ['username' => $username],
            ['username' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/']
        );

        if (!$validator->isValid()) {
            throw new \App\Core\Exceptions\NotFoundException("Пользователь не найден");
        }

        // Проверяем существование пользователя
        $userModel = $this->container->get(\App\Modules\Users\Models\User::class);
        $user = $userModel->findByName($username);

        if (!$user) {
            throw new \App\Core\Exceptions\NotFoundException("Пользователь не найден");
        }

        $currentPage = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.stories_per_page', 15, 'int');
        $offset = ($currentPage - 1) * $perPage;

        $filterService = $this->service(StoryFilterService::class);
        $stories = $filterService->getFilteredStories($perPage, $offset, '', '', 'hot', $username);
        $totalStories = $filterService->getTotalCount('', '', $username);
        $totalPages = (int)ceil($totalStories / $perPage);

        $bannedDomainsCache = $filterService->getBannedDomains();
        $storyIds = array_column($stories, 'id');
        $newCommentsMap = $filterService->getNewCommentsCounts($storyIds);

        $currentUserId = Auth::check() ? Auth::id() : 0;
        $votingContext = $this->getVotingContext($currentUserId);

        $currentVotes = [];
        if ($currentUserId > 0) {
            $voteModel = $this->container->get(Vote::class);
            foreach ($storyIds as $storyId) {
                $currentVotes[$storyId] = $voteModel->getUserVote($currentUserId, 'story', (int)$storyId);
            }
        }

        $this->render('index', [
            'stories' => $stories,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'newCommentsMap' => $newCommentsMap,
            'bannedDomainsCache' => $bannedDomainsCache,
            'sort' => 'hot',
            'author' => $username,
            'domain' => '',
            'currentUserId' => $currentUserId,
            'isAdmin' => Auth::isAdmin(),
            'canUserDownvote' => $votingContext['canDownvote'],
            'currentVotes' => $currentVotes,
            'title' => 'Публикации пользователя ' . e($username),
            'rssFeed' => [
                'title' => 'Публикации ' . e($username),
                'url' => '/u/' . e($username) . '/rss',
            ],
        ]);
    }
}
