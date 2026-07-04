<?php

declare(strict_types=1);

namespace App\Modules\Stories\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Modules\Stories\Services\StoryService;
use App\Modules\Stories\Services\StoryFilterService;
use App\Modules\Stories\Services\CommentService;
use App\Modules\Stories\Services\ReadRibbonService;
use App\Modules\Stories\Services\UrlFetcherService;
use App\Modules\Stories\Models\Story;
use App\Modules\Tags\Services\TagFilterService;
use App\Modules\Tags\Models\Tag;
use App\Modules\Auth\Services\Auth;
use App\Modules\Votes\Models\Vote;
use App\Modules\Users\Models\User;

/**
 * Контроллер для работы с историями (публикациями) и комментариями.
 * 
 * Бизнес-логика вынесена в Service-классы:
 * - StoryService: создание, обновление, удаление историй
 * - StoryFilterService: фильтрация и получение списков
 * - CommentService: работа с комментариями
 * - ReadRibbonService: отметки прочитанного
 */
class StoriesController extends Controller
{
    // =========================================================================
    // ЛЕНТА ИСТОРИЙ
    // =========================================================================

    /**
     * Отображение главной ленты историй.
     *
     * @param string $tagslug Фильтр по тегу (опционально)
     * @param string $domain Фильтр по домену (опционально)
     */
    public function index(string $tagslug = '', string $domain = ''): void
    {  
        $currentPage = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.stories_per_page', 15, 'int');
        $offset = ($currentPage - 1) * $perPage;

		// Получаем и фильтруем параметр author из GET-запроса
		$author = $this->validateAuthor($this->request->getParams('author', ''));

        // Читаем режим сортировки
        $sort = $this->request->getParams('sort', 'hot');
        if (!in_array($sort, ['hot', 'new', 'top'], true)) {
            $sort = 'hot';
        }

        // Получаем отфильтрованные истории через сервис
        $stories = $this->service(StoryFilterService::class)->getFilteredStories(
            $perPage,
            $offset,
            $tagslug,
            $domain,
            $sort,
			$author
        );
        $totalStories = $this->service(StoryFilterService::class)->getTotalCount($tagslug, $domain);
        $totalPages = (int)ceil($totalStories / $perPage);

        // Дополнительные данные
        $bannedDomainsCache = $this->service(StoryFilterService::class)->getBannedDomains();
        $storyIds = array_column($stories, 'id');
        $newCommentsMap = $this->service(StoryFilterService::class)->getNewCommentsCounts($storyIds);

        // ✅ Получаем данные для голосования через контейнер
        $currentUserId = Auth::check() ? Auth::id() : 0;
        $isAdmin = Auth::isAdmin();
        $canUserDownvote = false;
        $currentVotes = [];
        
        if ($currentUserId > 0) {
            // Получаем модель User из контейнера
            $userModel = $this->container->get(User::class);
            $viewerKarma = $userModel->getUserKarma($currentUserId);
            $minKarmaForDownvote = config('config.app.min_karma_for_downvote', 10, 'int');
            $canUserDownvote = ($viewerKarma >= $minKarmaForDownvote);
            
            // Получаем голоса для всех историй одним запросом
            $voteModel = $this->container->get(Vote::class);
            foreach ($storyIds as $storyId) {
                $currentVotes[$storyId] = $voteModel->getUserVote($currentUserId, 'story', (int)$storyId);
            }
        }

        // Формируем заголовок страницы
        $title = 'Лента историй';
		$tagInfo = '';
		$wikiPages = false;
		$primaryWikiPage = false;
		$wikiPagesCount = false;
		
        if ($tagslug) {
            $title = "Публикации с тегом # " . e($tagslug);
			
			// Получаем OG-данные из сервиса
			$ogData = $this->service(TagFilterService::class)->getTagOpenGraphData($tagslug);
			$this->setOpenGraph([
				'type' => 'article',
				'title' => $ogData['title'],
				'description' => $ogData['description'],
				'image' => config('config.app.url') . '/',
			]);
			
			// Инфа по тегу для инфы над постами
			$tagInfo =  $this->service(TagFilterService::class)->getByInfoSlug($tagslug);
	
			// Получаем данные о wiki для этого тега
			$wikiService = $this->service(\App\Modules\Wiki\Services\WikiService::class);
			$wikiPages = $wikiService->getPagesForTag($tagInfo['id']);
			$primaryWikiPage = $wikiService->getPrimaryPageForTag($tagInfo['id']);
			$wikiPagesCount = count($wikiPages);
        } elseif ($author) {
			 $title = "Публикации пользователя " . e($author);
		} elseif ($domain) {	
            $title = "Публикации с домена " . e($domain);
			
			$this->setOpenGraph([
				'type' => 'article',
				'title' => $title,
				'description' => null,
				'image' => config('config.app.url') . '/',
			]);
        }

        $this->render('index', [
            'title' => $title,
            'stories' => $stories,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
			'tagInfo' => $tagInfo,
            'newCommentsMap' => $newCommentsMap,
            'bannedDomainsCache' => $bannedDomainsCache,
            'sort' => $sort,
			'wikiPages' => $wikiPages,
			'primaryWikiPage' => $primaryWikiPage,
			'wikiPagesCount' => $wikiPagesCount,
			'author' => $author,
			'domain' => $domain,
            'currentUserId' => $currentUserId,
            'isAdmin' => $isAdmin,
            'canUserDownvote' => $canUserDownvote,
            'currentVotes' => $currentVotes,
        ]);
    }
    
    // =========================================================================
    // ПРОСМОТР ОДНОЙ ИСТОРИИ
    // =========================================================================

    /**
     * Просмотр одной истории с комментариями.
     *
     * @param string $id ID истории
     */
    public function show(string $id): void
    {
        $storyId = (int)$id;

        $story = $this->service(StoryFilterService::class)->getStoryWithAuthor($storyId);
        if (!$story) {
            $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
            if (class_exists($errorController)) {
                $controller = $this->container->make($errorController);
                $controller->notFound("История не найдена.");
                exit;
            }
            http_response_code(404);
            die("404 Not Found");
        }

        // Получаем комментарии в виде дерева
        $commentsTree = $this->service(StoryFilterService::class)->getCommentsTree($storyId);

        // Обрабатываем отметку прочитанного
        $newCount = $this->service(ReadRibbonService::class)->handleStoryView($storyId);

        // Получаем данные предложений
        $suggestionService = $this->service(\App\Modules\Suggestions\Services\SuggestionService::class);
        $activeSuggestions = $suggestionService->getActiveSuggestions('Story', $storyId);
        $changeLog = $suggestionService->getChangeLog('Story', $storyId, 10);

        // ✅ Получаем модели из контейнера вместо new
        $tagModel = $this->container->get(Tag::class);
        $allTags = $tagModel->getAllTags();

        $storyModel = $this->container->get(Story::class);
        $currentTagIds = $storyModel->getStoryTagIds($storyId);

        // ✅ Получаем данные для голосования
        $currentUserId = Auth::check() ? Auth::id() : 0;
        $isAdmin = Auth::isAdmin();
        $isModerator = Auth::isModerator();
        $isAuthor = $currentUserId > 0 && (int)$story['user_id'] === $currentUserId;
        
        $canUserDownvote = false;
        $currentStoryVote = null;
        $currentCommentVotes = [];
        $userSuggestionsCount = 0;
        
        if ($currentUserId > 0) {
            // Получаем модель User из контейнера
            $userModel = $this->container->get(User::class);
            $viewerKarma = $userModel->getUserKarma($currentUserId);
            $minKarmaForDownvote = config('config.app.min_karma_for_downvote', 10, 'int');
            $canUserDownvote = ($viewerKarma >= $minKarmaForDownvote);
            
            // Получаем голос за историю
            $voteModel = $this->container->get(Vote::class);
            $currentStoryVote = $voteModel->getUserVote($currentUserId, 'story', $storyId);
            
            // Получаем голоса за все комментарии
            foreach ($commentsTree as $parentId => $comments) {
                foreach ($comments as $comment) {
                    $commentId = (int)$comment['id'];
                    $currentCommentVotes[$commentId] = $voteModel->getUserVote($currentUserId, 'comment', $commentId);
                }
            }
            
            // Проверяем лимит предложений (только для не-модераторов)
            if (!$isModerator && !$isAdmin) {
                $userSuggestionsCount = $suggestionService->getUserActiveSuggestionsCount('Story', $storyId, $currentUserId);
            }
        }

        // Получаем OG-данные из сервиса
        $ogData = $this->service(StoryFilterService::class)->getStoryOpenGraphData($storyId);
        
        // Контроллер устанавливает OG-теги (презентация)
        $this->setOpenGraph([
            'type' => 'article',
            'title' => $ogData['title'],
            'description' => $ogData['description'],
            'image' => $ogData['image'],
            'article:author' => $ogData['author_url'],
        ]);

        $this->render('show', [
            'title' => $story['title'],
            'story' => $story,
            'commentsTree' => $commentsTree,
            'newCount' => $newCount,
            'activeSuggestions' => $activeSuggestions,
            'changeLog' => $changeLog,
            'allTags' => $allTags,
            'currentTagIds' => $currentTagIds,
            // ✅ Передаём данные для голосования и прав доступа
            'currentUserId' => $currentUserId,
            'isAdmin' => $isAdmin,
            'isModerator' => $isModerator,
            'isAuthor' => $isAuthor,
            'canUserDownvote' => $canUserDownvote,
            'currentStoryVote' => $currentStoryVote,
            'currentCommentVotes' => $currentCommentVotes,
            'userSuggestionsCount' => $userSuggestionsCount,
        ]);
    }

    
    // =========================================================================
    // СОЗДАНИЕ ИСТОРИИ
    // =========================================================================

    /**
     * Форма создания новой истории.
     */
    public function showCreateForm(): void
    {
        // ✅ Получаем модель из контейнера
        $tagModel = $this->container->get(Tag::class);
        $availableTags = $tagModel->getAllTags(false);

        $this->render('create', [
            'title' => 'Поделиться интересным',
            'availableTags' => $availableTags,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка создания новой истории.
     */
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
            $session = $this->container->get(Session::class);
            $session->flash('success', 'Ваша история успешно опубликована!');
            $this->redirectBack('/');
        }

        $this->redirectBack('/stories/create');
    }
    
    // =========================================================================
    // РЕДАКТИРОВАНИЕ ИСТОРИИ
    // =========================================================================

    /**
     * Форма редактирования истории.
     *
     * @param string $id ID истории
     */
    public function showEditForm(string $id): void
    {
        $storyId = (int)$id;
        
        // ✅ Получаем модели из контейнера
        $storyModel = $this->container->get(Story::class);
        $story = $storyModel->find($storyId);

        $userId = Auth::id();
        if (!$story || !$this->service(StoryService::class)->canEditStory($story, $userId)) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'У вас нет прав для изменения этой публикации.');
            $this->redirectBack('/');
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

    /**
     * Обработка обновления истории.
     *
     * @param string $id ID истории
     */
    public function update(string $id): void
    {
        $storyId = (int)$id;
        
        // ✅ Получаем модель из контейнера
        $storyModel = $this->container->get(Story::class);
        $story = $storyModel->find($storyId);
        $userId = Auth::id();

        if (!$story || !$this->service(StoryService::class)->canEditStory($story, $userId)) {
            $this->redirectBack('/');
        }

        $data = [
            'title' => $this->request->getParams('title'),
            'url' => $this->request->getParams('url') ?: null,
            'description' => $this->request->getParams('description') ?: null,
            'tags' => $this->request->post('tags', []),
            'user_is_following' => $this->request->post('user_is_following') !== null ? 1 : 0,
        ];

        $this->service(StoryService::class)->updateStory($storyId, $data);

        $session = $this->container->get(Session::class);
        $session->flash('success', 'Публикация успешно отредактирована.');
        $this->redirectBack('/story/' . $storyId);
    }
    
    // =========================================================================
    // АДМИНИСТРИРОВАНИЕ ИСТОРИЙ
    // =========================================================================

    /**
     * Удаление истории (только для администраторов).
     *
     * @param string $id ID истории
     */
    public function adminDelete(string $id): void
    {
        $this->service(StoryService::class)->deleteStory((int)$id, Auth::id());
        $this->redirectBack();
    }

    /**
     * Восстановление истории (только для администраторов).
     *
     * @param string $id ID истории
     */
    public function adminRestore(string $id): void
    {
        $this->service(StoryService::class)->restoreStory((int)$id, Auth::id());
        $this->redirectBack();
    }
    
    // =========================================================================
    // КОММЕНТАРИИ
    // =========================================================================

    /**
     * Добавление нового комментария.
     */
    public function addComment(): void
    {
        $storyId = (int)$this->request->getParams('story_id');
        $parentId = $this->request->getParams('parent_id') !== '' ? (int)$this->request->getParams('parent_id') : null;
        $commentText = $this->request->getParams('comment_text');

        $userId = Auth::id();

        $result = $this->service(CommentService::class)->createComment(
            $storyId,
            $commentText,
            $parentId,
            $userId
        );

        if (!empty($result)) {
            $this->redirect(comment_url($result['story_id'], $result['comment_id']));
        } else {
            $this->redirect('/story/' . $storyId);
        }
    }

    /**
     * Редактирование комментария.
     *
     * @param string $id ID комментария
     */
    public function editComment(string $id): void
    {
        $commentId = (int)$id;
        $newText = $this->request->getParams('comment_text');
        $userId = Auth::id();

        $result = $this->service(CommentService::class)->updateComment($commentId, $newText, $userId);

        if ($result === null) {
            $this->redirectBack();
            return;
        }

        $this->redirect(comment_url((int)$result['comment']['story_id'], $commentId));
    }

    /**
     * Удаление комментария.
     *
     * @param string $id ID комментария
     */
    public function deleteComment(string $id): void
    {
        $commentId = (int) $id;
        $userId = Auth::id();

        $result = $this->service(CommentService::class)->deleteComment($commentId, $userId);

        if ($result === null) {
            $this->redirectBack();
            return;
        }

        $this->redirect(comment_url($result['story_id'], $commentId));
    }

    /**
     * Восстановление удалённого комментария.
     *
     * @param string $id ID комментария
     */
    public function restoreComment(string $id): void
    {
        $commentId = (int) $id;
        $userId = Auth::id();

        $result = $this->service(CommentService::class)->restoreComment($commentId, $userId);

        if ($result === null) {
            $this->redirectBack();
            return;
        }

        $this->redirect(comment_url($result['story_id'], $commentId));
    }
    
    // =========================================================================
    // ПОДПИСКА И ПРОЧТЕНИЕ
    // =========================================================================

    /**
     * Переключение подписки на историю.
     *
     * @param string $id ID истории
     */
    public function toggleFollow(string $id): void
    {
        $storyId = (int)$id;
        $userId = Auth::id();

        // ✅ Получаем модель из контейнера
        $storyModel = $this->container->get(Story::class);
        $storyModel->toggleFollow($storyId, $userId);

        // AJAX-ответ
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

    /**
     * Отметить историю как прочитанную.
     *
     * @param string $id ID истории
     */
    public function markRead(string $id): void
    {
        $storyId = (int)$id;
        $this->service(ReadRibbonService::class)->markAsRead($storyId);

        $referer = $_SERVER['HTTP_REFERER'] ?? '/story/' . $storyId;
        $this->redirectBack($referer);
    }
	
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

		$fetcher = new UrlFetcherService();
		$attributes = $fetcher->fetchAttributes($url);

		$this->json($attributes);
	}
	
	private function validateAuthor(string $username): string
	{
		$username = trim($username);
		
		if ($username === '') {
			return '';
		}
		
		// ✅ Получаем Validator из контейнера
		$validator = $this->container->get(\App\Core\Validator::class);
		$validator->validate(
			['username' => $username],
			['username' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/']
		);
		
		// Если невалиден — возвращаем пустую строку (фильтр не применяется)
		if (!$validator->isValid()) {
			return '';
		}
		
		// ✅ Получаем User из контейнера
		$userModel = $this->container->get(User::class);
		$user = $userModel->findByName($username);
		
		return $user ? $username : '';
	}
	
	/**
	 * AJAX endpoint для предпросмотра Markdown
	 */
	public function preview(): void
	{
		// Проверяем CSRF
		if (!$this->request->isCsrfValid()) {
			$this->json(['error' => 'Неверный CSRF токен'], 419);
			return;
		}

		$text = $this->request->post('text', '');
		$allowImages = (bool)$this->request->post('allow_images', true);

		// Используем ваш существующий Markdown парсер
		$html = \App\Modules\Content\Core\Markdown::parse($text, $allowImages);

		$this->json([
			'html' => $html,
			'success' => true
		]);
	}
}