<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\SuperadminStats;
use App\Core\View;

final class SuperadminController extends Controller
{
    public function dashboard(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) {
            redirect('/superadmin/login');
        }

        $stats = SuperadminStats::overview();

        View::render('superadmin/dashboard', [
            'title'      => 'Visão Geral — Superadmin',
            'supervisor' => $supervisor,
            'stats'      => $stats,
        ], 'layouts/superadmin');
    }
}