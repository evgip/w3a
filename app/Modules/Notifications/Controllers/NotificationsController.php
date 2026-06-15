<?php

namespace App\Modules\Notifications\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Modules\Notifications\Models\Notification;

class NotificationsController extends Controller
{
    public function __construct()
    {
        // Проверка авторизации (как в MessagesController)
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
    }
    
    public function index(): void
    {
        $userId = (int)$_SESSION['user_id'];
        $notificationModel = new Notification();
        
        // Получаем тип фильтра из GET-параметра (по умолчанию 'all')
        $type = $_GET['type'] ?? 'all';
        $allowedTypes = ['all', 'reply', 'mention', 'message'];
        
        if (!in_array($type, $allowedTypes)) {
            $type = 'all';
        }
        
		 $page = (int)($_GET['page'] ?? 1);
		 $limit = 50;
		
        // Получаем уведомления с учетом фильтра
		$notifications = $notificationModel->getUserNotifications(
			$_SESSION['user_id'],
			$type,
			$limit,
			$page
		);
		
        // Получаем количество непрочитанных по типам для бейджей на вкладках
        $unreadCounts = $notificationModel->getUnreadCountByType($userId);
        $counts = ['reply' => 0, 'mention' => 0, 'message' => 0];
        foreach ($unreadCounts as $row) {
            if (isset($counts[$row['type']])) {
                $counts[$row['type']] = (int)$row['count'];
            }
        }
        
        // Общее количество непрочитанных
        $totalUnread = $notificationModel->getUnreadCount($userId);
        
        $this->render('index', [
            'notifications' => $notifications,
            'currentType' => $type,
            'counts' => $counts,
            'totalUnread' => $totalUnread
        ]);
    }
    
	public function markAsRead(int $id): void
	{
		$userId = (int)$_SESSION['user_id'];
		$notificationId = $id;  // ← Берем из URL, а не из POST
		
		if ($notificationId) {
			$notificationModel = new Notification();
			$notificationModel->markAsRead($notificationId, $userId);
		}
		
		$this->json(['success' => true]);
	}
	
    public function markAllAsRead(): void
    {
        $userId = (int)$_SESSION['user_id'];
        
        $notificationModel = new Notification();
        $notificationModel->markAllAsRead($userId);
        
        $this->json(['success' => true]);
    }
    
    /**
     * Получить количество непрочитанных уведомлений (AJAX)
     */
    public function getCount(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Если пользователь не авторизован - возвращаем 0
        if (!Auth::check()) {
            echo json_encode([
                'success' => true,
                'count' => 0,
                'authenticated' => false
            ]);
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $notificationModel = new Notification();
        $count = $notificationModel->countUnread($userId);

        echo json_encode([
            'success' => true,
            'count' => $count,
            'authenticated' => true
        ]);
        exit;
    }
}