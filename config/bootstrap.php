<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// O Composer é opcional em tempo de execução (o PSR-4 local é sempre registado).
$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

require_once BASE_PATH . '/app/Core/Autoload.php';
\App\Core\Autoload::register(BASE_PATH . '/app');
\App\Core\Config::load(BASE_PATH . '/.env');
require_once BASE_PATH . '/app/helpers.php';

\App\Core\ErrorHandler::register(
    BASE_PATH . '/storage/logs',
    \App\Core\Config::bool('APP_DEBUG', false)
);

$appConfiguration = require BASE_PATH . '/config/app.php';
\App\Core\Config::set('app', $appConfiguration);

$databaseConfiguration = require BASE_PATH . '/config/database.php';
\App\Core\Database::configure($databaseConfiguration);

// MailService lê a configuração de email, mas o bootstrap não envia mensagens.
$mailConfiguration = require BASE_PATH . '/config/mail.php';
\App\Core\Config::set('mail', $mailConfiguration);
unset($mailConfiguration);

if (!empty($appConfiguration['timezone'])) {
    date_default_timezone_set((string) $appConfiguration['timezone']);
}

\App\Core\Session::configure($appConfiguration['session']);

// Helpers temporários para as páginas procedurais ainda ligadas à ponte legada.
require_once BASE_PATH . '/config/url.php';

if (!function_exists('db')) {
    function db(): PDO
    {
        return \App\Core\Database::connection();
    }
}
