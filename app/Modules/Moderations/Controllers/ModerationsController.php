<?php

declare(strict_types=1);

namespace App\Modules\Moderations\Controllers;

use App\Core\Controller;
use App\Modules\Moderations\Models\ModNote;
use App\Modules\Moderations\Models\ModActivity;
use App\Modules\Admin\Models\AuditLog;
use App\Modules\Moderations\Services\ModerationService;
use App\Modules\Auth\Services\Auth;

/**
 * Контроллер модерации.
 *
 * Отвечает только за:
 *  - Получение параметров из запроса
 *  - Вызов сервиса
 *  - Редирект или рендеринг
 *
 * Вся бизнес-логика — в ModerationService.
 */
class ModerationsController extends Controller
{
    // ==========================================
    // /mod/log — Публичный лог модерации
    // ==========================================

    /**
     * Отображение публичного лога модерации.
     * Берёт записи из audit_logs с категорией 'moderation'.
     */
    public function log(): void
    {
        $page = max(1, (int)$this->request->query('page', 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        $auditLog = $this->service(AuditLog::class);
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

    /**
     * Отображение списка заметок модераторов.
     */
    public function notes(): void
    {
        $model = $this->service(ModNote::class);
        $notes = $model->getRecentNotes(100);

        $targetUserId = $this->request->query('user_id') !== ''
            ? (int)$this->request->query('user_id')
            : null;

        $this->render('notes', [
            'title'          => 'Модераторские заметки',
            'notes'          => $notes,
            'target_user_id' => $targetUserId,
        ]);
    }

    // ==========================================
    // POST /mod/notes/store — Добавить заметку
    // ==========================================

    /**
     * Добавление модераторской заметки.
     *
     * Контроллер только получает параметры и вызывает сервис.
     * Валидация, сохранение, событие и flash — в ModerationService.
     */
    public function storeNote(): void
    {
        $result = $this->service(ModerationService::class)->addNote(
            (int)$this->request->post('user_id'),
            Auth::id(),
            (string)($this->request->post('note') ?? ''),
            (int)($this->request->post('is_private') ?? 1)
        );

        $this->redirect('/mod/notes');
    }

    // ==========================================
    // POST /mod/notes/{id}/delete — Удалить заметку
    // ==========================================

    /**
     * Удаление модераторской заметки.
     *
     * @param string $id ID заметки
     */
    public function deleteNote(string $id): void
    {
        $this->service(ModerationService::class)->deleteNote((int)$id);
        $this->redirect('/mod/notes');
    }

    // ==========================================
    // /mod/stats — Статистика активности
    // ==========================================

    /**
     * Статистика активности модераторов (только админ/мод).
     */
    public function stats(): void
    {
        $activity = $this->service(ModActivity::class);

        $this->render('stats', [
            'title'       => 'Активность модераторов',
            'stats'       => $activity->getStats(30),
            'leaderboard' => $activity->getLeaderboard(30),
        ]);
    }

    // ==========================================
    // POST /mod/ban/{id} — Бан/разбан пользователя
    // ==========================================

    /**
     * Бан или разбан пользователя.
     *
     * Контроллер определяет действие и вызывает нужный метод сервиса.
     * Вся бизнес-логика (проверки, модели, события, flash) — в ModerationService.
     *
     * @param string $id ID целевого пользователя
     */
    public function banUser(string $id): void
    {
        $targetUserId = (int)$id;
        $currentUserId = Auth::id();
        $action = $this->request->post('action') ?? '';
        $reason = trim($this->request->post('reason') ?? '');

        $service = $this->service(ModerationService::class);

        if ($action === 'ban') {
            $result = $service->banUser($targetUserId, $currentUserId, $reason);
        } elseif ($action === 'unban') {
            $result = $service->unbanUser($targetUserId, $currentUserId);
        } else {
            Session::setFlash('error', 'Неизвестное действие.');
            $this->redirectBack();
            return;
        }

        if ($result === null) {
            // Flash-сообщение уже установлено в сервисе
            $this->redirectBack();
            return;
        }

        // Редирект на профиль пользователя
        $this->redirect('/user/' . $result['username']);
    }
}