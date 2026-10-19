<?php

declare(strict_types=1);

// Valores lidos do .env (XAMPP: root sem palavra-passe por omissão).
return [
    'host'    => env('DB_HOST', '127.0.0.1'),
    'port'    => (int) env('DB_PORT', 3306),
    'name'    => env('DB_NAME', 'monana_eclesia'),
    'user'    => env('DB_USER', 'root'),
    'pass'    => (string) env('DB_PASS', ''),
    'charset' => 'utf8mb4',
];
