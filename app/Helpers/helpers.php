<?php

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $cache = [];

        [$file, $path] = array_pad(explode('.', $key, 2), 2, null);

        if (!isset($cache[$file])) {
            $filePath = CONFIG_PATH . DIRECTORY_SEPARATOR . $file . '.php';
            if (!is_file($filePath)) {
                return $default;
            }
            $cache[$file] = require $filePath;
        }

        // config('database') -> devolve o ficheiro inteiro
        if ($path === null) {
            return $cache[$file];
        }

        $current = $cache[$file];
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}

if (!function_exists('esc')) {
    function esc(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('fmtKz')) {
    function fmtKz(float|int|string|null $value): string
    {
        $value = (float) ($value ?? 0);
        return number_format($value, 2, ',', '.') . ' Kz';
    }
}

if (!function_exists('base_url')) {
    /**
     * URL base da aplicação, sem barra final.
     * Usa APP_URL (.env); se vazio, deduz a partir do pedido
     * (ex.: http://localhost/monana-eclesia/public).
     */
    function base_url(): string
    {
        $configured = trim((string) env('APP_URL', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

        return ($https ? 'https' : 'http') . '://' . $host . $script;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        if (!preg_match('#^https?://#i', $path)) {
            $path = base_url() . '/' . ltrim($path, '/');
        }
        header('Location: ' . $path, true, 302);
        exit;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return base_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url(ltrim($path, '/'));
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $data = $_SESSION['_old'] ?? [];
        return $data[$key] ?? $default;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }
}

if (!function_exists('get_flash')) {
    function get_flash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . esc(csrf_token()) . '">';
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): never
    {
        echo '<pre style="background:#111;color:#eee;padding:16px;border-radius:8px;font-size:13px;overflow:auto">';
        foreach ($values as $v) {
            var_dump($v);
            echo "\n";
        }
        echo '</pre>';
        exit;
    }
}

if (!function_exists('logger')) {
    function logger(string $level, string $message, array $context = []): void
    {
        $dir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );
        @file_put_contents($file, $line, FILE_APPEND);
    }
}

if (!function_exists('iniciais')) {
    /**
     * Devolve as iniciais de um nome: "Ana Paula Santos" → "AS".
     * Usa o primeiro e o último nome, se houver mais de um.
     */
    function iniciais(?string $nome): string
    {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return '?';
        }

        $partes = preg_split('/\s+/', $nome);
        if (count($partes) === 1) {
            return mb_strtoupper(mb_substr($partes[0], 0, 1));
        }

        $primeiro = $partes[0];
        $ultimo   = $partes[count($partes) - 1];

        return mb_strtoupper(
            mb_substr($primeiro, 0, 1) . mb_substr($ultimo, 0, 1)
        );
    }
}

if (!function_exists('formatBytes')) {
    /**
     * Formata bytes em unidade legível: 1024 → "1 KB".
     */
    function formatBytes(int $bytes, int $precision = 1): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / (1024 ** $i), $precision) . ' ' . $unidades[$i];
    }
}