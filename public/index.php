<?php
// Único front controller: todos os pedidos dinâmicos passam por este ficheiro.
if (!defined('MONANA')) {
    define('MONANA', true);
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/bootstrap.php';
\App\Core\Session::start();

$appConfiguration = \App\Core\Config::get('app', []);
$request = \App\Core\Request::fromGlobals($appConfiguration);
\App\Core\Response::applySecurityHeaders();

// Em produção, o domínio HTTPS tem de vir de APP_URL (não confiamos no Host
// recebido do navegador para construir um redireccionamento de segurança).
if (!empty($appConfiguration['force_https']) && !$request->isSecure()) {
    $configuredUrl = trim((string) ($appConfiguration['url'] ?? ''));
    $urlParts = $configuredUrl !== '' ? parse_url($configuredUrl) : false;
    if (!is_array($urlParts) || empty($urlParts['host'])) {
        \App\Core\Response::text(
            'Configuração incompleta: define APP_URL com o domínio HTTPS da instalação.',
            503
        )->send($request->method());
        exit;
    }

    $httpsBase = preg_replace('#^http://#i', 'https://', $configuredUrl);
    $location = rtrim((string) $httpsBase, '/')
        . ($request->path() === '/' ? '/' : $request->path());
    $queryString = $request->queryString();
    if ($queryString !== '') {
        $location .= '?' . $queryString;
    }

    $httpsStatus = in_array($request->method(), ['GET', 'HEAD'], true) ? 301 : 308;
    \App\Core\Response::redirect($location, $httpsStatus)->send($request->method());
    exit;
}

$router = new \App\Core\Router($request, $appConfiguration);
$routeRegistrar = require BASE_PATH . '/routes/web.php';
if (!is_callable($routeRegistrar)) {
    throw new \RuntimeException('O ficheiro routes/web.php não registou rotas válidas.');
}
call_user_func($routeRegistrar, $router);

$response = $router->dispatch();
$response->send($request->method());
