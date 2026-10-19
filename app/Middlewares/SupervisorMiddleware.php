<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;

final class SupervisorMiddleware
{
    public function handle(): void
    {
        if (!Auth::check('supervisor')) {
            flash('error', 'Acesso restrito à plataforma.');
            redirect('/superadmin/login');
        }
    }
}