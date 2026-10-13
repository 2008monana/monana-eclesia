<?php
namespace App\Controllers\Igreja;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Services\ModuleAccessService;
use App\Services\DashboardService;

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
        $allowedModules = (new ModuleAccessService())->modules(
            (int) ($authenticatedUser['id'] ?? 0),
            $tenantId
        );
        $dashboardData = (new DashboardService())->resumo($tenantId, $allowedModules);
        $content = $view->render('igreja/dashboard', array_merge($dashboardData, [
            'churchName' => $churchName,
            'hasMigratedModules' => $dashboardData['activados'] !== [],
        ]));
        $html = $view->render('layouts/app', [
            'content' => $content,
            'title' => 'Painel da igreja',
            'area' => 'igreja',
            'active' => 'painel',
            'actorName' => (string) ($authenticatedUser['nome_completo'] ?? 'Utilizador'),
            'actorRole' => (string) ($authenticatedUser['papel'] ?? ''),
            'tenantName' => $churchName,
            'allowedModules' => $allowedModules,
            'membersEnabled' => Config::bool('MEMBERS_MVC_ENABLED', false),
            'registryEnabled' => Config::bool('COTAS_MVC_ENABLED', false),
            'listsEnabled' => Config::bool('LISTS_MVC_ENABLED', false),
            'expensesEnabled' => Config::bool('EXPENSES_MVC_ENABLED', false),
            'yearEndEnabled' => Config::bool('YEAR_END_MVC_ENABLED', false),
            'reportsEnabled' => Config::bool('REPORTS_MVC_ENABLED', false),
            'usersEnabled' => Config::bool('USERS_MVC_ENABLED', false),
            'auditEnabled' => Config::bool('AUDIT_MVC_ENABLED', false),
            'backupsEnabled' => Config::bool('BACKUPS_MVC_ENABLED', false),
            'profileEnabled' => Config::bool('PROFILE_MVC_ENABLED', false),
            'settingsEnabled' => Config::bool('SETTINGS_MVC_ENABLED', false),
            'footerText' => 'MonanaEclésia · ' . $churchName . ' · Protecção de dados pessoais — Lei n.º 22/11 de Angola',
        ]);
        return Response::html($html);
    }
}
