<?php

declare(strict_types=1);

namespace App\Middlewares;

final class CsrfMiddleware
{
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $token = $_POST['_token'] ?? '';
        $sess  = $_SESSION['_csrf'] ?? '';

        if (!$sess || !hash_equals((string) $sess, (string) $token)) {
            flash('error', 'Sessão expirada. Tente novamente.');

            // Só volta para a página anterior se for do próprio site (evita open redirect)
            $ref  = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            $base = base_url();
            redirect(($ref !== '' && str_starts_with($ref, $base)) ? $ref : '/login');
        }
    }
}
