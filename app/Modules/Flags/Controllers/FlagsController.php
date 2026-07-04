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
     * ✅ Хелпер: получить Session из контейнера
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }

    /**
     * ✅ Хелпер: получить Audit из контейнера
     */
    private function audit(): Audit
    {
        return $this->container->get(Audit::class);
    }

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
        // ✅ Используем Auth::id() вместо $_SESSION
        $userId = (int) Auth::id();

        if ($flagModel->hasUserFlagged($userId, $type, $targetId)) {
            $this->session()->flash('error', 'Вы уже подавали жалобу на этот контент.');
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
        // ✅ Используем Auth::id() вместо $_SESSION
        $userId   = (int) Auth::id();

        $flagModel = $this->service(Flag::class);
        $result = $flagModel->submit($userId, $type, $targetId, $reason, $comment);

        if (!$result['ok']) {
            $this->session()->flash('error', $result['error']);
        } else {
            $this->session()->flash('success', 'Спасибо! Ваша жалоба принята. Модераторы рассмотрят её в ближайшее время.');

            // ✅ Используем хелпер audit()
            $this->audit()->log('flag.submitted', 'Пользователь подал жалобу', 'flags', [
                'type'   => $type,
                'id'     => $targetId,
                'reason' => $reason,
            ]);

            if (!empty($result['hidden'])) {
                $this->audit()->log('flag.auto_hidden', 'Контент автоматически скрыт по порогу флагов', 'flags', [
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
        // ✅ Используем Auth::id() вместо $_SESSION
        $modId  = (int) Auth::id();

        $flagModel = $this->service(Flag::class);
        $flag = $flagModel->find((int) $id);

        if (!$flag) {
            $this->session()->flash('error', 'Жалоба не найдена');
            $this->redirect('/admin/flags');
            return;
        }

        if ($action === 'dismiss') {
            $flagModel->dismiss((int) $id, $modId);
            $this->audit()->log('flag.dismissed', 'Модератор отклонил жалобу', 'flags', ['flag_id' => (int) $id]);
            $this->session()->flash('success', 'Жалоба отклонена. Контент восстановлен.');
        } else {
            $flagModel->resolve((int) $id, $modId);
            $this->audit()->log('flag.resolved', 'Модератор подтвердил жалобу', 'flags', ['flag_id' => (int) $id]);
            $this->session()->flash('success', 'Жалоба подтверждена. Контент скрыт.');
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
     */
    private function buildTargetUrl(string $type, int $targetId): string
    {
        if ($type === 'story') {
            return "/story/{$targetId}";
        }

        // $targetId — это ID комментария, нужно получить story_id
        $commentModel = $this->service(Comment::class);
        $comment = $commentModel->find($targetId);

        if ($comment && !empty($comment['story_id'])) {
            return "/story/{$comment['story_id']}#comment-block-{$targetId}";
        }

        return '/';
    }
}