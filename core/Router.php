<?php
// core/Router.php
// ============================================
// ROUTER da arquitectura MVC (front controller)
// ============================================
// Recebe o pedido, resolve a rota para um par Controlador::acção e
// despacha. Rotas suportadas (sempre com o prefixo APP_BASE):
//   /<modulo>            ->  <Modulo>Controller::index()
//   /<modulo>/<acao>     ->  <Modulo>Controller::<acao>()
//   /login, /dashboard ..->  aliases amigáveis
//   /modules/...         ->  redirecionamento 301 p/ rota nova

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
        if (ctype_digit($file)) {
            return 'index'; // ex.: /members/edit/5 -> index com id=5
        }
        if (strpos($file, '-') === false) {
            return $file;
        }
        return lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $file))));
    }

    /** Rotas legadas /modules/<m>/<acao>.php -> rota amigável <m>/<acao> */
    private const LEGACY_REDIRECTS = [
        'modules/auth/login'            => 'login',
        'modules/auth/logout'           => 'logout',
        'modules/auth/forgot-password'  => 'recuperar-senha',
        'modules/auth/reset-password'   => 'resetar-senha',
    ];

    public static function dispatch(): void
    {
        require_once BASE_PATH . '/config/bootstrap.php';

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = trim($uri, '/');

        // Remover o prefixo da subpasta de instalação (APP_BASE),
        // ex.: /monana-eclesia/members -> members
        $base = trim((string) preg_replace('#^https?://[^/]+#', '', APP_BASE), '/');
        if ($base !== '') {
            if ($uri === $base) {
                $uri = '';
            } elseif (strpos($uri, $base . '/') === 0) {
                $uri = substr($uri, strlen($base) + 1);
            }
        }
        $segments = $uri === '' ? [] : explode('/', $uri);

        // Redirecionamento 301 das rotas legadas /modules/... antigas
        $routePath = strtolower(preg_replace('/\.php$/', '', $uri));
        if (isset(self::LEGACY_REDIRECTS[$routePath])) {
            header('Location: ' . url(self::LEGACY_REDIRECTS[$routePath]), true, 301);
            exit;
        }
        if (strpos($routePath, 'modules/') === 0) {
            header('Location: ' . url($uri), true, 301);
            exit;
        }

        // Alias amigável (login, dashboard, ...)
        if ($segments !== [] && isset(self::ALIASES[$segments[0]])) {
            [$ctrl, $action] = self::ALIASES[$segments[0]];
            self::call($ctrl, $action);
            return;
        }

        // Rota de módulo: /members, /members/add, /year-end ...
        if ($segments !== []) {
            $modulo = array_shift($segments);
            $file   = $segments[0] ?? 'index';
            // segmentos extra viram parâmetros:
            //   /members/view/5      -> $_GET['id']  = 5
            //   /audit/detalhe/12345 -> $_GET['p1']  = 12345
            if (isset($segments[1]) && ctype_digit($segments[1])) {
                $_GET['id'] = $segments[1];
            }
            for ($i = 1; isset($segments[$i]); $i++) {
                $_GET['p' . $i] = $segments[$i];
            }
            $ctrl = self::controllerName($modulo);
            if (is_file(BASE_PATH . '/controllers/' . $ctrl . '.php')) {
                self::call($ctrl, self::actionName($file));
                return;
            }
        }

        // Página raiz -> login
        if ($segments === [] || $uri === '') {
            redirect('login');
            return;
        }

        // Ficheiros estáticos servidos pelo servidor web; se
        // chegarem aqui (fallback), serve-os directamente.
        if ($segments[0] === 'assets') {
            self::serveAsset(implode('/', array_slice($segments, 1)));
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
