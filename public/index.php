<?php

declare(strict_types=1);

// ------------------------------------------------------------
// 1. Constantes (BASE_PATH, CONFIG_PATH, ...) — antes do autoload
// ------------------------------------------------------------
require dirname(__DIR__) . '/config/constants.php';

// ------------------------------------------------------------
// 2. Autoload (Composer) — carrega App\* e os helpers
// ------------------------------------------------------------
$vendor = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($vendor)) {
    http_response_code(500);
    exit('Dependências não instaladas. Execute: composer install');
}
require $vendor;

// ------------------------------------------------------------
// 3. Variáveis de ambiente (.env) — tem de vir ANTES de config()/env()
// ------------------------------------------------------------
if (class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();
}

// ------------------------------------------------------------
// 4. Erros: detalhe só em modo debug (APP_DEBUG=true no .env)
// ------------------------------------------------------------
$debug = (bool) config('app.debug');
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(static function (\Throwable $e) use ($debug): void {
    logger('error', get_class($e) . ': ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($debug) {
        echo '<pre style="white-space:pre-wrap">' . esc((string) $e) . '</pre>';
    } else {
        echo '<h1>500</h1><p>Ocorreu um erro. Tente novamente mais tarde.</p>';
    }
});

// ------------------------------------------------------------
// 5. Timezone + locale
// ------------------------------------------------------------
date_default_timezone_set((string) config('app.timezone', 'Africa/Luanda'));
setlocale(LC_ALL, 'pt_AO.UTF-8', 'pt_AO', 'pt_PT.UTF-8', 'pt_PT', 'pt');

// ------------------------------------------------------------
// 6. Sessão
// ------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_name((string) config('app.session.name'));
    session_set_cookie_params([
        'lifetime' => (int) config('app.session.lifetime'),
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ------------------------------------------------------------
// 7. Router
// ------------------------------------------------------------
use App\Core\Router;

// Caminho do pedido sem o prefixo da instalação.
// Ex.: /monana-eclesia/public/login  ou  /monana-eclesia/login  ->  /login
$path      = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
$rootDir   = rtrim(str_replace('\\', '/', dirname($scriptDir)), '/');

foreach ([$scriptDir, $rootDir] as $prefix) {
    if ($prefix === '' || $prefix[0] !== '/') {
        continue;
    }
    if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
        $path = substr($path, strlen($prefix)) ?: '/';
        break;
    }
}

// Caminho já sem prefixo (usado, por exemplo, pelo menu para marcar o item activo)
$_SERVER['APP_ROUTE'] = $path;

$router = new Router();
require dirname(__DIR__) . '/config/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'], $path);
