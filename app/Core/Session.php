<?php
namespace App\Core;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Sessão PHP endurecida, com SameSite e expiração por inactividade. */
final class Session
{
    /** @var array<string,mixed> */
    private static $settings = [];

    /** Configura os cookies antes de session_start(), sem abrir a sessão. */
    public static function configure(array $settings = []): void
    {
        self::$settings = array_merge([
            'name' => 'monana_session',
            'lifetime_seconds' => 1800,
            'base_path' => '',
            'secure_cookie' => null,
        ], $settings);

        if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
            return;
        }

        $name = (string) self::$settings['name'];
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name)) {
            $name = 'monana_session';
        }
        session_name($name);

        $lifetime = max(300, (int) self::$settings['lifetime_seconds']);
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        $secure = self::$settings['secure_cookie'];
        if ($secure === null) {
            $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
            $secure = ($https !== '' && $https !== 'off' && $https !== '0')
                || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        }

        $basePath = trim((string) self::$settings['base_path']);
        $cookiePath = $basePath === '' || $basePath === '/' ? '/' : '/' . trim($basePath, '/') . '/';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'domain' => '',
            'secure' => (bool) $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** Abre a sessão, aplica a configuração e actualiza a expiração. */
    public static function start(array $settings = []): void
    {
        if ($settings !== []) {
            self::configure($settings);
        } elseif (self::$settings === []) {
            self::configure();
        }

        if (session_status() === PHP_SESSION_NONE) {
            if (headers_sent()) {
                throw new \RuntimeException('A sessão não pode iniciar depois do envio de conteúdo.');
            }
            session_start();
        }

        self::refreshInactivity();
    }

    /** Gera um identificador novo, descartando o antigo. Chamar após autenticação. */
    public static function regenerate(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        return session_regenerate_id(true);
    }

    public static function get(string $key, $default = null)
    {
        return isset($_SESSION) && array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION) && array_key_exists($key, $_SESSION);
    }

    public static function set(string $key, $value): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }
        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void
    {
        if (isset($_SESSION) && array_key_exists($key, $_SESSION)) {
            unset($_SESSION[$key]);
        }
    }

    /** Termina a sessão actual e remove o cookie do navegador. */
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        $parameters = session_get_cookie_params();
        if (!headers_sent()) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'] ?? '/',
                'domain' => $parameters['domain'] ?? '',
                'secure' => (bool) ($parameters['secure'] ?? false),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }

    /** Devolve o tempo de expiração em segundos. */
    public static function lifetime(): int
    {
        return max(300, (int) (self::$settings['lifetime_seconds'] ?? 1800));
    }

    private static function refreshInactivity(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $now = time();
        $lastActivity = isset($_SESSION['_monana_last_activity'])
            ? (int) $_SESSION['_monana_last_activity']
            : $now;

        if ($lastActivity > 0 && ($now - $lastActivity) > self::lifetime()) {
            $_SESSION = [];
            session_regenerate_id(true);
        }

        $_SESSION['_monana_last_activity'] = $now;
    }
}
