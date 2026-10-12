<?php
namespace App\Core;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/**
 * Leitor pequeno de .env e de configuração carregada pela aplicação.
 * Não interpola variáveis e não imprime valores sensíveis em mensagens de erro.
 */
final class Config
{
    /** @var array<string,string> */
    private static $environment = [];

    /** @var array<string,mixed> */
    private static $items = [];

    /** Lê um ficheiro .env; as variáveis reais do servidor têm prioridade. */
    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (strncmp($line, 'export ', 7) === 0) {
                $line = trim(substr($line, 7));
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            $parsed = self::parseValue(substr($line, $separator + 1));
            $serverValue = getenv($key);
            $value = $serverValue === false ? $parsed : (string) $serverValue;

            self::$environment[$key] = $value;
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    /** Devolve uma variável de ambiente, ou o valor por defeito. */
    public static function env(string $key, $default = null)
    {
        $serverValue = getenv($key);
        if ($serverValue !== false) {
            return $serverValue;
        }

        if (array_key_exists($key, self::$environment)) {
            return self::$environment[$key];
        }

        return $default;
    }

    /** Indica se a variável existe, mesmo que o seu valor seja uma string vazia. */
    public static function hasEnv(string $key): bool
    {
        return getenv($key) !== false || array_key_exists($key, self::$environment);
    }

    /** Define uma secção de configuração da aplicação. */
    public static function set(string $key, $value): void
    {
        self::$items[$key] = $value;
    }

    /** Lê configuração por chave simples ou notação pontuada (ex.: app.name). */
    public static function get(string $key, $default = null)
    {
        if (array_key_exists($key, self::$items)) {
            return self::$items[$key];
        }

        if (strpos($key, '.') !== false) {
            $parts = explode('.', $key);
            $value = self::$items;
            foreach ($parts as $part) {
                if (!is_array($value) || !array_key_exists($part, $value)) {
                    return $default;
                }
                $value = $value[$part];
            }
            return $value;
        }

        return self::env($key, $default);
    }

    /** Converte valores comuns de .env em booleano. */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::env($key, null);
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /** Converte valores comuns de .env em inteiro. */
    public static function int(string $key, int $default = 0): int
    {
        $value = self::env($key, null);
        if ($value === null || $value === '') {
            return $default;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT);
        return $validated === false ? $default : (int) $validated;
    }

    /** Remove comentários finais e interpreta aspas simples ou duplas. */
    private static function parseValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        $last = substr($value, -1);
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $inner = substr($value, 1, -1);
            if ($first === '"') {
                $decoded = json_decode('"' . str_replace('"', '\\"', $inner) . '"');
                return is_string($decoded) ? $decoded : stripcslashes($inner);
            }
            return $inner;
        }

        $commentPosition = preg_match('/\s+#/', $value, $matches, PREG_OFFSET_CAPTURE);
        if ($commentPosition === 1) {
            $value = substr($value, 0, $matches[0][1]);
        }

        return trim($value);
    }
}
