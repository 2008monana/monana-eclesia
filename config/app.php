<?php

declare(strict_types=1);

return [
    'name'     => env('APP_NAME', 'Monana Eclesia'),
    'env'      => env('APP_ENV', 'local'),
    'debug'    => (bool) env('APP_DEBUG', false),
    // Vazio = deduzido automaticamente a partir do pedido (ver base_url()).
    'url'      => (string) env('APP_URL', ''),
    'timezone' => env('APP_TIMEZONE', 'Africa/Luanda'),
    'locale'   => 'pt-AO',

    'session' => [
        'name'     => 'monana_session',
        'lifetime' => 7200,
    ],

    'hash' => [
        'algo' => 'argon2id',
    ],
];
