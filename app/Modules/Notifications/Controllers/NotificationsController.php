<?php

namespace App\Modules\Notifications\Controllers;

use App\Core\Controller;
use App\Modules\Auth\Services\Auth;
use App\Modules\Notifications\Services\NotificationService;

/**
 * Контроллер модуля Notifications.
 * 
 * Маршруты:
 * - GET  /notifications                   → index()
 * - GET  /api/notifications/count         → getCount()
 * - POST /notifications/mark-all-read     → markAllAsRead()
 * - POST /notifications/{id}/read         → markAsRead()
 */
class NotificationsController extends Controller
{
    // =========================================================================
    // СПИСОК УВЕДОМЛЕНИЙ
    // =========================================================================

    public function index(): void
    {
        $userId = (int)$_SESSION['user_id'];

        $type = (string)$this->request->getParams('type', 'all');
        $page = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.notifications_per_page', 25, 'int');

        $data = $this->service(NotificationService::class)->getNotificationsForIndex(
            $userId, $type, $page, $perPage
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
    // =========================================================================

    public function markAsRead(string $id): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->validateCsrfFromHeader()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Ошибка CSRF']);
            exit;
        }

        $notificationId = (int)$id;
        $userId = (int)Auth::id();

        try {
            $success = $this->service(NotificationService::class)->markAsRead($notificationId, $userId);

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
    // =========================================================================

    public function markAllAsRead(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->validateCsrfFromHeader()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Ошибка CSRF']);
            exit;
        }

        $userId = (int)Auth::id();

        try {
            $success = $this->service(NotificationService::class)->markAllAsRead($userId);

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
    // =========================================================================

    public function getCount(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $userId = (int)Auth::id();
            $count = $this->service(NotificationService::class)->getUnreadCount($userId);

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