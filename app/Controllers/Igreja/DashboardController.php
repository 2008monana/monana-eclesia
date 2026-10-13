<?php
namespace App\Controllers\Igreja;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Ecrã de entrada seguro enquanto os módulos de negócio são migrados na Fase 5. */
final class DashboardController extends Controller
{
    public function index(): Response
    {
        $guard = (new AuthMiddleware())->handle($this->request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $user = Session::get('_auth_user', []);
        if (is_array($user) && ($user['papel'] ?? '') === 'superadmin') {
            return Response::redirect(url('superadmin'));
        }
        $tenantId = TenantMiddleware::id();
        if ($tenantId === null) {
            return Response::text('Não foi possível determinar a igreja autenticada.', 403);
        }
        $view = new View();
        $authenticatedUser = is_array($user) ? $user : [];
        $churchName = trim((string) ($authenticatedUser['igreja_nome'] ?? ''));
        if ($churchName === '') {
            $churchName = 'A tua igreja';
        }
        $content = $view->render('igreja/dashboard', [
            'churchName' => $churchName,
        ]);
        $html = $view->render('layouts/app', [
            'content' => $content,
            'title' => 'Painel da igreja',
            'area' => 'igreja',
            'active' => 'painel',
            'actorName' => (string) ($authenticatedUser['nome_completo'] ?? 'Utilizador'),
            'actorRole' => (string) ($authenticatedUser['papel'] ?? ''),
            'tenantName' => $churchName,
            'footerText' => 'MonanaEclésia · ' . $churchName . ' · Protecção de dados pessoais — Lei n.º 22/11 de Angola',
        ]);
        return Response::html($html);
    }
}
