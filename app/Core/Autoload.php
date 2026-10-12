<?php
namespace App\Core;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/**
 * Carregador PSR-4 mínimo para a namespace App\.
 * Não depende do Composer em tempo de execução; o autoload do Composer
 * fica configurado em paralelo para instalação e optimização em produção.
 */
final class Autoload
{
    /** Regista o mapeamento App\ => /app. */
    public static function register(string $appDirectory): void
    {
        $root = realpath($appDirectory);
        if ($root === false) {
            throw new \RuntimeException('A pasta da aplicação não foi encontrada.');
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        spl_autoload_register(static function ($class) use ($rootPrefix, $root): void {
            $prefix = 'App\\';
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relativeClass = substr($class, strlen($prefix));
            if ($relativeClass === '' || strpos($relativeClass, '..') !== false) {
                return;
            }

            $file = $rootPrefix . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
            $realFile = realpath($file);
            if ($realFile === false || !is_file($realFile)) {
                return;
            }

            // Defesa adicional contra traversal e symlinks fora de /app.
            if (strpos($realFile, $rootPrefix) !== 0 && $realFile !== $root) {
                return;
            }

            require_once $realFile;
        });
    }
}
