<?php
namespace App\Services;

use App\Core\ErrorHandler;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Transporte de desenvolvimento: regista que a mensagem não foi enviada. */
final class LogMailer implements MailerInterface
{
    public function send(string $recipient, string $subject, string $html, string $text): void
    {
        // Não registar destinatário, corpo nem URL: podem conter dados pessoais
        // e tokens de uso único. A fila mantém apenas o payload cifrado.
        ErrorHandler::log('EMAIL', 'LogMailer não envia mensagens; configure SMTP ou um serviço transaccional.', [
            'assunto' => substr($subject, 0, 160),
        ]);
        throw new \RuntimeException('O envio está em modo de registo; configure SMTP para entregar este email.');
    }
}
