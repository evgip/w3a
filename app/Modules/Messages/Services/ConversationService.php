<?php

declare(strict_types=1);

namespace App\Modules\Messages\Services;

use App\Modules\Messages\Models\Conversation;
use App\Modules\Users\Models\User;
use App\Modules\Messages\Exceptions\ConversationException;

/**
 * Сервис для управления диалогами между пользователями.
 * Не зависит от HTTP или сессий, выполняет только бизнес-логику.
 */
class ConversationService
{
    private Conversation $conversationModel;
    private User $userModel;

    public function __construct(
        Conversation $conversationModel,
        User $userModel
    ) {
        $this->conversationModel = $conversationModel;
        $this->userModel = $userModel;
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
     *
     * @throws ConversationException Если пользователи совпадают или произошла ошибка БД
     */
    public function getOrCreateConversation(int $userOneId, int $userTwoId): int
    {
        if ($userOneId === $userTwoId) {
            throw new ConversationException('Нельзя создать диалог с самим собой');
        }

        try {
            return $this->conversationModel->firstOrCreate($userOneId, $userTwoId);
        } catch (\Throwable $e) {
            throw new ConversationException('Ошибка при создании диалога');
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