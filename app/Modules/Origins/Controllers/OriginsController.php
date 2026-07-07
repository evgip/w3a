<?php

namespace App\Modules\Origins\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Audit;
use App\Modules\Origins\Models\Domain;
use App\Modules\Auth\Services\Auth;

class OriginsController extends Controller
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

    public function index(): void
    {
        $domainModel = $this->service(Domain::class);
        $bannedDomains = $domainModel->getBannedDomains();

        $this->render('index', [
            'title'         => 'Заблокированные домены',
            'bannedDomains' => $bannedDomains,
            'totalBanned'   => count($bannedDomains),
        ]);
    }

    public function showBanForm(): void
    {
        $this->render('ban_form', [
            'title'   => 'Заблокировать домен',
            'request' => $this->request,
        ]);
    }

    public function ban(): void
    {
        $this->request->validateCsrf();

        $domain = strtolower(trim($this->request->getParams('domain')));
        $reason = trim($this->request->getParams('ban_reason')) ?: 'Нарушение правил сообщества';

        if (empty($domain) || !preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*\.[a-z]{2,}$/i', $domain)) {
            $this->session()->flash('error', 'Указан некорректный домен. Пример: example.com');
            $this->redirectBack('/admin/domains/create');
        }

        $domainModel = $this->service(Domain::class);
        // ✅ Используем Auth::id() вместо $_SESSION
        $moderatorId = (int) Auth::id();

        if ($domainModel->ban($domain, $reason, $moderatorId)) {
            $this->audit()->log('admin.domain_banned', "Модератор заблокировал домен: {$domain}", 'admin', [
                'domain' => $domain,
                'reason' => $reason,
            ]);
            $this->session()->flash('success', "Домен «{$domain}» успешно заблокирован.");
        } else {
            $this->session()->flash('error', "Домен «{$domain}» уже заблокирован.");
        }

        $this->redirectBack('/admin/domains');
    }

    public function unban(string $id): void
    {
        $this->request->validateCsrf();

        $domainModel = $this->service(Domain::class);
        $domain = $domainModel->find((int) $id);

        if (!$domain) {
            $this->session()->flash('error', 'Домен не найден.');
            $this->redirectBack('/admin/domains');
        }

        $domainModel->unban($domain['domain']);

        $this->audit()->log('admin.domain_unbanned', "Модератор разблокировал домен: {$domain['domain']}", 'admin', [
            'domain_id' => (int) $id,
        ]);

        $this->session()->flash('success', "Домен «{$domain['domain']}» успешно разблокирован.");
        $this->redirectBack('/admin/domains');
    }

    public function adminIndex(): void
    {
        $domainModel = $this->service(Domain::class);
        $allDomains = $domainModel->getAllDomains();

        $this->render('admin_index', [
            'title'       => 'Управление доменами',
            'allDomains'  => $allDomains,
            'totalBanned' => $domainModel->getBannedCount(),
        ]);
    }
}
