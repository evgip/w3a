<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Events\Event;
use App\Core\Events\EventDispatcher;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\JsonResponseException;

abstract class Controller
{
    protected Request $request;
    protected EventDispatcher $eventDispatcher;
    protected Container $container;

    private ?array $commonViewDataCache = null;

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

        // Выбрасываем исключение вместо die()
        if (!file_exists($viewFile)) {
            throw new HttpException(500, "View file not found: {$viewName}");
        }

        // Рендерим view-файл в отдельной области видимости
        ob_start();
        (function () use ($data, $viewFile) {
            extract($data, EXTR_SKIP);
            include $viewFile;
        })();
        $content = ob_get_clean();

        $data['content'] = $content;

        // Рендерим layout
        if (file_exists($layoutFile)) {
            (function () use ($data, $layoutFile) {
                extract($data, EXTR_SKIP);
                include $layoutFile;
            })();
        } else {
            $fallbackLayout = dirname(__DIR__) . '/Modules/Common/Views/layout.php';
            if (file_exists($fallbackLayout)) {
                (function () use ($data, $fallbackLayout) {
                    extract($data, EXTR_SKIP);
                    include $fallbackLayout;
                })();
            } else {
                echo $content;
            }
        }
    }

    /**
     * Получение общих данных для всех шаблонов
     */
    protected function getCommonViewData(): array
    {

        // Возвращаем кеш, если уже вычисляли
        if ($this->commonViewDataCache !== null) {
            return $this->commonViewDataCache;
        }

        $data = [
            'currentUser' => [
                'id' => null,
                'name' => null,
                'role' => null,
                'avatar' => null,
                'isLoggedIn' => false,
                'isAdmin' => false,
                'isModerator' => false,
            ],
            'unreadNotificationsCount' => 0,
            'pendingFlagsCount' => 0,
            'activeSuggestionsCount' => 0,
        ];

        try {
            $session = $this->container->get(Session::class);
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

            // Счётчики для шапки (только для авторизованных)
            if ($data['currentUser']['isLoggedIn']) {
                $data['unreadNotificationsCount'] = $this->getUnreadNotificationsCount($userId);

                if ($data['currentUser']['isModerator']) {
                    $data['pendingFlagsCount'] = $this->getPendingFlagsCount();
                    $data['activeSuggestionsCount'] = $this->getActiveSuggestionsCount();
                }
            }
        } catch (\Throwable $e) {
            // Fallback: возвращаем пустые данные (уже установлены выше)
        }


        // Кешируем результат
        $this->commonViewDataCache = $data;
        return $data;
    }

    /**
     * Получить количество непрочитанных уведомлений
     */
    private function getUnreadNotificationsCount(int $userId): int
    {
        try {
            $notifModel = $this->container->get(\App\Modules\Notifications\Models\Notification::class);
            $muteService = $this->container->get(\App\Modules\Muted\Services\MuteService::class);
            $mutedUserIds = $muteService->getMutedUserIds($userId);
            return $notifModel->getUnreadCount($userId, $mutedUserIds);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Получить количество ожидающих флагов
     */
    private function getPendingFlagsCount(): int
    {
        try {
            $flagModel = $this->container->get(\App\Modules\Flags\Models\Flag::class);
            return $flagModel->getPendingCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Получить количество активных предложений
     */
    private function getActiveSuggestionsCount(): int
    {
        try {
            $suggestionModel = $this->container->get(\App\Modules\Suggestions\Models\Suggestion::class);
            return $suggestionModel->countAllActive();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Используем JsonResponseException вместо exit
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        throw new JsonResponseException($data, $statusCode);
    }

    /**
     * Используем RedirectException вместо exit
     */
    protected function redirect(string $url, int $code = 302): void
    {
        throw new \App\Core\Exceptions\RedirectException($url, $code);
    }

    protected function redirectBack(string $fallback = '/'): void
    {
        $this->redirect($this->getSafeBackUrl($fallback));
    }

    private function getSafeBackUrl(string $fallback = '/'): string
    {
        $referer = $this->request->header('HTTP_REFERER', $fallback);
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

        $appHost = parse_url(config('config.app.url', ''), PHP_URL_HOST);
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
            $host = $this->request->header('HTTP_HOST', 'localhost');
            $uri = $this->request->getUri();
            $data['url'] = 'https://' . $host . $uri;
        }
        OpenGraph::set($data);
    }

    protected function renderBreadcrumbs(array $items): string
    {
        $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
        foreach ($items as $item) {
            $label = $item['label'] ?? $item['title'] ?? '';
            if (isset($item['url'])) {
                $html .= '<li><a href="' . e($item['url']) . '">' . e($label) . '</a></li>';
            } else {
                $html .= '<li class="active" aria-current="page">' . e($label) . '</li>';
            }
        }
        $html .= '</ol></nav>';
        return $html;
    }
}
