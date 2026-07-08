<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\PasswordResetService;

class AuthController extends Controller
{
    public function showRequestResetForm(): void
    {
        $this->render('password/reset_request', [
            'title' => 'Восстановление пароля'
        ]);
    }

    public function sendResetLink(): void
    {
        $session = $this->container->get(Session::class);

        if (captcha_is_required() && !captcha_validate($this->request->post('smart-token'))) {
            $session->flash('error', 'Пожалуйста, подтвердите, что вы не робот.');
            $this->redirect(route('password.request'));
            return;
        }

        $email = filter_var($this->request->post('email', ''), FILTER_VALIDATE_EMAIL);

        if (!$email) {
            $session->flash('error', 'Неверный email адрес.');
            $this->redirect(route('password.request'));
            return;
        }

        $this->getPasswordResetService()->sendResetLink($email);

        $session->flash('success', 'Если email найден в системе, инструкция по восстановлению отправлена на почту.');
        $this->redirect(route('password.request'));
    }

    public function showResetPasswordForm(string $token): void
    {
        $session = $this->container->get(Session::class);
        $user = $this->getPasswordResetService()->validateToken($token);

        if (!$user) {
            $session->flash('error', 'Ссылка недействительна или истекла.');
            $this->redirect(route('password.request'));
            return;
        }

        $this->render('password/reset_form', [
            'title' => 'Установить новый пароль',
            'token' => $token
        ]);
    }

    public function executePasswordReset(): void
    {
        $session = $this->container->get(Session::class);
        $token = $this->request->getParams('token');
        $password = $this->request->getParams('password');
        $passwordConfirm = $this->request->getParams('password_confirm');

        if (empty($token) || empty($password) || empty($passwordConfirm)) {
            $session->flash('error', 'Заполните все поля.');
            $this->redirect(route('password.reset', ['token' => $token]));
            return;
        }

        if (strlen($password) < 6) {
            $session->flash('error', 'Пароль должен быть не менее 6 символов.');
            $this->redirect(route('password.reset', ['token' => $token]));
            return;
        }

        if ($password !== $passwordConfirm) {
            $session->flash('error', 'Пароли не совпадают.');
            $this->redirect(route('password.reset', ['token' => $token]));
            return;
        }

        $success = $this->getPasswordResetService()->resetPassword($token, $password);

        if ($success) {
            $session->flash('success', 'Пароль успешно изменён. Теперь вы можете войти.');
            $this->redirect(route('auth.login'));
        } else {
            $session->flash('error', 'Ошибка при смене пароля. Попробуйте запросить новую ссылку.');
            $this->redirect(route('password.request'));
        }
    }

    private function getPasswordResetService(): PasswordResetService
    {
        return $this->service(PasswordResetService::class);
    }

    public function login(): void
    {
        $session = $this->container->get(Session::class);
        $email = trim($this->request->getParams('email'));
        $password = $this->request->getParams('password');
        $remember = (bool)$this->request->getParams('remember');

        $user = $this->service(AuthService::class)->authenticate($email, $password);

        if (!$user) {
            $this->redirectWithMessage('/login', 'Неверный email или пароль', 'error');
            return;
        }

        $this->service(AuthService::class)->createSession($user, $remember);

        $session->flash('success', 'Добро пожаловать!');
        $this->redirect('/');
    }

    public function showLoginForm(): void
    {
        $this->render('login', [
            'title' => 'Авторизация',
            'request' => $this->request
        ]);
    }

    public function showRegisterForm(): void
    {
        if (config('invitations.config.invitations_enabled')) {
            $this->redirect(route('home'));
            return;
        }

        $session = $this->container->get(Session::class);
        $old = $session->get('old_input', []);
        $session->delete('old_input');

        $this->render('register', [
            'title' => 'Регистрация нового пользователя',
            'request' => $this->request,
            'old' => $old
        ]);
    }

    public function register(): void
    {
        $session = $this->container->get(Session::class);

        if (!captcha_validate($this->request->post('smart-token'))) {
            $session->flash('error', 'Пожалуйста, подтвердите, что вы не робот.');
            $this->redirect(route('auth.register'));
            return;
        }

        $username = trim($this->request->getParams('username'));
        $email = trim($this->request->getParams('email'));
        $password = $this->request->getParams('password');

        $validator = $this->container->get(\App\Core\Validator::class);
        $validator->validate([
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ], [
            'username' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (!$validator->isValid()) {

            $errors = $validator->getErrors();
            $errorMessages = array_merge(...array_values($errors));
            
            $session->flash('error', implode('<br>', $errorMessages));
            $session->set('old_input', [
                'username' => $username,
                'email' => $email,
            ]);
            $this->redirectBack('/register');
            return;
        }

        $userId = $this->service(AuthService::class)->register($username, $email, $password);
        
        if (!$userId) {
            $session->set('old_input', [
                'username' => $username,
                'email' => $email,
            ]);
            $this->redirectBack('/register');
            return;
        }

        $session->flash('success', 'Регистрация успешна! Проверьте почту.');
        $this->redirectBack('/login');
    }

    public function logout(): void
    {
        $this->service(AuthService::class)->logout();
        $this->redirectBack('/');
    }

    public function activateAccount(string $token): void
    {
        $session = $this->container->get(Session::class);
        $success = $this->getAuthService()->activateAccount($token);

        if ($success) {
            $session->flash('success', 'Аккаунт успешно активирован! Теперь вы можете войти.');
            $this->redirect(route('auth.login'));
        } else {
            $session->flash('error', 'Недействительная или устаревшая ссылка активации.');
            $this->redirect(route('auth.register'));
        }
    }

    private function getAuthService(): AuthService
    {
        return $this->service(AuthService::class);
    }
}