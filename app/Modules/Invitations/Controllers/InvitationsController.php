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
     * Проверка, включена ли система инвайтов
     */
    private function isInvitationsEnabled(): bool
    {
        return config_bool('config.app.invitations_enabled', false);
    }

    /**
     * Проверка минимальной кармы для приглашений
     */
    private function hasEnoughKarma(int $userId): bool
    {
        $minKarma = config_int('config.app.min_karma_for_invitation', 10);
        $userKarma = $this->service(User::class)->getUserKarma($userId);
        return $userKarma >= $minKarma;
    }

    /**
     * Главная страница управления приглашениями (GET /invitations)
     */
    public function index(): void
    {
        if (!$this->isInvitationsEnabled()) {
            Session::setFlash('error', 'Система приглашений отключена.');
            header('Location: /');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $invitationModel = $this->service(Invitation::class);

        // Получаем все приглашения пользователя
        $invitations = $invitationModel->getUserInvitations($userId);

        // Подсчитываем активные
        $activeCount = $invitationModel->countActiveInvitations($userId);

        // Максимум приглашений
        $maxInvitations = config_int('config.app.max_invitations_per_user', 5);

        $this->render('index', [
            'title' => 'Управление приглашениями',
            'invitations' => $invitations,
            'activeCount' => $activeCount,
            'maxInvitations' => $maxInvitations,
            'hasEnoughKarma' => $this->hasEnoughKarma($userId),
            'minKarma' => config_int('config.app.min_karma_for_invitation', 10),
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
            Session::setFlash('error', 'Система приглашений отключена.');
            header('Location: /');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];

        // Проверка кармы
        if (!$this->hasEnoughKarma($userId)) {
            Session::setFlash('error', 'Недостаточно кармы для создания приглашений.');
            header('Location: ' . route('invitations.index'));
            exit;
        }

        $invitationModel = $this->service(Invitation::class);

        // Проверка лимита
        $activeCount = $invitationModel->countActiveInvitations($userId);
        $maxInvitations = config_int('config.app.max_invitations_per_user', 5);

        if ($activeCount >= $maxInvitations) {
            Session::setFlash('error', "Вы достигли лимита активных приглашений ({$maxInvitations}).");
            header('Location: ' . route('invitations.index'));
            exit;
        }

        // Валидация email (опционально)
        $email = trim($this->request->getParams('email'));
        if (!empty($email)) {
            $validator = new Validator();
            $validator->validate(['email' => $email], [
                'email' => 'required|email'
            ]);

            if (!$validator->isValid()) {
                Session::setFlash('error', 'Некорректный email адрес.');
                header('Location: ' . route('invitations.index'));
                exit;
            }
        }

        // Создание приглашения
        $expiresDays = config_int('config.app.invitation_expires_days', 7);
        $invitationId = $invitationModel->createInvitation($userId, $email ?: null, $expiresDays);

        if ($invitationId) {
            $invitation = $invitationModel->getById($invitationId);

            // Отправка email, если указан
            if (!empty($email)) {
                $this->sendInvitationEmail($email, $invitation);
            }

            Session::setFlash('success', 'Приглашение успешно создано!');
        } else {
            Session::setFlash('error', 'Ошибка создания приглашения.');
        }

        header('Location: ' . route('invitations.index'));
        exit;
    }

    /**
     * Отозвать приглашение (POST /invitations/revoke/{id})
     */
    public function revoke(int $id): void
    {
        $this->request->validateCsrf();

        $userId = (int)$_SESSION['user_id'];

        if ($this->service(Invitation::class)->revokeInvitation($id, $userId)) {
            Session::setFlash('success', 'Приглашение отозвано.');
        } else {
            Session::setFlash('error', 'Не удалось отозвать приглашение.');
        }

        header('Location: ' . route('invitations.index'));
        exit;
    }

    /**
     * Страница регистрации по приглашению (GET /register/invite/{code})
     */
    public function showInviteRegistration(string $code): void
    {
        if (!$this->isInvitationsEnabled()) {
            Session::setFlash('error', 'Система приглашений отключена.');
            header('Location: /');
            exit;
        }

        $invitationModel = $this->service(Invitation::class);
        $invitation = $invitationModel->findByCode($code);

        if (!$invitation || !$invitationModel->isValid($invitation)) {
            Session::setFlash('error', 'Приглашение недействительно или истек срок действия.');
            header('Location: /');
            exit;
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
            Session::setFlash('error', 'Система приглашений отключена.');
            header('Location: /');
            exit;
        }

        $this->request->validateCsrf();

        $invitationModel = $this->service(Invitation::class);
        $invitation = $invitationModel->findByCode($code);

        if (!$invitation || !$invitationModel->isValid($invitation)) {
            Session::setFlash('error', 'Приглашение недействительно или истек срок действия.');
            header('Location: /');
            exit;
        }

        // Валидация
        $validator = new Validator();
        $validator->validate([
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

        if (!$validator->isValid()) {
            Session::setFlash('error', implode('<br>', $validator->getErrors()));
            header('Location: /register/invite/' . $code);
            exit;
        }

        // Проверка уникальности
        $userModel = $this->service(User::class);
        if ($userModel->findBy('username', $this->request->getParams('username'))) {
            Session::setFlash('error', 'Имя пользователя уже занято.');
            header('Location: /register/invite/' . $code);
            exit;
        }

        if ($userModel->findBy('email', $this->request->getParams('email'))) {
            Session::setFlash('error', 'Email уже зарегистрирован.');
            header('Location: /register/invite/' . $code);
            exit;
        }

        // Создание пользователя
        $newUserId = $userModel->create([
            'username' => $this->request->getParams('username'),
            'email' => $this->request->getParams('email'),
            'password' => password_hash($this->request->getParams('password'), PASSWORD_BCRYPT),
            'role' => 'user',
            'is_active' => 1 // Сразу активен, т.к. есть приглашение
        ]);

        if ($newUserId > 0) {
            // Активируем приглашение
            $invitationModel->acceptInvitation($code, $newUserId);

            Session::setFlash('success', 'Регистрация успешна! Добро пожаловать!');
            header('Location: ' . route('auth.login'));
            exit;
        }

        Session::setFlash('error', 'Ошибка регистрации.');
        header('Location: /register/invite/' . $code);
        exit;
    }

    /**
     * Запрос приглашения (GET /invite/request)
     */
    public function showRequestForm(): void
    {
        if (!$this->isInvitationsEnabled()) {
            Session::setFlash('error', 'Система приглашений отключена.');
            header('Location: /');
            exit;
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
            Session::setFlash('error', 'Система приглашений отключена.');
            header('Location: /');
            exit;
        }

        $this->request->validateCsrf();

        $email = trim($this->request->getParams('email'));
        $reason = trim($this->request->getParams('reason'));

        $validator = new Validator();
        $validator->validate([
            'email' => $email,
            'reason' => $reason
        ], [
            'email' => 'required|email',
            'reason' => 'required|min:10'
        ]);

        if (!$validator->isValid()) {
            Session::setFlash('error', implode('<br>', $validator->getErrors()));
            header('Location: /invite/request');
            exit;
        }

        $requestModel =  $this->service(InvitationRequest::class);

        // Проверка на повторный запрос
        if ($requestModel->hasPendingRequest($email)) {
            Session::setFlash('error', 'Вы уже отправили запрос. Ожидайте рассмотрения.');
            header('Location: /invite/request');
            exit;
        }

        // Проверка, не зарегистрирован ли уже
        $userModel = $this->service(User::class);
        if ($userModel->findBy('email', $email)) {
            Session::setFlash('error', 'Этот email уже зарегистрирован.');
            header('Location: /invite/request');
            exit;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $requestModel->createRequest($email, $reason, $ip);

        Session::setFlash('success', 'Ваш запрос отправлен! Мы рассмотрим его в ближайшее время.');
        header('Location: /');
        exit;
    }

	/**
	 * Отправка email с приглашением
	 */
	private function sendInvitationEmail(string $email, array $invitation): void
	{
		$siteName = app_name();
		$inviteUrl = route('home') . 'register/invite/' . $invitation['code'];
		$expiresAt = dt($invitation['expires_at']);

		// Получаем локализованные шаблоны через Lang::format()
		$subject = \App\Core\Lang::format('email_invitation_subject', [e($siteName)]);
		$htmlBody = \App\Core\Lang::format('email_invitation_body', [
			e($siteName),
			e($inviteUrl),
			e($expiresAt)
		]);

		\App\Modules\Mail\Core\Mailer::send($email, $subject, $htmlBody);
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

		\App\Modules\Mail\Core\Mailer::send($email, $subject, $htmlBody);
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

		\App\Modules\Mail\Core\Mailer::send($email, $subject, $htmlBody);
	}
}