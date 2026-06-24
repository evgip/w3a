<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Users\Models\User;
use App\Modules\Auth\Models\PasswordResetToken;

/**
 * Сервис для управления восстановлением пароля через email.
 */
class PasswordResetService
{
    /** @var int Время жизни токена в секундах (1 час) */
    private const TOKEN_LIFETIME = 3600;

    private User $userModel;
    private PasswordResetToken $tokenModel;

    public function __construct(User $userModel)
    {
        $this->userModel = $userModel;
        $this->tokenModel = new PasswordResetToken();
    }

    /**
     * Создать токен и отправить ссылку восстановления на email.
     */
    public function sendResetLink(string $email): bool
    {
        $email = trim($email);
        $user = $this->userModel->findBy('email', $email);

        if (!$user) {
            return true;
        }

        $token = bin2hex(random_bytes(32));
        
        // ⚠️ Изменено: create() → createToken()
        $this->tokenModel->createToken($email, $token);

        $resetUrl = $this->getResetUrl($token);
        $this->sendResetEmail($user['email'], $user['username'], $resetUrl);

        return true;
    }

    /**
     * Проверить валидность токена.
     */
    public function validateToken(string $token): ?array
    {
        $tokenData = $this->tokenModel->findByToken($token);

        if (!$tokenData) {
            return null;
        }

        $createdAt = strtotime($tokenData['created_at']);
        if ((time() - $createdAt) > self::TOKEN_LIFETIME) {
            $this->tokenModel->deleteByToken($token);
            return null;
        }

        $user = $this->userModel->findBy('email', $tokenData['email']);

        if (!$user) {
            $this->tokenModel->deleteByToken($token);
            return null;
        }

        return $user;
    }

    /**
     * Сбросить пароль по валидному токену.
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $user = $this->validateToken($token);

        if (!$user) {
            return false;
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $success = $this->userModel->update((int)$user['id'], ['password' => $passwordHash]);

        if ($success) {
            $this->tokenModel->deleteByToken($token);
        }

        return $success;
    }

    /**
     * Удалить просроченные токены (для cron).
     */
    public function cleanupExpiredTokens(): int
    {
        return $this->tokenModel->cleanupExpired();
    }

    /**
     * Сформировать полный URL для сброса пароля.
     */
    private function getResetUrl(string $token): string
    {
        $baseUrl = rtrim(config('config.app.url'), '/');
        return $baseUrl . '/password/reset/' . $token;
    }

    /**
     * Отправить HTML-письмо со ссылкой восстановления.
     */
    private function sendResetEmail(string $email, string $username, string $resetUrl): void
    {
        $siteName = config('app.name') ?? config('config.app.url');

        $subject = sprintf(__('email_recovery_subject'), htmlspecialchars($siteName));
        $body = sprintf(
            __('email_recovery_body'),
            htmlspecialchars($username),
            htmlspecialchars($resetUrl)
        );

        \App\Modules\Mail\Core\Mailer::send($email, $subject, $body);
    }
}