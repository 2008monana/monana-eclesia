<?php
namespace App\Services;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Transporte de email isolado do serviço que gere fila e templates. */
interface MailerInterface
{
    public function send(string $recipient, string $subject, string $html, string $text): void;
}
