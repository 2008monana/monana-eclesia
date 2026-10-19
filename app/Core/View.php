<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Renderiza views PHP com layout.
 *
 * Uso:
 *   View::render('auth/login-user', ['title' => 'Login'], 'layouts/auth');
 */
final class View
{
    public static function render(string $view, array $data = [], ?string $layout = null): void
    {
        $viewFile = VIEWS_PATH . DIRECTORY_SEPARATOR
                  . str_replace(['.', '/'], DIRECTORY_SEPARATOR, $view) . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException("View não encontrada: {$view} ({$viewFile})");
        }

        // Torna os dados acessíveis como variáveis no template
        extract($data, EXTR_SKIP);

        // Captura o conteúdo da view
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Se tem layout, injeta o conteúdo
        if ($layout !== null) {
            $layoutFile = VIEWS_PATH . DIRECTORY_SEPARATOR
                        . str_replace(['.', '/'], DIRECTORY_SEPARATOR, $layout) . '.php';

            if (!is_file($layoutFile)) {
                throw new RuntimeException("Layout não encontrado: {$layout}");
            }

            require $layoutFile;
            return;
        }

        echo $content;
    }

    public static function exists(string $view): bool
    {
        $file = VIEWS_PATH . DIRECTORY_SEPARATOR
              . str_replace(['.', '/'], DIRECTORY_SEPARATOR, $view) . '.php';
        return is_file($file);
    }
}