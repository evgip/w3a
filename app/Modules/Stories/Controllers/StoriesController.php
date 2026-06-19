<?php

declare(strict_types=1);

namespace App\Modules\Stories\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Session;
use App\Core\Audit;
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
use App\Modules\Moderations\Models\Moderation;

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
    /** @var StoryService|null Лениво инициализируемый сервис */
    private ?StoryService $storyService = null;

    /** @var StoryFilterService|null Лениво инициализируемый сервис */
    private ?StoryFilterService $filterService = null;

    /** @var CommentService|null Лениво инициализируемый сервис */
    private ?CommentService $commentService = null;

    /** @var ReadRibbonService|null Лениво инициализируемый сервис */
    private ?ReadRibbonService $readRibbonService = null;

    public function __construct()
    {
        // Временно: диагностика, какой класс падает
        $classes = [
            'App\\Modules\\Stories\\Models\\Story' => 'Story',
            'App\\Modules\\Origins\\Models\\Domain' => 'Domain',
            'App\\Modules\\Stories\\Models\\Comment' => 'Comment',
            'App\\Modules\\Stories\\Models\\ReadRibbon' => 'ReadRibbon',
            'App\\Modules\\Notifications\\Services\\NotificationService' => 'NotificationService',
        ];

        foreach ($classes as $class => $name) {
            try {
                if (!class_exists($class)) {
                    error_log("✗ {$name}: класс НЕ СУЩЕСТВУЕТ");
                    continue;
                }

                $reflection = new \ReflectionClass($class);

                if (!$reflection->isInstantiable()) {
                    error_log("✗ {$name}: НЕ МОЖЕТ быть создан (абстрактный/интерфейс/приватный конструктор)");
                    continue;
                }

                $constructor = $reflection->getConstructor();
                if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
                    error_log("✗ {$name}: требует {$constructor->getNumberOfRequiredParameters()} обязательных параметров");
                    continue;
                }

                new $class();
                error_log("✓ {$name}: OK");
            } catch (\Throwable $e) {
                error_log("✗ {$name}: " . $e->getMessage());
            }
        }

        // Теперь вызываем родительский конструктор
        try {
            parent::__construct();
        } catch (\Throwable $e) {
            error_log("✗ parent::__construct(): " . $e->getMessage());
            error_log("   Файл: " . $e->getFile() . ":" . $e->getLine());
        }
    }
    
    // =========================================================================
    // ЛЕНИВЫЕ ГЕТТЕРЫ СЕРВИСОВ
    // =========================================================================

    /**
     * Получает экземпляр StoryService (ленивая инициализация).
     */
    private function getStoryService(): StoryService
    {
        if ($this->storyService === null) {
            $this->storyService = new StoryService(
                new Story(),
                new Domain()
            );
        }
        return $this->storyService;
    }

    /**
     * Получает экземпляр StoryFilterService (ленивая инициализация).
     */
    private function getFilterService(): StoryFilterService
    {
        if ($this->filterService === null) {
            $this->filterService = new StoryFilterService(
                new Story(),
                new Domain()
            );
        }
        return $this->filterService;
    }

    /**
     * Получает экземпляр CommentService (ленивая инициализация).
     */
    private function getCommentService(): CommentService
    {
        if ($this->commentService === null) {
            $this->commentService = new CommentService(
                new Comment(),
                new NotificationService()
            );
        }
        return $this->commentService;
    }

    /**
     * Получает экземпляр ReadRibbonService (ленивая инициализация).
     */
    private function getReadRibbonService(): ReadRibbonService
    {
        if ($this->readRibbonService === null) {
            $this->readRibbonService = new ReadRibbonService(
                new ReadRibbon()
            );
        }
        return $this->readRibbonService;
    }
    
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
        $request = new Request();

        $currentPage = max(1, (int)$request->getParams('page', 1));
        $perPage = config_int('constants.pagination.stories_per_page', 15);
        $offset = ($currentPage - 1) * $perPage;

        // Получаем отфильтрованные истории через сервис
        $stories = $this->getFilterService()->getFilteredStories($perPage, $offset, $tagname, $domain);
        $totalStories = $this->getFilterService()->getTotalCount($tagname, $domain);
        $totalPages = (int)ceil($totalStories / $perPage);

        // Дополнительные данные
        $bannedDomainsCache = $this->getFilterService()->getBannedDomains();
        $storyIds = array_column($stories, 'id');
        $newCommentsMap = $this->getFilterService()->getNewCommentsCounts($storyIds);

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

        $story = $this->getFilterService()->getStoryWithAuthor($storyId);
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
        $commentsTree = $this->getFilterService()->getCommentsTree($storyId);

        // Обрабатываем отметку прочитанного
        $newCount = $this->getReadRibbonService()->handleStoryView($storyId);

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
        $this->requireAuth();

        $tagModel = new Tag();
        $availableTags = $tagModel->getAllTags();

        $this->render('create', [
            'title' => 'Поделиться интересным',
            'availableTags' => $availableTags,
            'request' => new Request()
        ]);
    }

    /**
     * Обработка создания новой истории.
     */
    public function create(): void
    {
        $this->requireAuth();

	    // Проверка бана пользователя
		$userModel = new \App\Modules\Users\Models\User();
		if ($userModel->isBanned((int)$_SESSION['user_id'])) {
			Session::setFlash('error', 'Ваш аккаунт заблокирован. Вы не можете публиковать истории.');
			header('Location: /stories/create');
			exit;
		}

        $request = new Request();
        $request->validateCsrf();

        $data = [
            'title' => $request->getParams('title'),
            'url' => $request->getParams('url') ?: null,
            'description' => $request->getParams('description') ?: null,
            'tags' => $_POST['tags'] ?? [],
            'user_is_following' => isset($_POST['user_is_following']) ? 1 : 0,
        ];

        $storyId = $this->getStoryService()->createStory($data, (int)$_SESSION['user_id']);

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
        $this->requireAuth();

        $storyId = (int)$id;
        $storyModel = new Story();
        $story = $storyModel->find($storyId);

        if (!$story || !$this->getStoryService()->canEditStory($story, (int)$_SESSION['user_id'])) {
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
            'request' => new Request()
        ]);
    }

    /**
     * Обработка обновления истории.
     *
     * @param string $id ID истории
     */
    public function update(string $id): void
    {
        $this->requireAuth();

        $request = new Request();
        $request->validateCsrf();

        $storyId = (int)$id;
        $storyModel = new Story();
        $story = $storyModel->find($storyId);

        if (!$story || !$this->getStoryService()->canEditStory($story, (int)$_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }

        $data = [
            'title' => $request->getParams('title'),
            'url' => $request->getParams('url') ?: null,
            'description' => $request->getParams('description') ?: null,
            'tags' => $_POST['tags'] ?? [],
            'user_is_following' => isset($_POST['user_is_following']) ? 1 : 0,
        ];

        $this->getStoryService()->updateStory($storyId, $data);

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
        Auth::middlewareAdmin();

        $request = new Request();
        $request->validateCsrf();

        $storyId = (int)$id;
        $storyModel = new Story();
        $storyModel->delete($storyId);

        Audit::log('admin.story_moderated', "Администратор принудительно скрыл публикацию ID: {$storyId}");
        Session::setFlash('success', 'Публикация успешно скрыта из общей ленты.');

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        exit;
    }

    /**
     * Восстановление истории (только для администраторов).
     *
     * @param string $id ID истории
     */
    public function adminRestore(string $id): void
    {
        Auth::middlewareAdmin();

        $request = new Request();
        $request->validateCsrf();

        $storyId = (int)$id;
        $storyModel = new Story();
        $storyModel->restore($storyId);

        Audit::log('admin.story_restored', "Администратор восстановил публикацию ID: {$storyId}");
        Session::setFlash('success', 'Публикация успешно восстановлена в общей ленте.');

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        exit;
    }
    
    // =========================================================================
    // КОММЕНТАРИИ
    // =========================================================================

    /**
     * Добавление нового комментария.
     */
    public function addComment(): void
    {
        $this->requireAuth();

		// НОВОЕ: Проверка бана пользователя
		$userModel = new \App\Modules\Users\Models\User();
		if ($userModel->isBanned((int)$_SESSION['user_id'])) {
			Session::setFlash('error', 'Ваш аккаунт заблокирован. Вы не можете оставлять комментарии.');
			header('Location: /story/' . $storyId);
			exit;
		}

        $request = new Request();
        $request->validateCsrf();

        $storyId = (int)$request->getParams('story_id');
        $parentId = $request->getParams('parent_id') !== '' ? (int)$request->getParams('parent_id') : null;
        $commentText = $request->getParams('comment_text');

        $commentId = $this->getCommentService()->createComment(
            $storyId,
            $commentText,
            $parentId,
            (int)$_SESSION['user_id']
        );

        if ($commentId > 0) {
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
        $this->requireAuth();

        $request = new Request();
        $request->validateCsrf();

        $commentId = (int)$id;
        $newText = $request->getParams('comment_text');

        $this->getCommentService()->updateComment($commentId, $newText, (int)$_SESSION['user_id']);

        $commentModel = new Comment();
        $comment = $commentModel->withTrashed()->find($commentId);

        if ($comment) {
            header('Location: ' . comment_url((int)$comment['story_id'], $commentId));
        } else {
            header('Location: /');
        }
        exit;
    }

    /**
     * Удаление комментария.
     *
     * @param string $id ID комментария
     */
    public function deleteComment(string $id): void
    {
        $this->requireAuth();

        $request = new Request();
        $request->validateCsrf();

        $commentId = (int)$id;

        // ✅ Получаем комментарий ДО удаления, чтобы знать story_id
        $commentModel = new Comment();
        $comment = $commentModel->find($commentId);

        $this->getCommentService()->deleteComment($commentId, (int)$_SESSION['user_id']);

        if ($comment) {
            header('Location: ' . comment_url((int)$comment['story_id'], $commentId));
        } else {
            header('Location: /');
        }
        exit;
    }

    /**
     * Восстановление удалённого комментария.
     *
     * @param string $id ID комментария
     */
    public function restoreComment(string $id): void
    {
        $this->requireAuth();

        $request = new Request();
        $request->validateCsrf();

        $commentId = (int)$id;
        $this->getCommentService()->restoreComment($commentId, (int)$_SESSION['user_id']);

        $commentModel = new Comment();
        $comment = $commentModel->withTrashed()->find($commentId);

        if ($comment) {
            header('Location: ' . comment_url((int)$comment['story_id'], $commentId));
        } else {
            header('Location: /');
        }
        exit;
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
        $this->requireAuth();

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
        $this->requireAuth();

        $request = new Request();
        $request->validateCsrf();

        $storyId = (int)$id;
        $this->getReadRibbonService()->markAsRead($storyId);

        $referer = $_SERVER['HTTP_REFERER'] ?? '/story/' . $storyId;
        header('Location: ' . $referer);
        exit;
    }
}
