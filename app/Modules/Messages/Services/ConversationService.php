<?php

namespace App\Modules\Messages\Services;

use App\Modules\Messages\Models\Conversation;
use App\Modules\Users\Models\User;
use App\Core\Session;

/**
 * Сервис для управления диалогами между пользователями.
 */
class ConversationService
{
    private Conversation $conversationModel;
    private User $userModel;
    private Session $session;

    public function __construct(
        Conversation $conversationModel,
        User $userModel,
        Session $session
    ) {
        $this->conversationModel = $conversationModel;
        $this->userModel = $userModel;
        $this->session = $session;
    }

    /**
     * Получить список всех диалогов пользователя с последним сообщением.
     */
    public function getUserConversations(int $userId): array
    {
        return $this->conversationModel->getUserConversations($userId);
    }

    /**
     * Получить диалог по ID с проверкой прав доступа.
     */
    public function getConversationWithAccessCheck(int $conversationId, int $userId): ?array
    {
        $chatRoom = $this->conversationModel->find($conversationId);

        if (!$chatRoom) {
            return null;
        }

        if ((int)$chatRoom['user_one'] !== $userId && (int)$chatRoom['user_two'] !== $userId) {
            return null;
        }

        return $chatRoom;
    }

    /**
     * Получить собеседника (другого участника диалога).
     */
    public function getConversationPartner(int $conversationId, int $currentUserId): ?array
    {
        $chatRoom = $this->conversationModel->find($conversationId);

        if (!$chatRoom) {
            return null;
        }

        $partnerId = ((int)$chatRoom['user_one'] === $currentUserId)
            ? (int)$chatRoom['user_two']
            : (int)$chatRoom['user_one'];

        return $this->userModel->find($partnerId);
    }

    /**
     * Создать новый диалог или получить существующий между двумя пользователями.
     */
    public function getOrCreateConversation(int $userOneId, int $userTwoId): ?int
    {
        if ($userOneId === $userTwoId) {
            $this->session->flash('error', 'Нельзя создать диалог с самим собой');
            return null;
        }

        try {
            return $this->conversationModel->firstOrCreate($userOneId, $userTwoId);
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Ошибка при создании диалога');
            return null;
        }
    }

    /**
     * Обновить timestamp диалога (для сортировки по активности).
     */
    public function touchConversation(int $conversationId): void
    {
        $this->conversationModel->update($conversationId, [
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Получить ID получателя сообщения в диалоге.
     */
    public function getRecipientId(int $conversationId, int $senderId): int
    {
        return $this->conversationModel->getRecipientId($conversationId, $senderId);
    }
}