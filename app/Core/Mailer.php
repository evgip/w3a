<?php

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /**
     * Dispatch an HTML format email using your central SMTP mail configuration parameters
     * 
     * @param string $to Recipient email address destination
     * @param string $subject Email title text line
     * @param string $htmlBody Structured HTML body context envelope payload
     * @return bool Returns true on delivery success, false on failure anomalies
     */
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $configFile = dirname(__DIR__) . '/Config/mail.php';
        $config = file_exists($configFile) ? require $configFile : [];

        // Fall back gracefully if SMTP configuration array blocks are empty
        if (empty($config['username']) || $config['username'] === 'your-login@yandex.ru') {
            Logger::warning("Mailer Warning: Real SMTP not configured. Falling back to local logging simulation loop.");
            Logger::info("SIMULATED MAIL TO [{$to}] | Subject: {$subject} | Body: " . strip_tags($htmlBody));
            return true;
        }

        $mail = new PHPMailer(true);

        try {
            // 1. Core Server Settings Configuration Initialization
            $mail->isSMTP();
            $mail->CharSet    = 'UTF-8';
            $mail->Host       = $config['host'];
            $mail->SMTPAuth   = $config['auth'];
            $mail->Username   = $config['username'];
            $mail->Password   = $config['password'];
            $mail->SMTPSecure = $config['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$config['port'];

            // 2. Setup Recipients and Headers metadata envelopes
            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($to);

            // 3. Bind Context Content Text Parameters
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            
            // Plain-text alternative fallback layout generation for old mail clients
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $htmlBody));

            $mail->send();
            return true;
        } catch (Exception $e) {
            Logger::error("PHPMailer Critical Delivery Exception: Mail to [{$to}] failed. Error info: " . $mail->ErrorInfo);
            return false;
        }
    }
}
