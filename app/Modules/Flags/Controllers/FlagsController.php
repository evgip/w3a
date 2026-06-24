<?php

namespace App\Modules\Flags\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Audit;
use App\Modules\Flags\Models\Flag;
use App\Modules\Stories\Models\Comment;
use App\Modules\Auth\Services\Auth;

class FlagsController extends Controller
{
    /**
     * GET /flags/report?type=story&id=123
     * Форма подачи жалобы
     */
    public function reportForm(string $type, string $id): void
    {
        $targetId = (int) $id;

        if (!in_array($type, ['story', 'comment'], true) || $targetId <= 0) {
            http_response_code(400);
            die('Некорректные параметры жалобы');
        }

        $flagModel = $this->service(Flag::class);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($flagModel->hasUserFlagged($userId, $type, $targetId)) {
            Session::setFlash('error', 'Вы уже подавали жалобу на этот контент.');
            $this->redirect($this->buildTargetUrl($type, $targetId));
            return;
        }

        $this->render('report_form', [
            'title'    => 'Пожаловаться на контент',
            'type'     => $type,
            'targetId' => $targetId,
            'reasons'  => Flag::getReasons(),
        ]);
    }

    /**
     * POST /flags/report
     * Обработка жалобы
     */
    public function submit(): void
    {
        $this->request->validateCsrf();

        $type     = $this->request->getParams('flaggable_type');
        $targetId = (int) $this->request->getParams('flaggable_id');
        $reason   = $this->request->getParams('reason');
        $comment  = $this->request->getParams('comment');
        $userId   = (int) ($_SESSION['user_id'] ?? 0);

        $flagModel = $this->service(Flag::class);
        $result = $flagModel->submit($userId, $type, $targetId, $reason, $comment);

        if (!$result['ok']) {
            Session::setFlash('error', $result['error']);
        } else {
            Session::setFlash('success', 'Спасибо! Ваша жалоба принята. Модераторы рассмотрят её в ближайшее время.');

            Audit::log('flag.submitted', 'Пользователь подал жалобу', 'flags', [
                'type'   => $type,
                'id'     => $targetId,
                'reason' => $reason,
            ]);

            if (!empty($result['hidden'])) {
                Audit::log('flag.auto_hidden', 'Контент автоматически скрыт по порогу флагов', 'flags', [
                    'type'      => $type,
                    'id'        => $targetId,
                    'threshold' => Flag::getHideThreshold(),
                ]);
            }
        }

        $this->redirect($this->buildTargetUrl($type, $targetId));
    }

    /**
     * GET /admin/flags
     * Список жалоб для модераторов
     */
    public function adminIndex(): void
    {
        $flagModel = $this->service(Flag::class);
        $pending = $flagModel->getPendingFlags();
        $recent  = $flagModel->getAllFlags(50);

        $this->render('admin_index', [
            'title'        => 'Жалобы пользователей',
            'pendingFlags' => $pending,
            'recentFlags'  => $recent,
            'reasons'      => Flag::getReasons(),
            'pendingCount' => count($pending),
            'hideThreshold'=> Flag::getHideThreshold(),
        ]);
    }

    /**
     * POST /admin/flags/{id}/resolve
     */
    public function resolve(string $id): void
    {
        $this->request->validateCsrf();

        $action = $this->request->getParams('action') ?: 'hide';
        $modId  = (int) ($_SESSION['user_id'] ?? 0);

        $flagModel = $this->service(Flag::class);
        $flag = $flagModel->find((int) $id);

        if (!$flag) {
            Session::setFlash('error', 'Жалоба не найдена');
            $this->redirect('/admin/flags');
            return;
        }

        if ($action === 'dismiss') {
            $flagModel->dismiss((int) $id, $modId);
            Audit::log('flag.dismissed', 'Модератор отклонил жалобу', 'flags', ['flag_id' => (int) $id]);
            Session::setFlash('success', 'Жалоба отклонена. Контент восстановлен.');
        } else {
            $flagModel->resolve((int) $id, $modId);
            Audit::log('flag.resolved', 'Модератор подтвердил жалобу', 'flags', ['flag_id' => (int) $id]);
            Session::setFlash('success', 'Жалоба подтверждена. Контент скрыт.');
        }

        $this->redirect('/admin/flags');
    }

    /**
     * GET /admin/flags/count (AJAX)
     */
    public function pendingCount(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['count' => $this->service(Flag::class)->getPendingCount()]);
        exit;
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Строит URL для редиректа к целевому контенту (истории или комментарию).
     *
     * @param string $type     Тип контента: 'story' или 'comment'
     * @param int    $targetId ID истории или комментария
     * @return string URL для редиректа
     */
    private function buildTargetUrl(string $type, int $targetId): string
    {
        if ($type === 'story') {
            // $targetId — это и есть ID истории
            return "/story/{$targetId}";
        }

        // $targetId — это ID комментария, нужно получить story_id
        $commentModel = $this->service(Comment::class);
        $comment = $commentModel->find($targetId);

        if ($comment && !empty($comment['story_id'])) {
            return "/story/{$comment['story_id']}#comment-block-{$targetId}";
        }

        // Фолбэк, если комментарий не найден
        return '/';
    }
}