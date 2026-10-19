<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DashboardStats;
use App\Core\View;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        $igrejaId = (int) $user['igreja_id'];
        $stats    = DashboardStats::forTenant($igrejaId);

        // Nome da igreja
        $igreja = \App\Core\Database::first(
            'SELECT `nome`, `logo` FROM `igrejas` WHERE `id` = :i LIMIT 1',
            ['i' => $igrejaId]
        );

        View::render('dashboard/index', [
            'title'  => 'Painel — Monana Eclesia',
            'user'   => $user,
            'igreja' => $igreja,
            'stats'  => $stats,
        ], 'layouts/app');
    }
}