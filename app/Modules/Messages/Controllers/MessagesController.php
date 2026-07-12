<?php

declare(strict_types=1);

namespace App\Modules\Messages\Controllers;

use App\Core\Controller;
use App\Modules\Messages\Services\ConversationService;
use App\Modules\Messages\Services\MessageService;

/**
 * Контроллер личных сообщений.
 * 
 * Обрабатывает:
 * - Список диалогов пользователя
 * - Просмотр диалога с пагинацией
 * - Отправку сообщений
 * - Создание новых диалогов
 * 
 * Все маршруты защищены middleware auth.
 */
class MessagesController extends Controller
{
    // =========================================================================
    // СПИСОК ДИАЛОГОВ
    // =========================================================================

    /**
     * Список всех диалогов текущего пользователя
     */
    public function index(): void
    {
        $userContext = $this->getUserContext();
        $chats = $this->service(ConversationService::class)->getUserConversations($userContext['id']);

        $this->render('index', [
            'title' => 'Мои диалоги',
            'chats' => $chats
        ]);
    }

    // =========================================================================
    // ПРОСМОТР ДИАЛОГА
    // =========================================================================

    /**
     * Просмотр диалога с пагинацией сообщений
     */
    public function showDialog(string $id): void
    {
        $conversationId = (int)$id;

        $userContext = $this->getUserContext();

        $chatRoom = $this->service(ConversationService::class)->getConversationWithAccessCheck($conversationId, $userContext['id']);
        if (!$chatRoom) {
            $this->redirectBack('/messages');
            return; // Добавлен return для ясности (хотя redirect бросает исключение)
        }

        $this->service(MessageService::class)->markAsRead($conversationId, $userContext['id']);

        $currentPage = max(1, (int)$this->request->getParams('chat_page', 1));
        $perPage = config('pagination.messages_per_page', 15, 'int');

        $messagesData = $this->service(MessageService::class)->getPaginatedMessages($conversationId, $currentPage, $perPage);
        $recipient = $this->service(ConversationService::class)->getConversationPartner($conversationId, $userContext['id']);

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

    /**
     * Отправка сообщения в диалог
     */
    public function sendMessage(): void
    {
        $conversationId = (int)$this->request->getParams('conversation_id');
        $messageText = $this->request->getParams('message_text');

        $userContext = $this->getUserContext();

        $this->service(MessageService::class)->sendMessage($conversationId, $userContext['id'], $messageText);

        $this->redirect('/messages/chat/' . $conversationId);
    }

    // =========================================================================
    // СОЗДАНИЕ НОВОГО ДИАЛОГА
    // =========================================================================

    /**
     * Создание нового диалога с пользователем
     */
    public function startConversation(string $userId): void
    {
        $userContext = $this->getUserContext();
        $targetUid = (int)$userId;

        $roomId = $this->service(ConversationService::class)->getOrCreateConversation($userContext['id'], $targetUid);

        if ($roomId === null) {
            $this->redirect('/messages');
            return;
        }

        $this->redirect('/messages/chat/' . $roomId);
    }
}
