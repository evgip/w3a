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
use App\Modules\Stories\Services\StoryPageService;
use App\Modules\Stories\Models\Story;
use App\Modules\Tags\Services\TagFilterService;
use App\Modules\Tags\Models\Tag;
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

        $userContext = $this->getUserContext();

        $currentVotes = [];
        if ($userContext['isLoggedIn']) {
            $voteModel = $this->container->get(Vote::class);
            $currentVotes = $voteModel->getUserVotesForStories($userContext['id'], $storyIds);
        }

        $pageData = $this->buildIndexPageData($tagslug, $domain);

        $rssFeed = [
            'title' => 'Новые истории',
            'url' => '/rss',
        ];

        if ($tagslug) {
            $rssFeed = [
                'title' => 'Тег #' . e($pageData['tagInfo']['name'] ?? $tagslug),
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
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'canUserDownvote' => $this->canUserDownvote($userContext['id']),
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
        $userContext = $this->getUserContext();

        // ✅ Используем StoryPageService для сборки всех данных страницы
        $pageService = $this->service(StoryPageService::class);
        $pageData = $pageService->buildShowPageData($storyId, $userContext);

        // Устанавливаем OpenGraph мета-теги
        $this->setOpenGraph([
            'type' => 'article',
            'title' => $pageData['ogData']['title'],
            'description' => $pageData['ogData']['description'],
            'image' => $pageData['ogData']['image'],
            'article:author' => $pageData['ogData']['author_url'],
        ]);

        // Рендерим шаблон
        $this->render('show', [
            'title' => $pageData['story']['title'],
            'story' => $pageData['story'],
            'commentsTree' => $pageData['commentsTree'],
            'newCount' => $pageData['newCount'],
            'lastReadCommentId' => $pageData['lastReadCommentId'],
            'activeSuggestions' => $pageData['activeSuggestions'],
            'changeLog' => $pageData['changeLog'],
            'allTags' => $pageData['allTags'],
            'currentTagIds' => $pageData['currentTagIds'],
            'currentUserId' => $pageData['currentUserId'],
            'isAdmin' => $pageData['isAdmin'],
            'isModerator' => $pageData['isModerator'],
            'isAuthor' => $pageData['isAuthor'],
            'canUserDownvote' => $this->canUserDownvote($userContext['id']),
            'currentStoryVote' => $pageData['currentStoryVote'],
            'currentCommentVotes' => $pageData['currentCommentVotes'],
            'userSuggestionsCount' => $pageData['userSuggestionsCount'],
            'isStorySaved' => $pageData['isStorySaved'],
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

        $userContext = $this->getUserContext();
        $storyId = $this->service(StoryService::class)->createStory($data, $userContext['id']);

        if ($storyId > 0) {
            $this->container->get(Session::class)->flash('success', 'Ваша история успешно опубликована!');
            $this->redirectBack('/');
        }

        $this->redirect('/story/' . $storyId);
    }

    // =========================================================================
    // РЕДАКТИРОВАНИЕ ИСТОРИИ
    // =========================================================================

    public function showEditForm(string $id): void
    {
        $storyId = (int)$id;

        $storyModel = $this->container->get(Story::class);
        $story = $storyModel->find($storyId);

        $userContext = $this->getUserContext();

        if (!$story || !$this->service(StoryService::class)->canEditStory($story, $userContext['id'])) {
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

        $userContext = $this->getUserContext();

        if (!$story || !$this->service(StoryService::class)->canEditStory($story, $userContext['id'])) {
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

        $this->redirect('/story/' . $storyId);
    }

    // =========================================================================
    // АДМИНИСТРИРОВАНИЕ ИСТОРИЙ
    // =========================================================================

    public function adminDelete(string $id): void
    {
        $userContext = $this->getUserContext();
        $this->service(StoryService::class)->deleteStory((int)$id, $userContext['id']);
        $this->redirectBack();
    }

    public function adminRestore(string $id): void
    {
        $userContext = $this->getUserContext();
        $this->service(StoryService::class)->restoreStory((int)$id, $userContext['id']);
        $this->redirectBack();
    }

    // =========================================================================
    // ПОДПИСКА И ПРОЧТЕНИЕ
    // =========================================================================

    public function toggleFollow(string $id): void
    {
        $storyId = (int)$id;

        $userContext = $this->getUserContext();

        $storyModel = $this->container->get(Story::class);
        $storyModel->toggleFollow($storyId, $userContext['id']);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $isFollowing = $storyModel->isFollowing($storyId, $userContext['id']);
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

    public function userStories(string $username): void
    {
        $validator = $this->container->get(\App\Core\Validator::class);
        $validator->validate(
            ['username' => $username],
            ['username' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/']
        );

        if (!$validator->isValid()) {
            throw new \App\Core\Exceptions\NotFoundException("Пользователь не найден");
        }

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

        $userContext = $this->getUserContext();

        $currentVotes = [];
        if ($userContext['isLoggedIn']) {
            $voteModel = $this->container->get(Vote::class);
            $currentVotes = $voteModel->getUserVotesForStories($userContext['id'], $storyIds);
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
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'canUserDownvote' => $this->canUserDownvote($userContext['id']),
            'currentVotes' => $currentVotes,
            'title' => 'Публикации пользователя ' . e($username),
            'rssFeed' => [
                'title' => 'Публикации ' . e($username),
                'url' => '/u/' . e($username) . '/rss',
            ],
        ]);
    }
}
