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
 * ✅ ИЗМЕНЕНО: Session внедряется через конструктор.
 */
class ModerationService
{
    private Moderation $moderationModel;
    private ModNote $modNoteModel;
    private User $userModel;
    private Session $session;
    private ?EventDispatcher $eventDispatcher;

    /**
     * ✅ ИЗМЕНЕНО: Добавлен Session в конструктор
     */
    public function __construct(
        Moderation $moderationModel,
        ModNote $modNoteModel,
        User $userModel,
        Session $session,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->moderationModel = $moderationModel;
        $this->modNoteModel = $modNoteModel;
        $this->userModel = $userModel;
        $this->session = $session;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function addNote(int $targetUserId, int $moderatorId, string $noteText, int $isPrivate = 1): ?array
    {
        $noteText = trim($noteText);

        if ($targetUserId <= 0 || $noteText === '') {
            $this->session->flash('error', 'Укажите пользователя и текст заметки.');
            return null;
        }

        $noteId = $this->modNoteModel->create([
            'user_id'      => $targetUserId,
            'moderator_id' => $moderatorId,
            'note'         => $noteText,
            'is_private'   => $isPrivate,
        ]);

        if (!$noteId) {
            $this->session->flash('error', 'Не удалось сохранить заметку.');
            return null;
        }

        $this->session->flash('success', 'Заметка добавлена.');

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

    public function deleteNote(int $noteId): bool
    {
        if ($noteId <= 0) {
            $this->session->flash('error', 'Некорректный ID заметки.');
            return false;
        }

        $this->modNoteModel->deleteNote($noteId);
        $this->session->flash('success', 'Заметка удалена.');

        return true;
    }

    public function banUser(int $targetUserId, int $moderatorId, string $reason): ?array
    {
        if ($targetUserId === $moderatorId) {
            $this->session->flash('error', 'Вы не можете применить это действие к себе.');
            return null;
        }

        $targetUser = $this->userModel->find($targetUserId);
        if (!$targetUser) {
            $this->session->flash('error', 'Пользователь не найден.');
            return null;
        }

        $this->moderationModel->banUser($targetUserId, $moderatorId, $reason);

        $this->dispatchEvent(new UserBanned(
            $targetUserId,
            $moderatorId,
            $reason ?: 'Без указания причины'
        ));

        $this->session->flash('success', "Пользователь «{$targetUser['username']}» забанен.");

        return [
            'user'     => $targetUser,
            'username' => $targetUser['username'],
        ];
    }

    public function unbanUser(int $targetUserId, int $moderatorId): ?array
    {
        if ($targetUserId === $moderatorId) {
            $this->session->flash('error', 'Вы не можете применить это действие к себе.');
            return null;
        }

        $targetUser = $this->userModel->find($targetUserId);
        if (!$targetUser) {
            $this->session->flash('error', 'Пользователь не найден.');
            return null;
        }

        $this->moderationModel->unbanUser($targetUserId);

        $this->dispatchEvent(new UserUnbanned(
            $targetUserId,
            $moderatorId,
            'Разбан пользователя'
        ));

        $this->session->flash('success', "Пользователь «{$targetUser['username']}» разбанен.");

        return [
            'user'     => $targetUser,
            'username' => $targetUser['username'],
        ];
    }

    private function dispatchEvent(\App\Core\Events\Event $event): void
    {
        if ($this->eventDispatcher === null) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}