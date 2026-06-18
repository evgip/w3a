<?php

namespace App\Modules\Moderations\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Session;
use App\Core\Request;
use App\Modules\Moderations\Models\ModNote;
use App\Modules\Moderations\Models\Moderation;
use App\Modules\Moderations\Models\ModActivity;

class ModerationsController extends Controller
{
    public function __construct()
    {
        // Все методы контроллера требуют прав модератора (или админа)
        Auth::middlewareModerator();
    }

    // ==========================================
    // /mod/log — Публичный лог модерации
    // ==========================================
    public function log(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $model = new Moderation();
        $data = $model->getPublicLog($page, 30);

        $this->render('log', [
            'title'        => 'Лог модерации',
            'items'        => $data['items'],
            'total'        => $data['total'],
            'pages'        => $data['pages'],
            'current_page' => $data['current_page'],
        ]);
    }

    // ==========================================
    // /mod/notes — Приватные заметки модераторов
    // ==========================================
    public function notes(): void
    {
        $model = new ModNote();
        $notes = $model->getRecentNotes(100);

		// Считываем user_id из URL, если он есть, и приводим к целому числу
		$targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

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
        $request = new Request();
        $request->validateCsrf();

        $userId      = (int) ($request->getParams('user_id') ?? 0);
        $noteText    = trim($request->getParams('note') ?? '');
        $isPrivate   = (int) ($request->getParams('is_private') ?? 1);

        if ($userId <= 0 || $noteText === '') {
            Session::setFlash('error', 'Укажите пользователя и текст заметки.');
            header('Location: /mod/notes');
            exit;
        }

        $model = new ModNote();
        $model->create([
            'user_id'      => $userId,
            'moderator_id' => (int) $_SESSION['user_id'],
            'note'         => $noteText,
            'is_private'   => $isPrivate,
        ]);

        // Логируем действие
        $modLog = new Moderation();
        $modLog->logAction(
            (int) $_SESSION['user_id'],
            'add_note',
            'user',
            $userId,
            mb_substr($noteText, 0, 200)
        );

        Session::setFlash('success', 'Заметка добавлена.');
        header('Location: /mod/notes');
        exit;
    }

    // ==========================================
    // POST /mod/notes/{id}/delete — Удалить заметку
    // ==========================================
    public function deleteNote(string $id): void
    {
        $request = new Request();
        $request->validateCsrf();

        $model = new ModNote();
        $model->deleteNote((int) $id);

        Session::setFlash('success', 'Заметка удалена.');
        header('Location: /mod/notes');
        exit;
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
		$request = new Request();
		$request->validateCsrf();

		$targetUserId = (int) $id;
		$currentUserId = (int) $_SESSION['user_id'];
		$action = $request->getParams('action') ?? '';
		$reason = trim($request->getParams('reason') ?? '');

		if ($targetUserId === $currentUserId) {
			Session::setFlash('error', 'Вы не можете применить это действие к себе.');
			header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
			exit;
		}

		if (!in_array($action, ['ban', 'unban'], true)) {
			Session::setFlash('error', 'Неизвестное действие.');
			header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
			exit;
		}

		$userModel = new \App\Modules\Users\Models\User();
		$targetUser = $userModel->find($targetUserId);

		if (!$targetUser) {
			Session::setFlash('error', 'Пользователь не найден.');
			header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
			exit;
		}

		$moderationModel = new Moderation();

		if ($action === 'ban') {
			$moderationModel->banUser($targetUserId, $currentUserId, $reason);

			$moderationModel->logAction(
				$currentUserId,
				'ban_user',
				'user',
				$targetUserId,
				$reason ?: 'Без указания причины'
			);

			Session::setFlash('success', "Пользователь «{$targetUser['username']}» забанен.");
		} else {
			$moderationModel->unbanUser($targetUserId);

			$moderationModel->logAction(
				$currentUserId,
				'unban_user',
				'user',
				$targetUserId,
				'Разбан пользователя'
			);

			Session::setFlash('success', "Пользователь «{$targetUser['username']}» разбанен.");
		}

		header('Location: /user/' . $targetUser['username']);
		exit;
	}
}