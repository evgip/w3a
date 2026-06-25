<?php

declare(strict_types=1);

namespace App\Modules\Moderations\Services;

use App\Modules\Moderations\Models\ModNote;
use App\Modules\Moderations\Models\Moderation;
use App\Modules\Users\Models\User;
use App\Core\Session;
use App\Core\Events\EventDispatcher;
use App\Core\Events\UserBanned;
use App\Core\Events\UserUnbanned;
use App\Core\Events\ModNoteAdded;

/**
 * Сервис для работы с модерацией.
 *
 * Отвечает за:
 *  - Бан/разбан пользователей
 *  - Модераторские заметки
 *  - Диспатч событий для аудита
 *
 * Вся бизнес-логика (проверки, создание, события, flash-сообщения) находится здесь.
 * Контроллер только получает параметры и делает редирект.
 */
class ModerationService
{
    private Moderation $moderationModel;
    private ModNote $modNoteModel;
    private User $userModel;
    private ?EventDispatcher $eventDispatcher;

    public function __construct(
        Moderation $moderationModel,
        ModNote $modNoteModel,
        User $userModel,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->moderationModel = $moderationModel;
        $this->modNoteModel = $modNoteModel;
        $this->userModel = $userModel;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Добавляет модераторскую заметку к пользователю.
     *
     * @param int    $targetUserId   ID пользователя, к которому добавляется заметка
     * @param int    $moderatorId    ID модератора
     * @param string $noteText       Текст заметки
     * @param int    $isPrivate      Приватность заметки (1 = только для модераторов)
     * @return array|null Данные созданной заметки или null при ошибке
     */
    public function addNote(int $targetUserId, int $moderatorId, string $noteText, int $isPrivate = 1): ?array
    {
        $noteText = trim($noteText);

        if ($targetUserId <= 0 || $noteText === '') {
            Session::setFlash('error', 'Укажите пользователя и текст заметки.');
            return null;
        }

        // Сохраняем заметку
        $noteId = $this->modNoteModel->create([
            'user_id'      => $targetUserId,
            'moderator_id' => $moderatorId,
            'note'         => $noteText,
            'is_private'   => $isPrivate,
        ]);

        if (!$noteId) {
            Session::setFlash('error', 'Не удалось сохранить заметку.');
            return null;
        }

        Session::setFlash('success', 'Заметка добавлена.');

        // Диспатчим событие для аудита (AuditListener запишет в audit_logs)
        $this->dispatchEvent(new ModNoteAdded(
            $moderatorId,
            $targetUserId,
            mb_substr($noteText, 0, 200)
        ));

        return [
            'note_id' => $noteId,
            'user_id' => $targetUserId,
        ];
    }

    /**
     * Удаляет модераторскую заметку.
     *
     * @param int $noteId ID заметки
     * @return bool Успешно ли удалено
     */
    public function deleteNote(int $noteId): bool
    {
        if ($noteId <= 0) {
            Session::setFlash('error', 'Некорректный ID заметки.');
            return false;
        }

        $this->modNoteModel->deleteNote($noteId);
        Session::setFlash('success', 'Заметка удалена.');

        // Опционально: можно добавить событие ModNoteDeleted
        // $this->dispatchEvent(new ModNoteDeleted($noteId));

        return true;
    }

    /**
     * Банит пользователя.
     *
     * Выполняет:
     *  - Проверку, что модератор не банит себя
     *  - Проверку существования целевого пользователя
     *  - Бан в модели Moderation
     *  - Диспатч события UserBanned (AuditListener запишет в аудит)
     *  - Установка flash-сообщения
     *
     * @param int    $targetUserId    ID пользователя для бана
     * @param int    $moderatorId     ID модератора
     * @param string $reason          Причина бана
     * @return array|null Данные пользователя при успехе, null при ошибке
     */
    public function banUser(int $targetUserId, int $moderatorId, string $reason): ?array
    {
        // Нельзя банить себя
        if ($targetUserId === $moderatorId) {
            Session::setFlash('error', 'Вы не можете применить это действие к себе.');
            return null;
        }

        // Проверяем существование пользователя
        $targetUser = $this->userModel->find($targetUserId);
        if (!$targetUser) {
            Session::setFlash('error', 'Пользователь не найден.');
            return null;
        }

        // Бан в модели
        $this->moderationModel->banUser($targetUserId, $moderatorId, $reason);

        // Диспатчим событие для аудита
        $this->dispatchEvent(new UserBanned(
            $targetUserId,
            $moderatorId,
            $reason ?: 'Без указания причины'
        ));

        Session::setFlash('success', "Пользователь «{$targetUser['username']}» забанен.");

        return [
            'user'     => $targetUser,
            'username' => $targetUser['username'],
        ];
    }

    /**
     * Разбанивает пользователя.
     *
     * Выполняет:
     *  - Проверку, что модератор не разбанивает себя
     *  - Проверку существования целевого пользователя
     *  - Разбан в модели Moderation
     *  - Диспатч события UserUnbanned (AuditListener запишет в аудит)
     *  - Установка flash-сообщения
     *
     * @param int $targetUserId ID пользователя для разбана
     * @param int $moderatorId  ID модератора
     * @return array|null Данные пользователя при успехе, null при ошибке
     */
    public function unbanUser(int $targetUserId, int $moderatorId): ?array
    {
        // Нельзя разбанивать себя
        if ($targetUserId === $moderatorId) {
            Session::setFlash('error', 'Вы не можете применить это действие к себе.');
            return null;
        }

        $targetUser = $this->userModel->find($targetUserId);
        if (!$targetUser) {
            Session::setFlash('error', 'Пользователь не найден.');
            return null;
        }

        $this->moderationModel->unbanUser($targetUserId);

        $this->dispatchEvent(new UserUnbanned(
            $targetUserId,
            $moderatorId,
            'Разбан пользователя'
        ));

        Session::setFlash('success', "Пользователь «{$targetUser['username']}» разбанен.");

        return [
            'user'     => $targetUser,
            'username' => $targetUser['username'],
        ];
    }

    /**
     * Безопасно диспатчит событие через EventDispatcher.
     *
     * @param \App\Core\Events\Event $event Событие для диспатча
     */
    private function dispatchEvent(\App\Core\Events\Event $event): void
    {
        if ($this->eventDispatcher === null) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}