<?php

namespace App\Modules\Mail\Core;

use App\Core\Logger;
use App\Core\Config;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Mailer - отправка email через PHPMailer (SMTP)
 * 
 * Использует библиотеку PHPMailer для надёжной отправки писем.
 * Настройки в app/Modules/Mail/Config/config.php
 */
class Mailer
{
    /**
     * Dispatch an HTML format email using your central SMTP mail configuration parameters
     */
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        // 🔑 Читаем конфиг НАПРЯМУЮ из файла модуля (надёжнее, чем через Config::getFile)
        $configFile = dirname(__DIR__) . '/Config/config.php';
        $config = file_exists($configFile) ? require $configFile : [];

        // 🔍 ДИАГНОСТИКА (временно, для отладки)
        if (empty($config)) {
            Logger::error("Mailer: Config file not found or empty: {$configFile}");
            return false;
        }

        // 🔑 Режим тестирования — пишем в лог
        if (!empty($config['pretend']) && $config['pretend'] === true) {
            Logger::info("PRETEND MAIL TO [{$to}] | Subject: {$subject} | Body: " . strip_tags($htmlBody));
            return true;
        }

        // Fall back gracefully if SMTP configuration array blocks are empty
        if (empty($config['username']) || $config['username'] === 'your-login@yandex.ru') {
            Logger::warning("Mailer Warning: Real SMTP not configured. Falling back to local logging simulation loop.");
            Logger::info("SIMULATED MAIL TO [{$to}] | Subject: {$subject} | Body: " . strip_tags($htmlBody));
            return true;
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $config['host'];
            $mail->SMTPAuth   = $config['auth'] ?? true;
            $mail->Username   = $config['username'];
            $mail->Password   = $config['password'];
            $mail->SMTPSecure = $config['encryption'];
            $mail->Port       = (int)$config['port'];
            $mail->CharSet    = 'utf-8';

            // 🔍 ДИАГНОСТИКА (временно)
            Logger::info("Mailer: Attempting to send via {$config['host']}:{$config['port']} as {$config['username']}");

            //Recipients
            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($to);

            //Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;

            $mail->send();
            return true;
        } catch (Exception $e) {
            Logger::error("PHPMailer Critical Delivery Exception: Mail to [{$to}] failed. Error info: " . $mail->ErrorInfo);
            return false;
        }
    }
}