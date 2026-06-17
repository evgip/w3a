<?php

namespace App\Modules\Flags\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Session;
use App\Core\Audit;
use App\Modules\Flags\Models\Flag;

class FlagsController extends Controller
{
    /**
     * GET /flags/report?type=story&id=123
     * Форма подачи жалобы (модальное окно / отдельная страница)
     */
    public function reportForm(): void
    {
        $this->requireAuth();

        $request = new Request();
        $type = $request->getQuery('type');
        $targetId = (int) $request->getQuery('id');

        if (!in_array($type, ['story', 'comment'], true) || $targetId <= 0) {
            http_response_code(400);
            die('Некорректные параметры жалобы');
        }

        $flagModel = new Flag();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        // Уже жаловался?
        if ($flagModel->hasUserFlagged($userId, $type, $targetId)) {
            Session::setFlash('error', 'Вы уже подавали жалобу на этот контент.');
            $back = $type === 'story' ? "/stories/{$targetId}" : "/stories#comment-{$targetId}";
            header("Location: {$back}");
            exit;
        }

        $this->render('report_form', [
            'title'    => 'Пожаловаться на контент',
            'type'     => $type,
            'targetId' => $targetId,
            'reasons'  => Flag::REASONS,
        ]);
    }

    /**
     * POST /flags/report
     * Обработка жалобы
     */
    public function submit(): void
    {
        $this->requireAuth();

        $request = new Request();
        $request->validateCsrf();

        $type     = $request->getParams('flaggable_type');
        $targetId = (int) $request->getParams('flaggable_id');
        $reason   = $request->getParams('reason');
        $comment  = $request->getParams('comment');
        $userId   = (int) ($_SESSION['user_id'] ?? 0);

        $flagModel = new Flag();
        $result = $flagModel->submit($userId, $type, $targetId, $reason, $comment);

        if (!$result['ok']) {
            Session::setFlash('error', $result['error']);
        } else {
            $reasonLabel = Flag::REASONS[$reason] ?? $reason;
            Session::setFlash(
                'success',
                'Спасибо! Ваша жалоба принята. Модераторы рассмотрят её в ближайшее время.'
            );

            Audit::log('flag.submitted', "Пользователь подал жалобу", [
                'type'   => $type,
                'id'     => $targetId,
                'reason' => $reason,
            ]);

            // Если контент был автоматически скрыт — логируем
            if (!empty($result['hidden'])) {
                Audit::log('flag.auto_hidden', "Контент автоматически скрыт по порогу флагов", [
                    'type'      => $type,
                    'id'        => $targetId,
                    'threshold' => $flagModel->getHideThreshold(),
                ]);
            }
        }

        $back = $type === 'story' ? "/stories/{$targetId}" : "/stories#comment-{$targetId}";
        header("Location: {$back}");
        exit;
    }

    /**
     * GET /admin/flags
     * Список жалоб для модераторов
     */
    public function adminIndex(): void
    {
        $this->requireModerator();

        $flagModel = new Flag();
        $pending = $flagModel->getPendingFlags();
        $recent  = $flagModel->getAllFlags(50);

        $this->render('admin_index', [
            'title'        => 'Жалобы пользователей',
            'pendingFlags' => $pending,
            'recentFlags'  => $recent,
            'reasons'      => Flag::REASONS,
            'pendingCount' => count($pending),
        ]);
    }

    /**
     * POST /admin/flags/{id}/resolve
     */
    public function resolve(string $id): void
    {
        $this->requireModerator();

        $request = new Request();
        $request->validateCsrf();

        $action = $request->getParams('action') ?: 'hide'; // hide | dismiss
        $modId  = (int) ($_SESSION['user_id'] ?? 0);

        $flagModel = new Flag();
        $flag = $flagModel->find((int) $id);

        if (!$flag) {
            Session::setFlash('error', 'Жалоба не найдена');
            header('Location: /admin/flags');
            exit;
        }

        if ($action === 'dismiss') {
            $flagModel->dismiss((int) $id, $modId);
            Audit::log('flag.dismissed', "Модератор отклонил жалобу", ['flag_id' => (int) $id]);
            Session::setFlash('success', 'Жалоба отклонена. Контент восстановлен.');
        } else {
            $flagModel->resolve((int) $id, $modId, 'hide');
            Audit::log('flag.resolved', "Модератор подтвердил жалобу", ['flag_id' => (int) $id]);
            Session::setFlash('success', 'Жалоба подтверждена. Контент скрыт.');
        }

        header('Location: /admin/flags');
        exit;
    }

    /**
     * GET /admin/flags/count (AJAX)
     * Возвращает JSON с количеством pending-жалоб — для бейджа в шапке
     */
    public function pendingCount(): void
    {
        $this->requireModerator();
        header('Content-Type: application/json');
        echo json_encode(['count' => (new Flag())->getPendingCount()]);
        exit;
    }

    private function requireModerator(): void
    {
        $this->requireAuth();
        if (!Auth::isAdmin() && !Auth::isModerator()) {
            http_response_code(403);
            die('<h1>403 Forbidden</h1><p>Требуются права модератора.</p>');
        }
    }
}