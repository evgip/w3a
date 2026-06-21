<?php

namespace App\Modules\Messages\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Session;
use App\Core\Validator;
use App\Modules\Messages\Models\Conversation;
use App\Modules\Messages\Models\Message;
use App\Modules\Messages\Services\ConversationService;
use App\Modules\Messages\Services\MessageService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Stories\Models\Comment;
use App\Modules\Users\Models\User;

class MessagesController extends Controller
{
    private ?ConversationService $conversationService = null;
    private ?MessageService $messageService = null;

    // =========================================================================
    // ЛЕНИВЫЕ ГЕТТЕРЫ СЕРВИСОВ
    // =========================================================================

    private function getConversationService(): ConversationService
    {
        if ($this->conversationService === null) {
            $this->conversationService = new ConversationService(
                new Conversation(),
                new User()
            );
        }
        return $this->conversationService;
    }

    private function getMessageService(): MessageService
    {
        if ($this->messageService === null) {
            $this->messageService = new MessageService(
                new Message(),
                new Conversation(),
                new NotificationService()
            );
        }
        return $this->messageService;
    }

    // =========================================================================
    // СПИСОК ДИАЛОГОВ
    // =========================================================================

    public function index(): void
    {
        $userId = (int)$_SESSION['user_id'];
        $chats = $this->getConversationService()->getUserConversations($userId);

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
        $userId = (int)$_SESSION['user_id'];

        $chatRoom = $this->getConversationService()->getConversationWithAccessCheck($conversationId, $userId);
        if (!$chatRoom) {
            header('Location: /messages');
            exit;
        }

        $this->getMessageService()->markAsRead($conversationId, $userId);

        $currentPage = max(1, (int)$this->request->getParams('chat_page', 1));
        $perPage = config_int('pagination.messages_per_page', 15);

        $messagesData = $this->getMessageService()->getPaginatedMessages($conversationId, $currentPage, $perPage);
        $recipient = $this->getConversationService()->getConversationPartner($conversationId, $userId);

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
        $messageText = trim($this->request->getParams('message_text'));
        $userId = (int)$_SESSION['user_id'];

        $validator = new Validator();
        if (!$validator->validate(['text' => $messageText], ['text' => 'required'])) {
            header('Location: /messages/chat/' . $conversationId);
            exit;
        }

        try {
            $this->getMessageService()->sendMessage($conversationId, $userId, $messageText);
        } catch (\InvalidArgumentException $e) {
            Session::setFlash('error', $e->getMessage());
        }

        header('Location: /messages/chat/' . $conversationId);
        exit;
    }

    // =========================================================================
    // СОЗДАНИЕ НОВОГО ДИАЛОГА
    // =========================================================================

    public function startConversation(string $userId): void
    {
        $myId = (int)$_SESSION['user_id'];
        $targetUid = (int)$userId;

        try {
            $roomId = $this->getConversationService()->getOrCreateConversation($myId, $targetUid);
            header('Location: /messages/chat/' . $roomId);
        } catch (\InvalidArgumentException $e) {
            Session::setFlash('error', $e->getMessage());
            header('Location: /messages');
        }

        exit;
    }
}