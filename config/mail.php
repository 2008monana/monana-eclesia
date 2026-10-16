<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

return [
    // O envio efectivo e a fila serão ligados na Fase 3.
    'mailer' => (string) \App\Core\Config::env('MAIL_MAILER', 'log'),
    'from_address' => (string) \App\Core\Config::env('MAIL_FROM_ADDRESS', 'nao-responder@example.invalid'),
    'from_name' => (string) \App\Core\Config::env('MAIL_FROM_NAME', 'MonanaEclésia'),
    'host' => (string) \App\Core\Config::env('MAIL_HOST', ''),
    'port' => \App\Core\Config::int('MAIL_PORT', 587),
    'username' => (string) \App\Core\Config::env('MAIL_USERNAME', ''),
    'password' => (string) \App\Core\Config::env('MAIL_PASSWORD', ''),
    'encryption' => (string) \App\Core\Config::env('MAIL_ENCRYPTION', 'tls'),
];
