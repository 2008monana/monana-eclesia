<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;

/**
 * Fixa a igreja (tenant) do utilizador autenticado.
 * Falha em fechado: sem igreja válida/activa, o acesso é recusado.
 */
final class TenantMiddleware
{
    public function handle(): void
    {
        $user = Auth::user();

        if (!$user || empty($user['igreja_id'])) {
            $this->deny('Não foi possível identificar a sua igreja.');
        }

        $igreja = Database::first(
            'SELECT `id`, `status` FROM `igrejas` WHERE `id` = :id AND `deletado_em` IS NULL LIMIT 1',
            ['id' => (int) $user['igreja_id']]
        );

        if (!$igreja || in_array($igreja['status'], ['suspensa', 'cancelada'], true)) {
            $this->deny('O acesso desta igreja está suspenso. Contacte o suporte.');
        }

        Tenant::set((int) $igreja['id']);
    }

    private function deny(string $message): never
    {
        Auth::logoutUser();
        flash('error', $message);
        redirect('/login');
    }
}
