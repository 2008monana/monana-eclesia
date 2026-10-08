<?php
// core/Router.php
// ============================================
// ROUTER da arquitectura MVC (front controller)
// ============================================
// Recebe o pedido, resolve a rota para um par Controlador::acção e
// despacha. As rotas mantêm os URLs já usados em todo o projecto:
//   /modules/<modulo>/<acao>.php  ->  <Modulo>Controller::<acao>()
//   /login, /dashboard ...        ->  aliases amigáveis

require_once BASE_PATH . '/config/bootstrap.php';
require_once BASE_PATH . '/core/View.php';
require_once BASE_PATH . '/core/Controller.php';

class Router
{
    /** Aliases de URLs amigáveis -> [Controlador, acção] */
    private const ALIASES = [
        'login'           => ['AuthController', 'login'],
        'recuperar-senha' => ['AuthController', 'forgotPassword'],
        'resetar-senha'   => ['AuthController', 'resetPassword'],
        'logout'          => ['AuthController', 'logout'],
        'dashboard'       => ['DashboardController', 'index'],
    ];

    /** Nome do controlador a partir do segmento do módulo. */
    private static function controllerName(string $modulo): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $modulo))) . 'Controller';
    }

    /** Converte um nome de ficheiro de acção em método camelCase. */
    private static function actionName(string $file): string
    {
        if (strpos($file, '-') === false) {
            return $file;
        }
        return lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $file))));
    }

    public static function dispatch(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = trim($uri, '/');

        // Remover possiveis prefixos de subpasta (ex.: /ipfva-gestao/...)
        // ate encontrar um segmento conhecido.
        $segments = $uri === '' ? [] : explode('/', $uri);

        // Alias amigável (login, dashboard, ...)
        foreach ($segments as $i => $seg) {
            if (isset(self::ALIASES[$seg])) {
                [$ctrl, $action] = self::ALIASES[$seg];
                self::call($ctrl, $action);
                return;
            }
            if ($seg === 'modules') {
                $modulo = $segments[$i + 1] ?? '';
                $file   = preg_replace('/\.php$/', '', $segments[$i + 2] ?? 'index');
                $ctrl   = self::controllerName($modulo);
                self::call($ctrl, self::actionName($file));
                return;
            }
            if ($seg === 'assets') {
                // Ficheiros estáticos servidos pelo servidor web; se
                // chegarem aqui (fallback), serve-os directamente.
                self::serveAsset(implode('/', array_slice($segments, 1)));
                return;
            }
        }

        // Página raiz -> login
        if ($segments === []) {
            redirect('login');
            return;
        }

        // Rota desconhecida -> 404
        self::notFound();
    }

    private static function call(string $ctrl, string $action): void
    {
        $path = BASE_PATH . '/controllers/' . $ctrl . '.php';
        if (!is_file($path)) {
            self::notFound();
        }
        require_once $path;
        if (!method_exists($ctrl, $action)) {
            self::notFound();
        }
        $instance = new $ctrl();
        $instance->$action();
    }

    private static function serveAsset(string $rel): void
    {
        $rel = realpath(BASE_PATH . '/' . $rel);
        if ($rel && strpos($rel, BASE_PATH . '/assets/') === 0 && is_file($rel)) {
            $mimes = [
                'css' => 'text/css', 'js' => 'application/javascript',
                'png' => 'image/png', 'svg' => 'image/svg+xml',
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'ico' => 'image/x-icon', 'woff2' => 'font/woff2',
            ];
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
            readfile($rel);
            exit;
        }
        self::notFound();
    }

    private static function notFound(): void
    {
        http_response_code(404);
        include BASE_PATH . '/paginas-erro/404.php';
        exit;
    }
}
