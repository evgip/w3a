<?php

namespace App\Modules\Notifications\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Session;
use App\Core\Request;
use App\Modules\Notifications\Models\Notification;

class NotificationsController extends Controller
{
    /**
     * Список всех уведомлений пользователя
     */
    public function index(): void
    {
        if (!Auth::check()) {
            Session::setFlash('error', 'Пожалуйста, войдите в систему.');
            header('Location: /login');
            exit;
        }

        $request = new Request();
        $page = max(1, (int)$request->getParams('page', 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $notificationModel = new Notification();
        $userId = (int)$_SESSION['user_id'];

        $notifications = $notificationModel->getUserNotifications($userId, $perPage, $offset);
        $unreadCount = $notificationModel->getUnreadCount($userId);

        // Подсчитываем общее количество для пагинации
        $totalCount = count($notificationModel->getUserNotifications($userId, 1000, 0));
        $totalPages = (int)ceil($totalCount / $perPage);

        $this->render('index', [
            'title' => 'Уведомления',
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'currentPage' => $page,
            'totalPages' => $totalPages
        ]);
    }

    /**
     * API: Получить только непрочитанные уведомления (для AJAX)
     */
    public function unread(): void
    {
        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $notificationModel = new Notification();
        $userId = (int)$_SESSION['user_id'];

        $unread = $notificationModel->getUnreadNotifications($userId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'count' => count($unread),
            'notifications' => $unread
        ]);
        exit;
    }

    /**
     * Пометить одно уведомление как прочитанное
     */
    public function markAsRead(string $id): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        $request = new Request();
        $request->validateCsrf();

        $notificationId = (int)$id;
        $userId = (int)$_SESSION['user_id'];

        $notificationModel = new Notification();
        $notification = $notificationModel->find($notificationId);

        // Проверяем, что уведомление принадлежит текущему пользователю
        if ($notification && (int)$notification['user_id'] === $userId) {
            $notificationModel->markAsRead($notificationId, $userId);
            Session::setFlash('success', 'Уведомление отмечено как прочитанное');
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/notifications'));
        exit;
    }

    /**
     * Пометить все уведомления как прочитанные
     */
    public function markAllRead(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        $request = new Request();
        $request->validateCsrf();

        $notificationModel = new Notification();
        $userId = (int)$_SESSION['user_id'];

        $notificationModel->markAllAsRead($userId);
        Session::setFlash('success', 'Все уведомления отмечены как прочитанные');

        header('Location: /notifications');
        exit;
    }

    /**
     * API: Получить количество непрочитанных уведомлений (для счётчика в шапке)
     */
    public function count(): void
    {
        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['count' => 0]);
            exit;
        }

        $notificationModel = new Notification();
        $userId = (int)$_SESSION['user_id'];
        $count = $notificationModel->getUnreadCount($userId);

        header('Content-Type: application/json');
        echo json_encode(['count' => $count]);
        exit;
    }
}