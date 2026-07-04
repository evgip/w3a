<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Users\Models\User;
use App\Modules\Auth\Models\PasswordResetToken;
use App\Modules\Mail\Core\Mailer;

class PasswordResetService
{
    private const TOKEN_LIFETIME = 3600;

    private User $userModel;
    private PasswordResetToken $tokenModel;
    private Mailer $mailer;

    public function __construct(
        User $userModel, 
        PasswordResetToken $tokenModel,
        Mailer $mailer
    ) {
        $this->userModel = $userModel;
        $this->tokenModel = $tokenModel;
        $this->mailer = $mailer;
    }

    public function sendResetLink(string $email): bool
    {
        $email = trim($email);
        $user = $this->userModel->findBy('email', $email);

        if (!$user) {
            return true;
        }

        $token = bin2hex(random_bytes(32));
        $this->tokenModel->createToken($email, $token);

        $resetUrl = $this->getResetUrl($token);
        $this->sendResetEmail($user['email'], $user['username'], $resetUrl);

        return true;
    }

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

    public function cleanupExpiredTokens(): int
    {
        return $this->tokenModel->cleanupExpired();
    }

    private function getResetUrl(string $token): string
    {
        $baseUrl = rtrim(config('config.app.url'), '/');
        return $baseUrl . '/password/reset/' . $token;
    }

    private function sendResetEmail(string $email, string $username, string $resetUrl): void
    {
        $siteName = config('app.name') ?? config('config.app.url');

        $subject = sprintf(__('email_recovery_subject'), htmlspecialchars($siteName));
        $body = sprintf(
            __('email_recovery_body'),
            htmlspecialchars($username),
            htmlspecialchars($resetUrl)
        );

        $this->mailer->send($email, $subject, $body);
    }
}