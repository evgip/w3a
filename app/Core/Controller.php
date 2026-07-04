<?php

namespace App\Core;

use App\Core\Events\Event;
use App\Core\Events\EventDispatcher;

abstract class Controller
{
    protected Request $request;
    protected EventDispatcher $eventDispatcher;
    protected Container $container;

    public function __construct(
        Request $request,
        EventDispatcher $eventDispatcher,
        Container $container
    ) {
        $this->request = $request;
        $this->eventDispatcher = $eventDispatcher;
        $this->container = $container;
    }

    protected function dispatch(Event $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }

	/**
	 * Рендеринг шаблона
	 */
	protected function render(string $viewName, array $data = []): void
	{
		$data['csrf_token'] = $this->request->getCsrfToken();
		
		// ✅ Добавляем общие данные для layout
		$data = array_merge($data, $this->getCommonViewData());
		
		$calledClass = get_called_class();
		$parts = explode('\\', $calledClass);
		$moduleName = $parts[2] ?? '';
		
		if (!empty($moduleName)) {
			\App\Core\Lang::loadModuleLang($moduleName);
		}
		
		$modulePath = dirname(__DIR__) . "/Modules/{$moduleName}";
		$viewFile = "{$modulePath}/Views/{$viewName}.php";
		$layoutFile = "{$modulePath}/Views/layout.php";

		if (!file_exists($viewFile)) {
			http_response_code(500);
			$errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
			if (class_exists($errorController)) {
				$controller = $this->container->make($errorController);
				$controller->serverError("Внутренняя ошибка сервера.");
				exit;
			}
			die("<h1>500 Internal Server Error</h1>");
		}

		// ✅ Рендерим view-файл в отдельной области видимости
		ob_start();
		(function() use ($data, $viewFile) {
			extract($data, EXTR_SKIP);
			include $viewFile;
		})();
		$content = ob_get_clean();

		// ✅ ДОБАВЛЯЕМ content в data, чтобы он был доступен в layout
		$data['content'] = $content;

		// ✅ Рендерим layout с извлечением переменных из $data
		if (file_exists($layoutFile)) {
			(function() use ($data, $layoutFile) {
				extract($data, EXTR_SKIP);
				include $layoutFile;
			})();
		} else {
			$fallbackLayout = dirname(__DIR__) . '/Modules/Common/Views/layout.php';
			if (file_exists($fallbackLayout)) {
				(function() use ($data, $fallbackLayout) {
					extract($data, EXTR_SKIP);
					include $fallbackLayout;
				})();
			} else {
				echo $content;
			}
		}
	}

    /**
     * ✅ НОВЫЙ МЕТОД: Получение общих данных для всех шаблонов
     * Эти данные автоматически добавляются в каждый render()
     */
    protected function getCommonViewData(): array
    {
        $data = [];
        
        // ✅ ОТЛАДКА: проверяем, что метод вызывается
        error_log('=== getCommonViewData() called ===');
        
        try {
            // Получаем Session из контейнера
            $session = $this->container->get(Session::class);
            
            // ✅ ОТЛАДКА: проверяем сессию
            error_log('Session user_id: ' . ($session->get('user_id') ?? 'NULL'));
            error_log('Session data: ' . print_r($session->all(), true));
            
            $userId = $session->get('user_id');
            
            $data['currentUser'] = [
                'id' => $userId,
                'name' => $session->get('user_name'),
                'role' => $session->get('user_role'),
                'avatar' => $session->get('user_avatar'),
                'isLoggedIn' => (bool)$userId,
                'isAdmin' => ($session->get('user_role') === 'admin'),
                'isModerator' => in_array($session->get('user_role'), ['admin', 'moderator']),
            ];
            
            // ✅ ОТЛАДКА: проверяем currentUser
            error_log('currentUser: ' . print_r($data['currentUser'], true));
            
            // Счётчики для шапки (только для авторизованных)
            if ($data['currentUser']['isLoggedIn']) {
                try {
                    // Непрочитанные уведомления
                    $notifModel = $this->container->get(\App\Modules\Notifications\Models\Notification::class);
                    $data['unreadNotificationsCount'] = $notifModel->getUnreadCount($data['currentUser']['id']);
                    
                    // Ожидающие флаги (для админов/модераторов)
                    if ($data['currentUser']['isModerator']) {
                        $flagModel = $this->container->get(\App\Modules\Flags\Models\Flag::class);
                        $data['pendingFlagsCount'] = $flagModel->getPendingCount();
                        
                        $suggestionModel = $this->container->get(\App\Modules\Suggestions\Models\Suggestion::class);
                        $data['activeSuggestionsCount'] = $suggestionModel->countAllActive();
                    } else {
                        $data['pendingFlagsCount'] = 0;
                        $data['activeSuggestionsCount'] = 0;
                    }
                } catch (\Throwable $e) {
                    error_log('Error in getCommonViewData counters: ' . $e->getMessage());
                    $data['unreadNotificationsCount'] = 0;
                    $data['pendingFlagsCount'] = 0;
                    $data['activeSuggestionsCount'] = 0;
                }
            } else {
                $data['unreadNotificationsCount'] = 0;
                $data['pendingFlagsCount'] = 0;
                $data['activeSuggestionsCount'] = 0;
            }
        } catch (\Throwable $e) {
            error_log('Error in getCommonViewData: ' . $e->getMessage());
            error_log('Trace: ' . $e->getTraceAsString());
            
            // Fallback: возвращаем пустые данные
            $data['currentUser'] = [
                'id' => null,
                'name' => null,
                'role' => null,
                'avatar' => null,
                'isLoggedIn' => false,
                'isAdmin' => false,
                'isModerator' => false,
            ];
            $data['unreadNotificationsCount'] = 0;
            $data['pendingFlagsCount'] = 0;
            $data['activeSuggestionsCount'] = 0;
        }
        
        return $data;
    }

    protected function json(array $data, int $statusCode = 200): void
    {
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    protected function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }

    protected function redirectBack(string $fallback = '/'): void
    {
        $this->redirect($this->getSafeBackUrl($fallback));
    }

    private function getSafeBackUrl(string $fallback = '/'): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        return $this->isSafeUrl($referer) ? $referer : $fallback;
    }

    private function isSafeUrl(string $url): bool
    {
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        $urlHost = parse_url($url, PHP_URL_HOST);
        if ($urlHost === null) {
            return false;
        }
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        return $urlHost === $appHost;
    }

    protected function redirectWithMessage(string $url, string $message, string $type = 'success'): void
    {
        $session = $this->container->get(Session::class);
        $session->flash($type, $message);
        $this->redirect($url);
    }

    protected function backWithMessage(string $message, string $type = 'success', string $fallback = '/'): void
    {
        $this->redirectWithMessage($this->getSafeBackUrl($fallback), $message, $type);
    }

    protected function service(string $class): mixed
    {
        return $this->container->get($class);
    }

    protected function setOpenGraph(array $data): void
    {
        if (!isset($data['url'])) {
            $data['url'] = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
        }
        OpenGraph::set($data);
    }

    protected function renderBreadcrumbs(array $items): string
    {
        $html = '<nav class="breadcrumb" aria-label="breadcrumb"><ol>';
        foreach ($items as $item) {
            if (isset($item['url'])) {
                $html .= '<li><a href="' . e($item['url']) . '">' . e($item['title']) . '</a></li>';
            } else {
                $html .= '<li class="active" aria-current="page">' . e($item['title']) . '</li>';
            }
        }
        $html .= '</ol></nav>';
        return $html;
    }
}