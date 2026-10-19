<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;

final class GuestMiddleware
{
    public function handle(): void
    {
        if (Auth::check('user')) {
            redirect('/dashboard');
        }
    }
}