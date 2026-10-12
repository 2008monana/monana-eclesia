<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

$databaseConfiguration = [
    'driver' => (string) \App\Core\Config::env('DB_CONNECTION', 'mysql'),
    'host' => trim((string) \App\Core\Config::env('DB_HOST', 'localhost')),
    'port' => \App\Core\Config::int('DB_PORT', 3306),
    'database' => trim((string) \App\Core\Config::env('DB_DATABASE', '')),
    'username' => (string) \App\Core\Config::env('DB_USERNAME', ''),
    'password' => (string) \App\Core\Config::env('DB_PASSWORD', ''),
    'charset' => (string) \App\Core\Config::env('DB_CHARSET', 'utf8mb4'),
];

// Compatibilidade temporária com módulos antigos; novos controladores usam
// directamente Database::connection() através dos seus repositórios.
if (!function_exists('getConnection')) {
    function getConnection(): PDO
    {
        return \App\Core\Database::connection();
    }
}

return $databaseConfiguration;
