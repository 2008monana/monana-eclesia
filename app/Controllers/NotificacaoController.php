<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Notifications;
use App\Core\Rbac;
use App\Core\Tenant;
use App\Core\View;

final class NotificacaoController extends Controller
{
    public function index(): void
    {
        if (!Rbac::can('assinaturas.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();
        $notificacoes = Notifications::historico($igrejaId, 100);

        View::render('notificacoes/index', [
            'title'        => 'Notificações de Assinatura — Monana Eclesia',
            'user'         => Auth::user(),
            'notificacoes' => $notificacoes,
        ], 'layouts/app');
    }
}