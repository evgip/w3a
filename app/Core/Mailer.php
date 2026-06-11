<?php

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

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
			
			
                // Server settings
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;                   //Enable verbose debug output
                $mail->isSMTP();                                            //Send using SMTP
                $mail->Host       = $config['host'];        //Set the SMTP server to send through
                $mail->SMTPAuth   = $config['auth'];                                   //Enable SMTP authentication
                $mail->Username   = $config['username'];        //SMTP username
                $mail->Password   = $config['password'];        //SMTP password
                $mail->SMTPSecure = $config['encryption']; // PHPMailer::ENCRYPTION_SMTPS;  //Enable implicit TLS encryption
                $mail->Port       = (int)$config['port'];        //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

                /* $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ]; */

                $mail->CharSet = 'utf-8';

                //Recipients
                $mail->setFrom($config['from_email'], $config['from_name']);
                $mail->addAddress($to);                                  //Name is optional

                //Content
                $mail->isHTML(true);                                        //Set email format to HTML
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
