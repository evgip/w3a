<?php

namespace App\Modules\Messages\Controllers;

use App\Core\Controller;
use App\Modules\Messages\Services\ConversationService;
use App\Modules\Messages\Services\MessageService;
use App\Modules\Auth\Services\Auth;

class MessagesController extends Controller
{
    // =========================================================================
    // СПИСОК ДИАЛОГОВ
    // =========================================================================

    public function index(): void
    {
        $userId = Auth::id();
        $chats = $this->service(ConversationService::class)->getUserConversations($userId);

        $this->render('index', [
            'title' => 'Мои диалоги',
            'chats' => $chats
        ]);
    }

    // =========================================================================
    // ПРОСМОТР ДИАЛОГА
    // =========================================================================

    public function showDialog(string $id): void
    {
        $conversationId = (int)$id;
        $userId = Auth::id();

        $chatRoom = $this->service(ConversationService::class)->getConversationWithAccessCheck($conversationId, $userId);
        if (!$chatRoom) {
            $this->redirectBack('/messages');
        }

        $this->service(MessageService::class)->markAsRead($conversationId, $userId);

        $currentPage = max(1, (int)$this->request->getParams('chat_page', 1));
        $perPage = config('pagination.messages_per_page', 15, 'int');

        $messagesData = $this->service(MessageService::class)->getPaginatedMessages($conversationId, $currentPage, $perPage);
        $recipient = $this->service(ConversationService::class)->getConversationPartner($conversationId, $userId);

        $this->render('dialog', [
            'title' => 'Чат с ' . e($recipient['username']),
            'messages' => $messagesData['messages'],
            'recipient' => $recipient,
            'conversationId' => $conversationId,
            'currentPage' => $messagesData['currentPage'],
            'totalPages' => $messagesData['totalPages'],
            'request' => $this->request
        ]);
    }

    // =========================================================================
    // ОТПРАВКА СООБЩЕНИЯ
    // =========================================================================

	public function sendMessage(): void
	{
		$conversationId = (int)$this->request->getParams('conversation_id');
		$messageText = $this->request->getParams('message_text');
		$userId = Auth::id();

		$this->service(MessageService::class)->sendMessage($conversationId, $userId, $messageText);

		$this->redirect('/messages/chat/' . $conversationId);
	}

    // =========================================================================
    // СОЗДАНИЕ НОВОГО ДИАЛОГА
    // =========================================================================

	public function startConversation(string $userId): void
	{
		$myId = Auth::id();
		$targetUid = (int)$userId;

		$roomId = $this->service(ConversationService::class)->getOrCreateConversation($myId, $targetUid);

		if ($roomId === null) {
			$this->redirect('/messages');
			return;
		}

		$this->redirect('/messages/chat/' . $roomId);
	}
}