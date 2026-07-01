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
     * @param string $tagname Фильтр по тегу (опционально)
     * @param string $domain Фильтр по домену (опционально)
     */
    public function index(string $tagname = '', string $domain = ''): void
    {
        $currentPage = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.stories_per_page', 15, 'int');
        $offset = ($currentPage - 1) * $perPage;

        // Читаем режим сортировки
        $sort = $this->request->getParams('sort', 'hot');
        if (!in_array($sort, ['hot', 'new', 'top'], true)) {
            $sort = 'hot';
        }

        // Получаем отфильтрованные истории через сервис
        $stories = $this->service(StoryFilterService::class)->getFilteredStories(
            $perPage,
            $offset,
            $tagname,
            $domain,
            $sort
        );
        $totalStories = $this->service(StoryFilterService::class)->getTotalCount($tagname, $domain);
        $totalPages = (int)ceil($totalStories / $perPage);

        // Дополнительные данные
        $bannedDomainsCache = $this->service(StoryFilterService::class)->getBannedDomains();
        $storyIds = array_column($stories, 'id');
        $newCommentsMap = $this->service(StoryFilterService::class)->getNewCommentsCounts($storyIds);

        // Формируем заголовок страницы
        $title = 'Лента историй';
		$tagInfo = '';
        if ($tagname) {
            $title = "Публикации с тегом # " . e($tagname);
			
			// Получаем OG-данные из сервиса
			$ogData = $this->service(TagFilterService::class)->getTagOpenGraphData($tagname);
			$this->setOpenGraph([
				'type' => 'article',
				'title' => $ogData['title'],
				'description' => $ogData['description'],
				'image' => config('config.app.url') . '/',
			]);
			
			// Инфа по тегу для инфы над постами
			$tagInfo =  $this->service(TagFilterService::class)->getByInfoSlug($tagname);
	
			// Получаем данные о wiki для этого тега
			$wikiService = $this->service(\App\Modules\Wiki\Services\WikiService::class);
			$wikiPages = $wikiService->getPagesForTag($tagInfo['id']);
			$primaryWikiPage = $wikiService->getPrimaryPageForTag($tagInfo['id']);
			$wikiPagesCount = count($wikiPages);

			/*// Проверяем права на создание wiki
			$canCreateWiki = false;
			if (\App\Modules\Auth\Services\Auth::check()) {
				$permissionService = $this->service(\App\Modules\Wiki\Services\WikiPermissionService::class);
				$canCreateWiki = $permissionService->canCreateWikiForTag($tagInfo['id'], \App\Modules\Auth\Services\Auth::id());
			} */
			

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
			'wikiPages' => $wikiPages ?? false,
			'primaryWikiPage' => $primaryWikiPage ?? false,
			'wikiPagesCount' => $wikiPagesCount ?? false,
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
                (new $errorController())->notFound("История не найдена.");
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

        // Получаем теги для модалки
        $tagModel = new \App\Modules\Tags\Models\Tag();
        $allTags = $tagModel->getAllTags();

        $storyModel = new \App\Modules\Stories\Models\Story();
        $currentTagIds = $storyModel->getStoryTagIds($storyId);

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
            'currentTagIds' => $currentTagIds
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
        $tagModel = new Tag();
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
            Session::setFlash('success', 'Ваша история успешно опубликована!');
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
        $storyModel = new Story();
        $story = $storyModel->find($storyId);

        $userId = Auth::id();
        if (!$story || !$this->service(StoryService::class)->canEditStory($story, $userId)) {
            Session::setFlash('error', 'У вас нет прав для изменения этой публикации.');
            $this->redirectBack('/');
        }

        $tagModel = new Tag();

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
        $storyModel = new Story();
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

        Session::setFlash('success', 'Публикация успешно отредактирована.');
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
     *
     * Контроллер выполняет только:
     *  - Получение параметров из запроса
     *  - Вызов сервиса
     *  - Редирект
     */
    public function addComment(): void
    {
        $storyId = (int)$this->request->getParams('story_id');
        $parentId = $this->request->getParams('parent_id') !== '' ? (int)$this->request->getParams('parent_id') : null;
        $commentText = $this->request->getParams('comment_text');

        $userId = Auth::id();

        // Сервис выполняет создание, диспатчит событие и устанавливает flash
        $result = $this->service(CommentService::class)->createComment(
            $storyId,
            $commentText,
            $parentId,
            $userId
        );

        if (!empty($result)) {
            // Успех — редирект на комментарий
            $this->redirect(comment_url($result['story_id'], $result['comment_id']));
        } else {
            // Ошибка — редирект на историю (flash уже установлен в сервисе)
            $this->redirect('/story/' . $storyId);
        }
    }

    /**
     * Редактирование комментария.
     *
     * Контроллер выполняет только:
     *  - Получение параметров из запроса
     *  - Вызов сервиса
     *  - Редирект
     *
     * @param string $id ID комментария
     */
    public function editComment(string $id): void
    {
        $commentId = (int)$id;
        $newText = $this->request->getParams('comment_text');
        $userId = Auth::id();

        // Сервис выполняет обновление и диспатчит событие
        $result = $this->service(CommentService::class)->updateComment($commentId, $newText, $userId);

        if ($result === null) {
            // При ошибке flash-сообщение уже установлено в сервисе
            $this->redirectBack();
            return;
        }

        // Редирект на комментарий
        $this->redirect(comment_url((int)$result['comment']['story_id'], $commentId));
    }

    /**
     * Удаление комментария.
     *
     * Контроллер выполняет только:
     *  - Получение параметров из запроса
     *  - Вызов сервиса
     *  - Редирект
     *
     * Вся бизнес-логика (проверка прав, удаление, событие, flash) — в сервисе.
     *
     * @param string $id ID комментария
     */
    public function deleteComment(string $id): void
    {
        $commentId = (int) $id;
        $userId = Auth::id();

        // Сервис выполняет удаление и диспатчит событие
        // Возвращает данные для редиректа или null при ошибке
        $result = $this->service(CommentService::class)->deleteComment($commentId, $userId);

        if ($result === null) {
            // При ошибке flash-сообщение уже установлено в сервисе
            $this->redirectBack();
            return;
        }

        // ✅ Редирект на страницу истории с якорем на комментарий
        // Используем данные из сервиса — без дублирования запроса к БД!
        $this->redirect(comment_url($result['story_id'], $commentId));
    }

    /**
     * Восстановление удалённого комментария.
     *
     * Контроллер выполняет только:
     *  - Получение параметров из запроса
     *  - Вызов сервиса
     *  - Редирект
     *
     * @param string $id ID комментария
     */
    public function restoreComment(string $id): void
    {
        $commentId = (int) $id;
        $userId = Auth::id();

        // Сервис выполняет восстановление и диспатчит событие
        $result = $this->service(CommentService::class)->restoreComment($commentId, $userId);

        if ($result === null) {
            // При ошибке flash-сообщение уже установлено в сервисе
            $this->redirectBack();
            return;
        }

        // ✅ Редирект на страницу истории с якорем на комментарий
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

        $storyModel = new Story();
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
}
