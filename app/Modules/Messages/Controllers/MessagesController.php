<?php

namespace App\Modules\Messages\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Session;
use App\Core\Validator;
use App\Modules\Messages\Models\Conversation;
use App\Modules\Messages\Models\Message;

class MessagesController extends Controller
{
    public function __construct()
    {
        if (!Auth::check()) {
            Session::setFlash('error', 'Пожалуйста, авторизуйтесь для доступа к сообщениям.');
            header('Location: /login');
            exit;
        }
    }

    /**
     * Displays user conversations overview panel (GET /messages)
     */
    public function index(): void
    {
        $userId = (int)$_SESSION['user_id'];
        $convModel = new Conversation();

        $this->render('index', [
            'title' => 'Мои диалоги',
            'chats' => $convModel->getUserConversations($userId)
        ]);
    }

    /**
     * Opens a specific dialogue room thread with structural pagination (GET /messages/chat/{id})
     */
    public function showDialog(string $id): void
    {
        $conversationId = (int)$id;
        $userId = (int)$_SESSION['user_id'];

        $convModel = new Conversation();
        $chatRoom = $convModel->find($conversationId);

        if (!$chatRoom || ((int)$chatRoom['user_one'] !== $userId && (int)$chatRoom['user_two'] !== $userId)) {
            header('Location: /messages');
            exit;
        }

        $request = new Request();
        $msgModel = new Message();
        $msgModel->markAsRead($conversationId, $userId);

        // --- PAGINATION MATHEMATICS ENGINE ---
        // Fetch current page from URL parameter query state (Default to page 1)
        $currentPage = (int)$request->getParams('chat_page', 1);
        if ($currentPage < 1) {
            $currentPage = 1;
        }

        $perPage = config_int('pagination.messages_per_page', 15);; // Load messages in batches of 15 rows
        $totalMessages = $msgModel->getTotalMessageCount($conversationId);
        $totalPages = (int)ceil($totalMessages / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }

        // Calculate offset based on current page
        $offset = ($currentPage - 1) * $perPage;

        // Fetch the paginated historical chunk chronologically
        $messages = $msgModel->getPaginatedChatHistory($conversationId, $perPage, $offset);

        $targetUid = ((int)$chatRoom['user_one'] === $userId) ? (int)$chatRoom['user_two'] : (int)$chatRoom['user_one'];
        $userModel = new \App\Modules\Users\Models\User();
        $recipient = $userModel->find($targetUid);

        $this->render('dialog', [
            'title'          => 'Чат с ' . e($recipient['username']),
            'messages'       => $messages,
            'recipient'      => $recipient,
            'conversationId' => $conversationId,
            'currentPage'    => $currentPage,
            'totalPages'     => $totalPages,
            'request'        => $request
        ]);
    }

    /**
     * Process message persistence delivery pipelines (POST /messages/send)
     */
    public function sendMessage(): void
    {
        $request = new Request();
        $request->validateCsrf();

        $conversationId = (int)$request->getParams('conversation_id');
        $messageText    = trim($request->getParams('message_text'));
        $userId         = (int)$_SESSION['user_id'];

        $validator = new Validator();
        if (!$validator->validate(['text' => $messageText], ['text' => 'required'])) {
            header('Location: /messages/chat/' . $conversationId);
            exit;
        }

        $msgModel = new Message();
        $messageId = $msgModel->create([
            'conversation_id' => $conversationId,
            'sender_id'       => $userId,
            'message'         => $messageText
        ]);

	   // Touch the parent conversation row column updated_at attribute to bump position sorting rankings
		$convModel = new Conversation();
		$convModel->update($conversationId, ['updated_at' => date('Y-m-d H:i:s')]);

		// Отправляем уведомление получателю
		if ($messageId > 0) {
			// Получаем recipient_id через метод модели
			$recipientId = $convModel->getRecipientId($conversationId, $userId);
			
			if ($recipientId > 0) {
				$notificationService = new \App\Modules\Notifications\Services\NotificationService();
				$notificationService->notifyMessageSent($messageId, $recipientId, $userId);
			}
		}

        header('Location: /messages/chat/' . $conversationId);
        exit;
    }

    /**
     * Action parameter target hook routing chat initializations via Profile page buttons (POST /messages/start/{userId})
     */
    public function startConversation(string $userId): void
    {
        $request = new Request();
        $request->validateCsrf();

        $myId = (int)$_SESSION['user_id'];
        $targetUid = (int)$userId;

        if ($myId === $targetUid) {
            header('Location: /messages');
            exit;
        }

        $convModel = new Conversation();
        $roomId = $convModel->firstOrCreate($myId, $targetUid);

        header('Location: /messages/chat/' . $roomId);
        exit;
    }
}


