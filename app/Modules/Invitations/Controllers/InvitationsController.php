<?php

namespace App\Modules\Invitations\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Validator;
use App\Modules\Invitations\Models\Invitation;
use App\Modules\Invitations\Models\InvitationRequest;
use App\Modules\Users\Models\User;
use App\Modules\Auth\Services\Auth;

class InvitationsController extends Controller
{
	/**
	 * ✅ Хелпер: получить Mailer из контейнера
	 */
	private function mailer(): \App\Modules\Mail\Core\Mailer
	{
		return $this->container->get(\App\Modules\Mail\Core\Mailer::class);
	}
	
    /**
     * ✅ Хелпер: получить Session из контейнера
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }

    /**
     * ✅ Хелпер: получить Validator из контейнера
     */
    private function validator(): Validator
    {
        return $this->container->get(Validator::class);
    }

    /**
     * Проверка, включена ли система инвайтов
     */
    private function isInvitationsEnabled(): bool
    {
        return config('invitations.config.invitations_enabled');
    }

    /**
     * Проверка минимальной кармы для приглашений
     */
    private function hasEnoughKarma(int $userId): bool
    {
        $minKarma = config('invitations.config.min_karma_for_invitation');
        $userKarma = $this->service(User::class)->getUserKarma($userId);
        return $userKarma >= $minKarma;
    }

    /**
     * Главная страница управления приглашениями (GET /invitations)
     */
    public function index(): void
    {
        if (!$this->isInvitationsEnabled()) {
            $this->session()->flash('error', 'Система приглашений отключена.');
            $this->redirectBack('/');
        }

        $userId = Auth::id();
        $invitationModel = $this->service(Invitation::class);

        $invitations = $invitationModel->getUserInvitations($userId);
        $activeCount = $invitationModel->countActiveInvitations($userId);
        $maxInvitations = config('invitations.config.max_invitations_per_user');

        $this->render('index', [
            'title' => 'Управление приглашениями',
            'invitations' => $invitations,
            'activeCount' => $activeCount,
            'maxInvitations' => $maxInvitations,
            'hasEnoughKarma' => $this->hasEnoughKarma($userId),
            'minKarma' => config('invitations.config.min_karma_for_invitation'),
            'request' => $this->request
        ]);
    }

    /**
     * Создать новое приглашение (POST /invitations/create)
     */
    public function create(): void
    {
        $this->request->validateCsrf();

        if (!$this->isInvitationsEnabled()) {
            $this->session()->flash('error', 'Система приглашений отключена.');
            $this->redirectBack('/');
        }

        $userId = Auth::id();

        if (!$this->hasEnoughKarma($userId)) {
            $this->session()->flash('error', 'Недостаточно кармы для создания приглашений.');
            $this->redirectBack(route('invitations.index'));
        }

        $invitationModel = $this->service(Invitation::class);

        $activeCount = $invitationModel->countActiveInvitations($userId);
        $maxInvitations = config('invitations.config.max_invitations_per_user');

        if ($activeCount >= $maxInvitations) {
            $this->session()->flash('error', "Вы достигли лимита активных приглашений ({$maxInvitations}).");
            $this->redirectBack(route('invitations.index'));
        }

        // ✅ Используем хелпер validator()
        $email = trim($this->request->getParams('email'));
        if (!empty($email)) {
            $this->validator()->validate(['email' => $email], [
                'email' => 'required|email'
            ]);

            if (!$this->validator()->isValid()) {
                $this->session()->flash('error', 'Некорректный email адрес.');
                $this->redirectBack(route('invitations.index'));
            }
        }

        $expiresDays = config('invitations.config.invitation_expires_days', 7, 'int');
        $invitationId = $invitationModel->createInvitation($userId, $email ?: null, $expiresDays);

        if ($invitationId) {
            $invitation = $invitationModel->find($invitationId);

            if (!empty($email)) {
                $this->sendInvitationEmail($email, $invitation);
            }

            $this->session()->flash('success', 'Приглашение успешно создано!');
        } else {
            $this->session()->flash('error', 'Ошибка создания приглашения.');
        }

        $this->redirectBack(route('invitations.index'));
    }

    /**
     * Отозвать приглашение (POST /invitations/revoke/{id})
     */
    public function revoke(int $id): void
    {
        $this->request->validateCsrf();

        $userId = Auth::id();

        if ($this->service(Invitation::class)->revokeInvitation($id, $userId)) {
            $this->session()->flash('success', 'Приглашение отозвано.');
        } else {
            $this->session()->flash('error', 'Не удалось отозвать приглашение.');
        }

        $this->redirectBack(route('invitations.index'));
    }

    /**
     * Страница регистрации по приглашению (GET /register/invite/{code})
     */
    public function showInviteRegistration(string $code): void
    {
        if (!$this->isInvitationsEnabled()) {
            $this->session()->flash('error', 'Система приглашений отключена.');
            $this->redirectBack('/');
        }

        $invitationModel = $this->service(Invitation::class);
        $invitation = $invitationModel->findByCode($code);

        if (!$invitation || !$invitationModel->isValid($invitation)) {
            $this->session()->flash('error', 'Приглашение недействительно или истек срок действия.');
            $this->redirectBack('/');
        }

        $this->render('register_invite', [
            'title' => 'Регистрация по приглашению',
            'code' => $code,
            'invitation' => $invitation,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка регистрации по приглашению (POST /register/invite/{code})
     */
    public function registerWithInvite(string $code): void
    {
        if (!$this->isInvitationsEnabled()) {
            $this->session()->flash('error', 'Система приглашений отключена.');
            $this->redirectBack('/');
        }

        $this->request->validateCsrf();

        $invitationModel = $this->service(Invitation::class);
        $invitation = $invitationModel->findByCode($code);

        if (!$invitation || !$invitationModel->isValid($invitation)) {
            $this->session()->flash('error', 'Приглашение недействительно или истек срок действия.');
            $this->redirectBack('/');
        }

        // ✅ Используем хелпер validator()
        $this->validator()->validate([
            'username' => $this->request->getParams('username'),
            'email' => $this->request->getParams('email'),
            'password' => $this->request->getParams('password'),
            'password_confirm' => $this->request->getParams('password_confirm')
        ], [
            'username' => 'required|min:3|max:50',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'password_confirm' => 'required|match:password'
        ]);

        if (!$this->validator()->isValid()) {
            $errors = $this->validator()->getErrors();
            $errorMessages = [];
            foreach ($errors as $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $errorMessages[] = $error;
                }
            }
            $this->session()->flash('error', implode('<br>', $errorMessages));
            $this->redirectBack('/register/invite/' . $code);
        }

        $userModel = $this->service(User::class);
        if ($userModel->findBy('username', $this->request->getParams('username'))) {
            $this->session()->flash('error', 'Имя пользователя уже занято.');
            $this->redirectBack('/register/invite/' . $code);
        }

        if ($userModel->findBy('email', $this->request->getParams('email'))) {
            $this->session()->flash('error', 'Email уже зарегистрирован.');
            $this->redirectBack('/register/invite/' . $code);
        }

        $newUserId = $userModel->create([
            'username' => $this->request->getParams('username'),
            'email' => $this->request->getParams('email'),
            'password' => password_hash($this->request->getParams('password'), PASSWORD_BCRYPT),
            'role' => 'user',
            'is_active' => 1
        ]);

        if ($newUserId > 0) {
            $invitationModel->acceptInvitation($code, $newUserId);

            $this->session()->flash('success', 'Регистрация успешна! Добро пожаловать!');
            $this->redirectBack(route('auth.login'));
        }

        $this->session()->flash('error', 'Ошибка регистрации.');
        $this->redirectBack('/register/invite/' . $code);
    }

    /**
     * Запрос приглашения (GET /invite/request)
     */
    public function showRequestForm(): void
    {
        if (!$this->isInvitationsEnabled()) {
            $this->session()->flash('error', 'Система приглашений отключена.');
            $this->redirectBack('/');
        }

        $this->render('request', [
            'title' => 'Запрос приглашения',
            'request' => $this->request
        ]);
    }

    /**
     * Обработка запроса приглашения (POST /invite/request)
     */
    public function submitRequest(): void
    {
        if (!$this->isInvitationsEnabled()) {
            $this->session()->flash('error', 'Система приглашений отключена.');
            $this->redirectBack('/');
        }

        $this->request->validateCsrf();

        $email = trim($this->request->getParams('email'));
        $reason = trim($this->request->getParams('reason'));

        $this->validator()->validate([
            'email' => $email,
            'reason' => $reason
        ], [
            'email' => 'required|email',
            'reason' => 'required|min:10'
        ]);

        if (!$this->validator()->isValid()) {
            $errors = $this->validator()->getErrors();
            $errorMessages = [];
            foreach ($errors as $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $errorMessages[] = $error;
                }
            }
            $this->session()->flash('error', implode('<br>', $errorMessages));
            $this->redirectBack('/invite/request');
        }

        $requestModel = $this->service(InvitationRequest::class);

        if ($requestModel->hasPendingRequest($email)) {
            $this->session()->flash('error', 'Вы уже отправили запрос. Ожидайте рассмотрения.');
            $this->redirectBack('/invite/request');
        }

        $userModel = $this->service(User::class);
        if ($userModel->findBy('email', $email)) {
            $this->session()->flash('error', 'Этот email уже зарегистрирован.');
            $this->redirectBack('/invite/request');
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $requestModel->createRequest($email, $reason, $ip);

        $this->session()->flash('success', 'Ваш запрос отправлен! Мы рассмотрим его в ближайшее время.');
        $this->redirectBack('/');
    }

    /**
     * Отправка email с приглашением
     */
    private function sendInvitationEmail(string $email, array $invitation): void
    {
        $siteName = app_name();
        $inviteUrl = route('home') . 'register/invite/' . $invitation['code'];
        $expiresAt = dt($invitation['expires_at']);

        $subject = \App\Core\Lang::format('email_invitation_subject', [e($siteName)]);
        $htmlBody = \App\Core\Lang::format('email_invitation_body', [
            e($siteName),
            e($inviteUrl),
            e($expiresAt)
        ]);

        $this->mailer()->send($email, $subject, $htmlBody);
    }
    
    /**
     * Отправка email об одобрении запроса
     */
    private function sendApprovedEmail(string $email, string $inviteCode, string $expiresAt): void
    {
        $siteName = app_name();
        $inviteUrl = route('home') . 'register/invite/' . $inviteCode;

        $subject = \App\Core\Lang::format('email_invitation_request_approved_subject', [e($siteName)]);
        $htmlBody = \App\Core\Lang::format('email_invitation_request_approved_body', [
            e($siteName),
            e($inviteUrl),
            e($expiresAt)
        ]);

        $this->mailer()->send($email, $subject, $htmlBody);
    }

    /**
     * Отправка email об отклонении запроса
     */
    private function sendRejectedEmail(string $email): void
    {
        $siteName = app_name();

        $subject = \App\Core\Lang::format('email_invitation_request_rejected_subject', [e($siteName)]);
        $htmlBody = \App\Core\Lang::format('email_invitation_request_rejected_body', [
            e($siteName)
        ]);

        $this->mailer()->send($email, $subject, $htmlBody);
    }
}