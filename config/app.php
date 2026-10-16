<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

$environment = strtolower((string) \App\Core\Config::env('APP_ENV', 'production'));
$appUrl = trim((string) \App\Core\Config::env('APP_URL', ''));

if ($appUrl !== '') {
    $urlParts = parse_url($appUrl);
    if (!is_array($urlParts)
        || empty($urlParts['host'])
        || !isset($urlParts['scheme'])
        || !in_array(strtolower($urlParts['scheme']), ['http', 'https'], true)
        || isset($urlParts['user'])
        || isset($urlParts['pass'])
        || isset($urlParts['query'])
        || isset($urlParts['fragment'])
    ) {
        throw new InvalidArgumentException('APP_URL tem de ser um URL HTTP(S) válido, sem credenciais.');
    }
}

$forceHttps = \App\Core\Config::bool(
    'APP_FORCE_HTTPS',
    !in_array($environment, ['local', 'testing'], true)
);
if ($forceHttps && $appUrl !== '' && stripos($appUrl, 'http://') === 0) {
    $appUrl = 'https://' . substr($appUrl, 7);
}

$explicitBasePath = \App\Core\Config::hasEnv('APP_BASE_PATH');
$basePath = \App\Core\Config::env('APP_BASE_PATH', null);
if ($basePath === null) {
    $urlPath = $appUrl !== '' ? parse_url($appUrl, PHP_URL_PATH) : '';
    $basePath = is_string($urlPath) ? $urlPath : '';
}
$basePath = trim((string) $basePath);
if ($basePath !== '' && $basePath !== '/') {
    $basePath = '/' . trim(preg_replace('#/+#', '/', $basePath), '/');
} else {
    $basePath = '';
}

if ($appUrl !== '' && $explicitBasePath) {
    $urlPath = parse_url($appUrl, PHP_URL_PATH);
    $urlPath = is_string($urlPath) ? '/' . trim($urlPath, '/') : '';
    if ($urlPath === '/') {
        $urlPath = '';
    }
    if ($urlPath === '' && $basePath !== '') {
        $appUrl = rtrim($appUrl, '/') . $basePath;
    } elseif ($urlPath !== $basePath) {
        throw new InvalidArgumentException('APP_URL e APP_BASE_PATH têm de usar o mesmo caminho base.');
    }
}

return [
    'name' => (string) \App\Core\Config::env('APP_NAME', 'MonanaEclésia'),
    'environment' => $environment,
    'debug' => \App\Core\Config::bool('APP_DEBUG', false),
    'url' => rtrim($appUrl, '/'),
    'base_path' => $basePath,
    'timezone' => (string) \App\Core\Config::env('APP_TIMEZONE', 'Africa/Luanda'),
    'force_https' => $forceHttps,
    'session' => [
        'name' => (string) \App\Core\Config::env('SESSION_NAME', 'monana_session'),
        'lifetime_seconds' => max(300, \App\Core\Config::int('SESSION_LIFETIME_MINUTES', 30) * 60),
        'base_path' => $basePath,
        'secure_cookie' => $forceHttps ? true : null,
    ],
];
