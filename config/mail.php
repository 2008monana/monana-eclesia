<?php

declare(strict_types=1);

// Preencha MAIL_* no .env. Com MAIL_HOST vazio, os e-mails não são enviados
// (em modo debug, o conteúdo fica registado em storage/logs).
return [
    'host'       => (string) env('MAIL_HOST', ''),
    'port'       => (int) env('MAIL_PORT', 587),
    'username'   => (string) env('MAIL_USERNAME', ''),
    'password'   => (string) env('MAIL_PASSWORD', ''),
    'encryption' => strtolower((string) env('MAIL_ENCRYPTION', 'tls')), // tls | ssl | (vazio)
    'from'       => [
        'address' => (string) env('MAIL_FROM_ADDRESS', 'no-reply@monana.app'),
        'name'    => (string) env('MAIL_FROM_NAME', 'Monana Eclesia'),
    ],
];
