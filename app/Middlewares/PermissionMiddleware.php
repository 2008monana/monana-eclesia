<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Rbac;

/**
 * PermissionMiddleware — verifica uma permissão específica.
 *
 * Uso no Router:
 *   $router->get('/membros', [...], ['auth', 'tenant', 'perm:membros.ver']);
 *
 * O Router precisa passar o parâmetro. Como isso ainda não é suportado,
 * a assinatura fica como abaixo e o Router cria a instância com `new`.
 */
final class PermissionMiddleware
{
    public function __construct(private string $permission = '')
    {
    }

    public function handle(): void
    {
        if ($this->permission === '' || Rbac::can($this->permission)) {
            return;
        }

        // Se for uma action de escrita e não tiver permissão → erro
        flash('error', 'Não tem permissão para acessar esta área.');
        redirect('/dashboard');
    }
}