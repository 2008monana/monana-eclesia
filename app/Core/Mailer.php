<?php

declare(strict_types=1);

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

final class Mailer
{
    public static function send(string $to, string $toName, string $subject, string $htmlBody): bool
    {
        $cfg = (array) config('mail', []);

        // SMTP não configurado: não tenta enviar. Em debug, regista o conteúdo para testes locais.
        if (empty($cfg['host'])) {
            logger('warning', 'SMTP não configurado (MAIL_HOST vazio): e-mail não enviado', ['to' => $to, 'subject' => $subject]);
            if (config('app.debug')) {
                logger('debug', 'Conteúdo do e-mail (apenas debug)', ['to' => $to, 'body' => $htmlBody]);
            }
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host     = (string) $cfg['host'];
            $mail->Port     = (int) ($cfg['port'] ?? 587);
            $mail->SMTPAuth = !empty($cfg['username']);
            $mail->Username = (string) ($cfg['username'] ?? '');
            $mail->Password = (string) ($cfg['password'] ?? '');
            $mail->Timeout  = 10;
            $mail->CharSet  = 'UTF-8';

            $enc = (string) ($cfg['encryption'] ?? 'tls');
            if ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure  = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom(
                (string) ($cfg['from']['address'] ?? 'no-reply@monana.app'),
                (string) ($cfg['from']['name'] ?? 'Monana Eclesia')
            );
            $mail->addAddress($to, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $htmlBody));

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            logger('error', 'Falha ao enviar email', [
                'to'      => $to,
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }
}
