<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router simples com suporte a:
 * - métodos HTTP (GET, POST)
 * - parâmetros de URL {id}
 * - middlewares por rota
 * - grupos com prefixo + middlewares
 * - handler como [Controller::class, 'method'] OU Closure
 *
 * IMPORTANTE: não carregar constants.php aqui. O bootstrap (public/index.php)
 * já o faz ANTES do autoload. O Router apenas usa config() quando necessário.
 */
final class Router
{
    private array $routes = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    // ========================================================
    // Registo de rotas
    // ========================================================

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $oldPrefix  = $this->prefix;
        $oldGroupMw = $this->groupMiddleware;

        $this->prefix          .= rtrim($prefix, '/');
        $this->groupMiddleware  = array_merge($this->groupMiddleware, $middleware);

        $callback($this);

        $this->prefix          = $oldPrefix;
        $this->groupMiddleware = $oldGroupMw;
    }

    private function add(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $fullPath = $this->prefix . '/' . ltrim($path, '/');
        $fullPath = rtrim($fullPath, '/') ?: '/';

        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $fullPath);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
            'path'       => $fullPath,
        ];
    }

    // ========================================================
    // Dispatch
    // ========================================================

    public function dispatch(string $method, string $uri): void
    {
        $uri = '/' . ltrim(parse_url($uri, PHP_URL_PATH) ?? '/', '/');
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            foreach ($route['middleware'] as $mw) {
                $this->runMiddleware($mw);
            }

            $handler = $route['handler'];

            // Closure
            if ($handler instanceof \Closure) {
                $handler(...array_values($params));
                return;
            }

            // [Controller::class, 'method']
            [$class, $action] = $handler;
            if (!class_exists($class)) {
                $this->abort(500, "Controller não encontrado: {$class}");
            }
            $controller = new $class();
            if (!method_exists($controller, $action)) {
                $this->abort(500, "Método não encontrado: {$class}@{$action}");
            }
            $controller->$action(...array_values($params));
            return;
        }

        $this->abort(404, 'Página não encontrada');
    }

    private function runMiddleware(string $name): void
{
    // Suporte a perm:membros.ver
    if (str_starts_with($name, 'perm:')) {
        $permission = substr($name, 5);
        $class = \App\Middlewares\PermissionMiddleware::class;
        (new $class($permission))->handle();
        return;
    }

    $map = [
        'auth'         => \App\Middlewares\AuthMiddleware::class,
        'supervisor'   => \App\Middlewares\SupervisorMiddleware::class,
        'tenant'       => \App\Middlewares\TenantMiddleware::class,
        'guest'        => \App\Middlewares\GuestMiddleware::class,
        'csrf'         => \App\Middlewares\CsrfMiddleware::class,
        'subscription' => \App\Middlewares\SubscriptionMiddleware::class,
    ];

    if (!isset($map[$name])) {
        throw new \RuntimeException("Middleware desconhecido: {$name}");
    }

    $class = $map[$name];

    // PermissionMiddleware precisa de parâmetro
    if ($class === \App\Middlewares\PermissionMiddleware::class) {
        (new $class(''))->handle();
        return;
    }

    (new $class())->handle();
}
    private function abort(int $code, string $message): never
    {
        http_response_code($code);
        if (config('app.debug')) {
            echo "<h1>{$code}</h1><p>" . esc($message) . "</p>";
        } else {
            echo "<h1>{$code}</h1>";
        }
        exit;
    }
}