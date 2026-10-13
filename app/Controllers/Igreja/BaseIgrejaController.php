<?php
namespace App\Controllers\Igreja;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\PapelMiddleware;
use App\Middleware\TenantMiddleware;
use App\Services\ModuleAccessService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Guarda e composição comuns aos módulos MVC exclusivos de uma igreja. */
abstract class BaseIgrejaController extends Controller
{
    /** @var int */
    protected $churchId = 0;

    /** @var array<string,mixed> */
    protected $actor = [];

    /** @var array<int,string> */
    protected $allowedModules = [];

    /**
     * Revalida sessão, papel, tenant e permissão persistida antes de qualquer
     * leitura ou escrita do módulo.
     */
    protected function authorizeModule(string $module, array $roles = []): ?Response
    {
        $auth = (new AuthMiddleware())->handle($this->request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $user = Session::get('_auth_user', []);
        if (!is_array($user)) {
            return Response::redirect(url('login'));
        }
        if (($user['papel'] ?? '') === 'superadmin') {
            return Response::redirect(url('superadmin'));
        }

        // As tabelas de negócio só recebem igreja_id na Fase 6. O código MVC
        // pode ser publicado antes, mas não consulta/escreve dados até à activação.
        $featureFlags = [
            'members' => 'MEMBERS_MVC_ENABLED',
            'registry' => 'COTAS_MVC_ENABLED',
            'lists' => 'LISTS_MVC_ENABLED',
            'expenses' => 'EXPENSES_MVC_ENABLED',
            'year_end' => 'YEAR_END_MVC_ENABLED',
            'reports' => 'REPORTS_MVC_ENABLED',
            'users' => 'USERS_MVC_ENABLED',
            'audit' => 'AUDIT_MVC_ENABLED',
            'backups' => 'BACKUPS_MVC_ENABLED',
            'profile' => 'PROFILE_MVC_ENABLED',
            'settings' => 'SETTINGS_MVC_ENABLED',
        ];
        if (isset($featureFlags[$module]) && !Config::bool($featureFlags[$module], false)) {
            return Response::text(
                'Este módulo está preparado, mas aguarda a migração tenant da Fase 6.',
                503
            );
        }

        if ($roles === []) {
            $roles = ['admin_igreja', 'pastor', 'tesoureiro', 'secretario', 'lider'];
        }
        $roleResponse = (new PapelMiddleware())->handle($roles);
        if ($roleResponse instanceof Response) {
            return $roleResponse;
        }

        $churchId = TenantMiddleware::id();
        $userId = (int) ($user['id'] ?? 0);
        if ($churchId === null || $userId < 1) {
            return Response::text('Não foi possível determinar a igreja autenticada.', 403);
        }

        $access = new ModuleAccessService();
        $this->allowedModules = $access->modules($userId, $churchId);
        if (!in_array($module, $this->allowedModules, true)) {
            return Response::text('Este módulo não está atribuído à tua conta.', 403);
        }

        $this->churchId = $churchId;
        $this->actor = $user;
        return null;
    }

    /** Envolve uma vista de conteúdo no shell autenticado partilhado. */
    protected function renderChurchModule(string $template, array $data, string $title, string $active, int $status = 200): Response
    {
        $content = $this->view->render($template, $data);
        $churchName = trim((string) ($this->actor['igreja_nome'] ?? ''));
        if ($churchName === '') {
            $churchName = 'A tua igreja';
        }
        $html = $this->view->render('layouts/app', [
            'content' => $content,
            'title' => $title,
            'area' => 'igreja',
            'active' => $active,
            'actorName' => (string) ($this->actor['nome_completo'] ?? 'Utilizador'),
            'actorRole' => (string) ($this->actor['papel'] ?? ''),
            'tenantName' => $churchName,
            'allowedModules' => $this->allowedModules,
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
        ]);
        return Response::html($html, $status);
    }
}
