<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Subscription;

final class AuthMiddleware
{
    public function handle(): void
    {
        if (!Auth::check('user')) {
            flash('error', 'Faça login para continuar.');
            redirect('/login');
        }

        // Guarda o status da assinatura em globals (usado pelo layout)
        $GLOBALS['__subscription_status'] = Subscription::realStatus();
        $GLOBALS['__subscription_banner'] = Subscription::bannerInfo();

        // Bloqueia escritas (POST) quando a assinatura está expirada/suspensa
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Subscription::canWrite()) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (!str_contains($uri, '/assinatura') && !str_contains($uri, '/logout')) {
                flash('error', 'Assinatura expirada ou suspensa. Não é possível realizar esta ação.');
                redirect('/assinatura');
            }
        }
    }
}