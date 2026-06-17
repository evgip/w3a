<?php

namespace App\Modules\Origins\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Session;
use App\Core\Audit;
use App\Modules\Origins\Models\Domain;

class OriginsController extends Controller
{
    /**
     * Публичная страница списка забаненных доменов (GET /domains)
     */
    public function index(): void
    {
        $domainModel = new Domain();
        $bannedDomains = $domainModel->getBannedDomains();

        $this->render('index', [
            'title'         => 'Заблокированные домены',
            'bannedDomains' => $bannedDomains,
            'totalBanned'   => count($bannedDomains),
        ]);
    }

    /**
     * Форма добавления домена в бан-лист (GET /admin/domains/create)
     * Только для админов/модераторов
     */
    public function showBanForm(): void
    {
        $this->requireModerator();

        $this->render('ban_form', [
            'title'   => 'Заблокировать домен',
            'request' => new Request(),
        ]);
    }

    /**
     * Обработка бана домена (POST /admin/domains/ban)
     */
    public function ban(): void
    {
        $this->requireModerator();

        $request = new Request();
        $request->validateCsrf();

        $domain = strtolower(trim($request->getParams('domain')));
        $reason = trim($request->getParams('ban_reason')) ?: 'Нарушение правил сообщества';

        // Валидация домена
        if (empty($domain) || !preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*\.[a-z]{2,}$/i', $domain)) {
            Session::setFlash('error', 'Указан некорректный домен. Пример: example.com');
            header('Location: /admin/domains/create');
            exit;
        }

        $domainModel = new Domain();
        $moderatorId = (int) ($_SESSION['user_id'] ?? 0);

        if ($domainModel->ban($domain, $reason, $moderatorId)) {
            Audit::log('admin.domain_banned', "Модератор заблокировал домен: {$domain}", [
                'domain' => $domain,
                'reason' => $reason,
            ]);
            Session::setFlash('success', "Домен «{$domain}» успешно заблокирован.");
        } else {
            Session::setFlash('error', "Домен «{$domain}» уже заблокирован.");
        }

        header('Location: /admin/domains');
        exit;
    }

    /**
     * Разбан домена (POST /admin/domains/{id}/unban)
     */
    public function unban(string $id): void
    {
        $this->requireModerator();

        $request = new Request();
        $request->validateCsrf();

        $domainModel = new Domain();
        $domain = $domainModel->find((int) $id);

        if (!$domain) {
            Session::setFlash('error', 'Домен не найден.');
            header('Location: /admin/domains');
            exit;
        }

        $domainModel->unban($domain['domain']);

        Audit::log('admin.domain_unbanned', "Модератор разблокировал домен: {$domain['domain']}", [
            'domain_id' => (int) $id,
        ]);

        Session::setFlash('success', "Домен «{$domain['domain']}» успешно разблокирован.");
        header('Location: /admin/domains');
        exit;
    }

    /**
     * Админ-панель управления доменами (GET /admin/domains)
     */
    public function adminIndex(): void
    {
        $this->requireModerator();

        $domainModel = new Domain();
        $allDomains = $domainModel->getAllDomains();

        $this->render('admin_index', [
            'title'       => 'Управление доменами',
            'allDomains'  => $allDomains,
            'totalBanned' => $domainModel->getBannedCount(),
        ]);
    }

    /**
     * Проверка прав модератора или админа
     */
    private function requireModerator(): void
    {
        $this->requireAuth();
        if (!Auth::isAdmin() && !Auth::isModerator()) {
            http_response_code(403);
            die('<h1>403 Forbidden</h1><p>Доступ запрещён. Требуются права модератора.</p>');
        }
    }
}