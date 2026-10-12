<?php
namespace App\Core;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Renderizador de vistas PHP isolado da lógica de negócio. */
final class View
{
    /** @var string */
    private $root;

    public function __construct(string $root = '')
    {
        if ($root === '') {
            if (!defined('BASE_PATH')) {
                throw new \RuntimeException('BASE_PATH não está definido.');
            }
            $root = BASE_PATH . '/app/Views';
        }
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
    }

    /** Renderiza uma vista e devolve o HTML, sem o enviar directamente. */
    public function render(string $name, array $data = []): string
    {
        $relative = str_replace('\\', '/', trim($name, '/'));
        if ($relative === '' || strpos($relative, '..') !== false
            || !preg_match('#^[A-Za-z0-9_/-]+$#', $relative)
        ) {
            throw new \InvalidArgumentException('Nome de vista inválido.');
        }

        $candidate = $this->root . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative) . '.php';
        $root = realpath($this->root);
        $file = realpath($candidate);
        if ($root === false || $file === false || !is_file($file)
            || strpos($file, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0
        ) {
            throw new \RuntimeException('A vista solicitada não foi encontrada.');
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
