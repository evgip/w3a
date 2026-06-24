<?php

declare(strict_types=1);

namespace App\Modules\Stories\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Events\StoryDeleted;
use App\Core\Events\StoryRestore;
use App\Core\Events\CommentUpdated;
use App\Core\Events\CommentCreated;
use App\Core\Events\CommentDeleted;
use App\Core\Events\CommentRestored;
use App\Modules\Stories\Services\StoryService;
use App\Modules\Stories\Services\StoryFilterService;
use App\Modules\Stories\Services\CommentService;
use App\Modules\Stories\Services\ReadRibbonService;
use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Models\Comment;
use App\Modules\Stories\Models\ReadRibbon;
use App\Modules\Tags\Models\Tag;
use App\Modules\Origins\Models\Domain;
use App\Modules\Notifications\Services\NotificationService;
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
        $perPage = config_int('constants.pagination.stories_per_page', 15);
        $offset = ($currentPage - 1) * $perPage;

		// ← НОВОЕ: читаем режим сортировки
		$sort = $this->request->getParams('sort', 'hot');
		if (!in_array($sort, ['hot', 'new', 'top'], true)) {
			$sort = 'hot';
		}

        // Получаем отфильтрованные истории через сервис
		$stories = $this->service(StoryFilterService::class)->getFilteredStories(
			$perPage, $offset, $tagname, $domain, $sort
		);
        $totalStories = $this->service(StoryFilterService::class)->getTotalCount($tagname, $domain);
        $totalPages = (int)ceil($totalStories / $perPage);

        // Дополнительные данные
        $bannedDomainsCache = $this->service(StoryFilterService::class)->getBannedDomains();
        $storyIds = array_column($stories, 'id');
        $newCommentsMap = $this->service(StoryFilterService::class)->getNewCommentsCounts($storyIds);

        // Формируем заголовок страницы
        $title = 'Лента историй';
        if ($tagname) {
            $title = "Публикации с тегом # " . e($tagname);
        } elseif ($domain) {
            $title = "Публикации с домена " . e($domain);
        }

        $this->render('index', [
            'title' => $title,
            'stories' => $stories,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'newCommentsMap' => $newCommentsMap,
            'bannedDomainsCache' => $bannedDomainsCache,
			 'sort' => $sort, 
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

        $this->render('show', [
            'title' => $story['title'],
            'story' => $story,
            'commentsTree' => $commentsTree,
            'newCount' => $newCount,
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
            header('Location: /');
        } else {
            header('Location: /stories/create');
        }
        exit;
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

        if (!$story || !$this->service(StoryService::class)->canEditStory($story, (int)$_SESSION['user_id'])) {
            Session::setFlash('error', 'У вас нет прав для изменения этой публикации.');
            header('Location: /');
            exit;
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

        if (!$story || !$this->service(StoryService::class)->canEditStory($story, (int)$_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }

        $data = [
            'title' => $this->request->getParams('title'),
            'url' => $this->request->getParams('url') ?: null,
            'description' => $this->request->getParams('description') ?: null,
            'tags' => $_POST['tags'] ?? [],
            'user_is_following' => isset($_POST['user_is_following']) ? 1 : 0,
        ];

        $this->service(StoryService::class)->updateStory($storyId, $data);

        Session::setFlash('success', 'Публикация успешно отредактирована.');
        header('Location: /story/' . $storyId);
        exit;
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
        $storyId = (int)$id;
        $storyModel = new Story();
        $storyModel->delete($storyId);

		// Отправляем событие
		$this->dispatch(new StoryDeleted(
			$storyId,
			Auth::id()
		));
        Session::setFlash('success', 'Публикация успешно скрыта из общей ленты.');

        $this->redirectBack();
    }

    /**
     * Восстановление истории (только для администраторов).
     *
     * @param string $id ID истории
     */
    public function adminRestore(string $id): void
    {
        $storyId = (int)$id;
        $storyModel = new Story();
        $storyModel->restore($storyId);

		$this->dispatch(new StoryRestore(
			$storyId,
			Auth::id()
		));
        Session::setFlash('success', 'Публикация успешно восстановлена в общей ленте.');

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

        $commentId = $this->service(CommentService::class)->createComment(
            $storyId,
            $commentText,
            $parentId,
            (int)$_SESSION['user_id']
        );

        if ($commentId > 0) {
			  $this->dispatch(new CommentCreated(
					$commentId,
					$storyId,
					(int)$_SESSION['user_id'],
					$parentId
				));
			
            Session::setFlash('success', 'Ваш комментарий успешно опубликован!');
            header('Location: ' . comment_url($storyId, $commentId));
        } else {
            header('Location: /story/' . $storyId);
        }
        exit;
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

		//  Получаем результат обновления
		$result = $this->service(CommentService::class)->updateComment($commentId, $newText, $userId);

		//  Проверяем успех
		if ($result === null) {
			$this->redirectBack();
			return;
		}

		// Событие для аудита
		$this->dispatch(new CommentUpdated(
			$commentId,
			$userId,
			(bool) $result['is_author']
		));

		//  Используем уже полученный комментарий для редиректа (без дублирования запроса!)
		$this->redirect(comment_url((int)$result['comment']['story_id'], $commentId));
	
	}

	/**
	 * Удаление комментария.
	 *
	 * @param string $id ID комментария
	 */
	public function deleteComment(string $id): void
	{
		$commentId = (int)$id;
		$userId = Auth::id();

		// Сервис возвращает данные (или null при ошибке)
		$result = $this->service(CommentService::class)->deleteComment($commentId, $userId);

		if ($result === null) {
			// При ошибке flash-сообщение уже установлено в сервисе
			$this->redirectBack();
			return;
		}

		// Отправляем событие (аудит отработает через AuditListener)
		$this->dispatch(new CommentDeleted(
			$commentId,
			$result['story_id'],
			$userId,
			(bool) $result['is_author']
		));

		// Редирект через хелпер (используем данные из сервиса — без дублирования запроса!)
		$this->redirect(comment_url($result['story_id'], $commentId));
	}

	/**
	 * Восстановление удалённого комментария.
	 *
	 * @param string $id ID комментария
	 */
	public function restoreComment(string $id): void
	{
		$commentId = (int)$id;
		$userId = Auth::id();

		// Сервис возвращает данные (или null при ошибке)
		$result = $this->service(CommentService::class)->restoreComment($commentId, $userId);

		if ($result === null) {
			// При ошибке flash-сообщение уже установлено в сервисе
			$this->redirectBack();
			return;
		}

		$this->dispatch(new CommentRestored(
			$commentId,
			$result['story_id'],
			$userId,
			(bool)$result['is_author']
		));

		// Редирект через хелпер (используем данные из сервиса — без дублирования запроса!)
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
        $userId = (int)$_SESSION['user_id'];

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

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? "/story/{$storyId}"));
        exit;
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
        header('Location: ' . $referer);
        exit;
    }
}
