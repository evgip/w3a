<?php

namespace App\Modules\Notifications\Controllers;

use App\Core\Controller;
use App\Modules\Auth\Services\Auth;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Stories\Models\Comment;
use App\Modules\Users\Models\User;

/**
 * Контроллер модуля Notifications.
 * 
 * Маршруты (должны соответствовать JS):
 * - GET  /notifications                   → index()
 * - GET  /api/notifications/count         → getCount()
 * - POST /notifications/mark-all-read     → markAllAsRead()  (конкретный — первым!)
 * - POST /notifications/{id}/read         → markAsRead()
 */
class NotificationsController extends Controller
{
    private ?NotificationService $notificationService = null;

    private function getNotificationService(): NotificationService
    {
        if ($this->notificationService === null) {
            $this->notificationService = new NotificationService();
        }
        return $this->notificationService;
    }

    // =========================================================================
    // СПИСОК УВЕДОМЛЕНИЙ
    // =========================================================================

    public function index(): void
    {
        $userId = (int)$_SESSION['user_id'];

        $type = (string)$this->request->getParams('type', 'all');
        $page = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config_int('constants.pagination.notifications_per_page', 25);

        $data = $this->getNotificationService()->getNotificationsForIndex(
            $userId,
            $type,
            $page,
            $perPage
        );

        $this->render('index', [
            'title' => 'Уведомления',
            'notifications' => $data['notifications'],
            'currentType' => $data['currentType'],
            'counts' => $data['counts'],
            'totalUnread' => $data['totalUnread'],
            'currentPage' => $page,
            'request' => $this->request,
        ]);
    }

    // =========================================================================
    // ОТМЕТКА ОДНОГО УВЕДОМЛЕНИЯ КАК ПРОЧИТАННОГО
    // Маршрут: POST /notifications/{id}/read
    // JS отправляет CSRF в заголовке X-CSRF-Token
    // =========================================================================

    public function markAsRead(string $id): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Проверяем CSRF из заголовка (JS отправляет его именно так)
        if (!$this->validateCsrfFromHeader()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Ошибка CSRF']);
            exit;
        }

        $notificationId = (int)$id;
        $userId = (int)Auth::id();

        try {
            $success = $this->getNotificationService()->markAsRead($notificationId, $userId);

            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Отмечено как прочитанное' : 'Не удалось отметить'
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
        }

        exit;
    }

    // =========================================================================
    // ОТМЕТКА ВСЕХ УВЕДОМЛЕНИЙ КАК ПРОЧИТАННЫХ
    // Маршрут: POST /notifications/mark-all-read
    // JS отправляет CSRF в заголовке X-CSRF-Token
    // =========================================================================

    public function markAllAsRead(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Проверяем CSRF из заголовка
        if (!$this->validateCsrfFromHeader()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Ошибка CSRF']);
            exit;
        }

        $userId = (int)Auth::id();

        try {
            $success = $this->getNotificationService()->markAllAsRead($userId);

            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Все уведомления отмечены' : 'Ошибка'
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
        }

        exit;
    }

    // =========================================================================
    // API: СЧЁТЧИК НЕПРОЧИТАННЫХ
    // Маршрут: GET /api/notifications/count
    // JS ожидает: { count: number }
    // =========================================================================

    public function getCount(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $userId = (int)Auth::id();
            $count = $this->getNotificationService()->getUnreadCount($userId);

            // JS ожидает именно { count: N } — без лишних полей
            echo json_encode(['count' => $count]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['count' => 0]);
        }

        exit;
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Проверить CSRF-токен из заголовка X-CSRF-Token.
     * JS отправляет токен именно так (см. fetch в notifications.js).
     */
    private function validateCsrfFromHeader(): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (empty($sessionToken) || empty($headerToken)) {
            return false;
        }

        return hash_equals($sessionToken, $headerToken);
    }
}