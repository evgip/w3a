<?php

namespace App\Modules\Moderations\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Session;
use App\Modules\Moderations\Models\ModNote;
use App\Modules\Moderations\Models\Moderation;
use App\Modules\Moderations\Models\ModActivity;
use App\Modules\Admin\Models\AuditLog;  // ✅ Добавляем импорт
use App\Core\Events\UserBanned;         // ✅ Для событий
use App\Core\Events\UserUnbanned;
use App\Core\Events\ModNoteAdded;

class ModerationsController extends Controller
{
    // ==========================================
    // /mod/log — Публичный лог модерации
    // ==========================================
    public function log(): void
    {
        $page = max(1, (int)$this->request->query('page', 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;
        
        // ✅ Используем AuditLog с фильтром по категории 'moderation'
        $auditLog = new AuditLog();
        $items = $auditLog->getByCategory('moderation', $perPage, $offset);
        $total = $auditLog->countByCategory('moderation');
        $pages = max(1, (int)ceil($total / $perPage));
        
        // Декодируем payload для шаблона
        foreach ($items as &$item) {
            $item['decoded_payload'] = !empty($item['payload']) 
                ? json_decode($item['payload'], true) 
                : [];
        }

        $this->render('log', [
            'title'        => 'Лог модерации',
            'items'        => $items,
            'total'        => $total,
            'pages'        => $pages,
            'current_page' => $page,
        ]);
    }

    // ==========================================
    // /mod/notes — Приватные заметки модераторов
    // ==========================================
    public function notes(): void
    {
        $model = new ModNote();
        $notes = $model->getRecentNotes(100);

        // ✅ Используем $this->request->query()
        $targetUserId = $this->request->query('user_id') !== '' 
            ? (int)$this->request->query('user_id') 
            : null;

        $this->render('notes', [
            'title' => 'Модераторские заметки',
            'notes' => $notes,
            'target_user_id' => $targetUserId,
        ]);
    }

    // ==========================================
    // POST /mod/notes/store — Добавить заметку
    // ==========================================
    public function storeNote(): void
    {
        $userId = (int)$this->request->post('user_id');
        $noteText = trim($this->request->post('note') ?? '');
        $isPrivate = (int)($this->request->post('is_private') ?? 1);
        $currentUserId = Auth::id();

        if ($userId <= 0 || $noteText === '') {
            Session::setFlash('error', 'Укажите пользователя и текст заметки.');
            $this->redirect('/mod/notes');
            return;
        }

        $model = new ModNote();
        $model->create([
            'user_id'      => $userId,
            'moderator_id' => $currentUserId,
            'note'         => $noteText,
            'is_private'   => $isPrivate,
        ]);

        // Отправляем событие вместо logAction()
        $this->dispatch(new ModNoteAdded(
            $currentUserId,
            $userId,
            mb_substr($noteText, 0, 200)
        ));

        Session::setFlash('success', 'Заметка добавлена.');
        $this->redirect('/mod/notes');
    }

    // ==========================================
    // POST /mod/notes/{id}/delete — Удалить заметку
    // ==========================================
    public function deleteNote(string $id): void
    {
        // ✅ CSRF уже проверен в middleware
        // $this->request->validateCsrf();

        $model = new ModNote();
        $model->deleteNote((int)$id);

        Session::setFlash('success', 'Заметка удалена.');
        $this->redirect('/mod/notes');
    }

    // ==========================================
    // /mod/stats — Статистика активности (только админ/мод)
    // ==========================================
    public function stats(): void
    {
        $activity = new ModActivity();

        $this->render('stats', [
            'title'       => 'Активность модераторов',
            'stats'       => $activity->getStats(30),
            'leaderboard' => $activity->getLeaderboard(30),
        ]);
    }
    
    // ==========================================
    // POST /mod/ban/{id} — Бан/разбан пользователя
    // ==========================================
    public function banUser(string $id): void
    {
        $targetUserId = (int)$id;
        $currentUserId = Auth::id();
        $action = $this->request->post('action') ?? '';
        $reason = trim($this->request->post('reason') ?? '');

        if ($targetUserId === $currentUserId) {
            Session::setFlash('error', 'Вы не можете применить это действие к себе.');
            $this->redirectBack();
            return;
        }

        if (!in_array($action, ['ban', 'unban'], true)) {
            Session::setFlash('error', 'Неизвестное действие.');
            $this->redirectBack();
            return;
        }

        $userModel = new \App\Modules\Users\Models\User();
        $targetUser = $userModel->find($targetUserId);

        if (!$targetUser) {
            Session::setFlash('error', 'Пользователь не найден.');
            $this->redirectBack();
            return;
        }

        $moderationModel = new Moderation();

        if ($action === 'ban') {
            $moderationModel->banUser($targetUserId, $currentUserId, $reason);

            // ✅ Отправляем событие вместо logAction()
            $this->dispatch(new UserBanned(
                $targetUserId,
                $currentUserId,
                $reason ?: 'Без указания причины'
            ));

            Session::setFlash('success', "Пользователь «{$targetUser['username']}» забанен.");
        } else {
            $moderationModel->unbanUser($targetUserId);

            // ✅ Отправляем событие вместо logAction()
            $this->dispatch(new UserUnbanned(
                $targetUserId,
                $currentUserId,
                'Разбан пользователя'
            ));

            Session::setFlash('success', "Пользователь «{$targetUser['username']}» разбанен.");
        }

        $this->redirect('/user/' . $targetUser['username']);
    }
}