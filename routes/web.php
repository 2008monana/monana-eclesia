<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/**
 * Rotas canónicas em português. Os módulos migrados usam controllers MVC;
 * apenas os endpoints transitórios explicitamente marcados recorrem à ponte
 * fail-closed, e os aliases ingleses de leitura fazem 301 para pt-AO.
 */
return static function (\App\Core\Router $router): void {
    // Rotas novas de autenticação, setup inicial e Superadmin.
    $router->get('/instalacao/superadmin', [\App\Controllers\Auth\SetupSuperadminController::class, 'form']);
    $router->post('/instalacao/superadmin', [\App\Controllers\Auth\SetupSuperadminController::class, 'create']);

    $router->get('/login', [\App\Controllers\Auth\LoginController::class, 'index']);
    $router->post('/login', [\App\Controllers\Auth\LoginController::class, 'authenticate']);
    $router->post('/sair', [\App\Controllers\Auth\LoginController::class, 'logout']);
    $router->post('/logout', [\App\Controllers\Auth\LoginController::class, 'logout']);
    $router->post('/forgot-password', [\App\Controllers\Auth\SenhaController::class, 'requestReset']);
    $router->post('/reset-password', [\App\Controllers\Auth\SenhaController::class, 'submitReset']);
    $router->get('/recuperar-senha', [\App\Controllers\Auth\SenhaController::class, 'forgotForm']);
    $router->post('/recuperar-senha', [\App\Controllers\Auth\SenhaController::class, 'requestReset']);
    $router->get('/resetar-senha', [\App\Controllers\Auth\SenhaController::class, 'resetForm']);
    $router->post('/resetar-senha', [\App\Controllers\Auth\SenhaController::class, 'submitReset']);
    $router->get('/activar-conta', [\App\Controllers\Auth\AtivacaoController::class, 'form']);
    $router->post('/activar-conta', [\App\Controllers\Auth\AtivacaoController::class, 'activate']);
    $router->get('/trocar-senha', [\App\Controllers\Auth\SenhaController::class, 'changeForm']);
    $router->post('/trocar-senha', [\App\Controllers\Auth\SenhaController::class, 'change']);
    $router->post('/sessoes/terminar-todas', [\App\Controllers\Auth\SenhaController::class, 'terminateAll']);

    $router->get('/painel', [\App\Controllers\Igreja\DashboardController::class, 'index']);
    $router->get('/superadmin', [\App\Controllers\Superadmin\DashboardController::class, 'index']);
    $router->get('/superadmin/igrejas', [\App\Controllers\Superadmin\IgrejaController::class, 'index']);
    $router->get('/superadmin/igrejas/nova', [\App\Controllers\Superadmin\IgrejaController::class, 'createForm']);
    $router->post('/superadmin/igrejas', [\App\Controllers\Superadmin\IgrejaController::class, 'create']);
    $router->get('/superadmin/igrejas/{id}/editar', [\App\Controllers\Superadmin\IgrejaController::class, 'editForm']);
    $router->post('/superadmin/igrejas/{id}/editar', [\App\Controllers\Superadmin\IgrejaController::class, 'update']);
    $router->post('/superadmin/igrejas/{id}/estado', [\App\Controllers\Superadmin\IgrejaController::class, 'setStatus']);
    $router->post('/superadmin/emails/{id}/reenviar', [\App\Controllers\Superadmin\IgrejaController::class, 'resendEmail']);

    $router->get('/superadmin/planos', [\App\Controllers\Superadmin\PlanoController::class, 'index']);
    $router->get('/superadmin/planos/novo', [\App\Controllers\Superadmin\PlanoController::class, 'createForm']);
    $router->post('/superadmin/planos', [\App\Controllers\Superadmin\PlanoController::class, 'create']);
    $router->get('/superadmin/planos/{id}/editar', [\App\Controllers\Superadmin\PlanoController::class, 'editForm']);
    $router->post('/superadmin/planos/{id}/editar', [\App\Controllers\Superadmin\PlanoController::class, 'update']);
    $router->post('/superadmin/planos/{id}/apagar', [\App\Controllers\Superadmin\PlanoController::class, 'delete']);
    $router->get('/', static function (): \App\Core\Response {
        return \App\Core\Response::redirect(url('login'));
    });

    $legacy = static function (string $file): callable {
        return static function (\App\Core\Request $request, array $parameters) use ($file): \App\Core\Response {
            return \App\Core\LegacyBridge::serve($file, $request, $parameters);
        };
    };

    $redirect = static function (string $target): callable {
        return static function (\App\Core\Request $request, array $parameters) use ($target): \App\Core\Response {
            $path = $target;
            foreach ($parameters as $name => $value) {
                $path = str_replace('{' . $name . '}', rawurlencode((string) $value), $path);
            }
            $location = url($path);
            $query = $request->queryString();
            if ($query !== '') {
                $location .= '?' . $query;
            }
            return \App\Core\Response::redirect($location, 301);
        };
    };

    // Membros, Cotas, Listas e módulos seguintes usam controladores MVC próprios.
    $memberController = \App\Controllers\Igreja\MembroController::class;
    $router->get('/membros', [$memberController, 'index']);
    $router->get('/membros/novo', [$memberController, 'createForm']);
    $router->post('/membros/novo', [$memberController, 'create']);
    $router->get('/membros/editar', [$memberController, 'editByQuery']);
    $router->post('/membros/editar', [$memberController, 'updateByQuery']);
    $router->post('/membros/apagar', [$memberController, 'deleteByPost']);
    $router->post('/membros/alterar-estado', [$memberController, 'toggleByPost']);
    $router->get('/membros/ver', [$memberController, 'showByQuery']);

    // O histórico de pagamentos continua bloqueado até migrar Cotas. Esta rota
    // específica tem de anteceder /membros/{id}.
    $pendingMemberHistory = $legacy('members/historico-pagamentos.php');
    $router->get('/membros/historico-pagamentos', $pendingMemberHistory);
    $router->post('/membros/historico-pagamentos', $pendingMemberHistory);

    $router->get('/membros/{id}/editar', [$memberController, 'editForm']);
    $router->post('/membros/{id}/editar', [$memberController, 'update']);
    $router->get('/membros/{id}/ver', [$memberController, 'showByLegacyRoute']);
    $router->post('/membros/{id}/apagar', [$memberController, 'delete']);
    $router->post('/membros/{id}/estado', [$memberController, 'toggleStatus']);
    $router->post('/membros/{id}/alterar-estado', [$memberController, 'toggleStatus']);
    $router->get('/membros/{id}', [$memberController, 'show']);

    // Endereços ingleses existentes redireccionam em GET/HEAD; POST usa o
    // controlador MVC (nunca volta a executar SQL procedural).
    foreach (['/members', '/members/index'] as $legacyMembersIndex) {
        $router->get($legacyMembersIndex, $redirect('/membros'));
    }
    $router->get('/members/add', $redirect('/membros/novo'));
    $router->post('/members/add', [$memberController, 'create']);
    $router->get('/members/edit', $redirect('/membros/editar'));
    $router->post('/members/edit', [$memberController, 'updateByQuery']);
    $router->get('/members/delete', $redirect('/membros'));
    $router->post('/members/delete', [$memberController, 'deleteByPost']);
    $router->get('/members/toggle-status', $redirect('/membros'));
    $router->post('/members/toggle-status', [$memberController, 'toggleByPost']);
    $router->get('/members/view', $redirect('/membros/ver'));
    $router->get('/members/view/{id}', $redirect('/membros/{id}'));
    $router->get('/members/edit/{id}', $redirect('/membros/{id}/editar'));
    $router->post('/members/edit/{id}', [$memberController, 'update']);
    $router->get('/members/delete/{id}', $redirect('/membros/{id}'));
    $router->post('/members/delete/{id}', [$memberController, 'delete']);
    $router->get('/members/toggle-status/{id}', $redirect('/membros/{id}'));
    $router->post('/members/toggle-status/{id}', [$memberController, 'toggleStatus']);

    $router->get('/members/historico-pagamentos', $redirect('/membros/historico-pagamentos'));
    $router->post('/members/historico-pagamentos', $pendingMemberHistory);

    // Cotas (segunda etapa da Fase 5); o recálculo permanece bloqueado até revisão funcional.
    $cotaController = \App\Controllers\Igreja\CotaController::class;
    $router->get('/cotas', [$cotaController, 'index']);
    $router->get('/cotas/novo', [$cotaController, 'createForm']);
    $router->post('/cotas/novo', [$cotaController, 'create']);
    $router->get('/cotas/editar', [$cotaController, 'editByQuery']);
    $router->post('/cotas/editar', [$cotaController, 'updateByQuery']);
    $router->post('/cotas/apagar', [$cotaController, 'deleteByPost']);
    $router->get('/cotas/{id}/editar', [$cotaController, 'editForm']);
    $router->post('/cotas/{id}/editar', [$cotaController, 'update']);
    $router->post('/cotas/{id}/apagar', [$cotaController, 'delete']);
    $pendingCotaRecalculation = $legacy('registry/recalcular-referencias.php');
    $router->get('/cotas/pdf', [$cotaController, 'pdf']);
    $router->get('/cotas/recalcular-referencias', $pendingCotaRecalculation);
    $router->post('/cotas/recalcular-referencias', $pendingCotaRecalculation);

    foreach (['/registry', '/registry/index'] as $legacyCotaIndex) {
        $router->get($legacyCotaIndex, $redirect('/cotas'));
    }
    $router->get('/registry/add', $redirect('/cotas/novo'));
    $router->post('/registry/add', [$cotaController, 'create']);
    $router->get('/registry/edit', $redirect('/cotas/editar'));
    $router->post('/registry/edit', [$cotaController, 'updateByQuery']);
    $router->get('/registry/delete', $redirect('/cotas'));
    $router->post('/registry/delete', [$cotaController, 'deleteByPost']);
    $router->get('/registry/generate-pdf', $redirect('/cotas/pdf'));
    $router->get('/registry/recalcular-referencias', $redirect('/cotas/recalcular-referencias'));
    $router->post('/registry/recalcular-referencias', $pendingCotaRecalculation);
    $router->get('/registry/edit/{id}', $redirect('/cotas/{id}/editar'));
    $router->post('/registry/edit/{id}', [$cotaController, 'update']);
    $router->get('/registry/delete/{id}', $redirect('/cotas'));
    $router->post('/registry/delete/{id}', [$cotaController, 'delete']);

    // Listas reutilizam os dados tenant-scoped de membros e cotas.
    $listaController = \App\Controllers\Igreja\ListaController::class;
    $router->get('/listas', [$listaController, 'index']);
    $router->get('/listas/mamas', [$listaController, 'mamas']);
    $router->get('/listas/papas', [$listaController, 'papas']);
    $router->get('/listas/jovens', [$listaController, 'jovens']);
    $router->get('/listas/criancas', [$listaController, 'criancas']);
    $router->get('/listas/pdf', [$listaController, 'pdf']);
    foreach (['/lists', '/lists/index'] as $legacyListaIndex) {
        $router->get($legacyListaIndex, $redirect('/listas'));
    }
    foreach (['mamas', 'papas', 'jovens'] as $legacyCategory) {
        $router->get('/lists/' . $legacyCategory, $redirect('/listas/' . $legacyCategory));
    }
    $router->get('/lists/generate-pdf', $redirect('/listas/pdf'));

    // Saídas mantêm a origem do fundo em cada registo; não há transferência entre fundos.
    $saidaController = \App\Controllers\Igreja\SaidaController::class;
    $router->get('/saidas', [$saidaController, 'index']);
    $router->get('/saidas/nova', [$saidaController, 'createForm']);
    $router->post('/saidas/nova', [$saidaController, 'create']);
    $router->get('/saidas/editar', [$saidaController, 'editByQuery']);
    $router->post('/saidas/editar', [$saidaController, 'updateByQuery']);
    $router->post('/saidas/apagar', [$saidaController, 'deleteByPost']);
    $router->get('/saidas/{id}/editar', [$saidaController, 'editForm']);
    $router->post('/saidas/{id}/editar', [$saidaController, 'update']);
    $router->post('/saidas/{id}/apagar', [$saidaController, 'delete']);
    foreach (['/expenses', '/expenses/index'] as $legacyExpenseIndex) {
        $router->get($legacyExpenseIndex, $redirect('/saidas'));
    }
    $router->get('/expenses/add', $redirect('/saidas/nova'));
    $router->post('/expenses/add', [$saidaController, 'create']);
    $router->get('/expenses/edit', $redirect('/saidas/editar'));
    $router->post('/expenses/edit', [$saidaController, 'updateByQuery']);
    $router->get('/expenses/delete', $redirect('/saidas'));
    $router->post('/expenses/delete', [$saidaController, 'deleteByPost']);
    $router->get('/expenses/edit/{id}', $redirect('/saidas/{id}/editar'));
    $router->post('/expenses/edit/{id}', [$saidaController, 'update']);
    $router->get('/expenses/delete/{id}', $redirect('/saidas'));
    $router->post('/expenses/delete/{id}', [$saidaController, 'delete']);

    // Contribuições de fim de ano: contribuição individual não excede a meta anual.
    $fimAnoController = \App\Controllers\Igreja\FimAnoController::class;
    $router->get('/contribuicoes-fim-de-ano', [$fimAnoController, 'index']);
    $router->post('/contribuicoes-fim-de-ano/registar', [$fimAnoController, 'registar']);
    $router->post('/contribuicoes-fim-de-ano/remover', [$fimAnoController, 'remover']);
    $router->post('/contribuicoes-fim-de-ano/alterar-estado', [$fimAnoController, 'alternarEstado']);
    $router->get('/contribuicoes-fim-de-ano/saldo', [$fimAnoController, 'saldo']);
    $router->get('/contribuicoes-fim-de-ano/pdf', [$fimAnoController, 'pdf']);
    foreach (['/year-end', '/year-end/index'] as $legacyFimAnoIndex) {
        $router->get($legacyFimAnoIndex, $redirect('/contribuicoes-fim-de-ano'));
    }
    $router->get('/year-end/register', $redirect('/contribuicoes-fim-de-ano'));
    $router->post('/year-end/register', [$fimAnoController, 'registar']);
    $router->get('/year-end/remove', $redirect('/contribuicoes-fim-de-ano'));
    $router->post('/year-end/remove', [$fimAnoController, 'remover']);
    $router->get('/year-end/toggle-status', $redirect('/contribuicoes-fim-de-ano'));
    $router->post('/year-end/toggle-status', [$fimAnoController, 'alternarEstado']);
    $router->get('/year-end/get-saldo', $redirect('/contribuicoes-fim-de-ano/saldo'));
    $router->post('/year-end/get-saldo', [$fimAnoController, 'saldo']);
    $router->get('/year-end/generate-pdf', $redirect('/contribuicoes-fim-de-ano/pdf'));

    // Relatórios consultam agregações isoladas por igreja; PDFs usam Dompdf.
    $relatorioController = \App\Controllers\Igreja\RelatorioController::class;
    $router->get('/relatorios', [$relatorioController, 'financeiro']);
    $router->get('/relatorios/anual', [$relatorioController, 'anual']);
    $router->get('/relatorios/despesas', [$relatorioController, 'saidas']);
    $router->get('/relatorios/exportar-excel', [$relatorioController, 'excel']);
    $router->get('/relatorios/pdf', [$relatorioController, 'pdf']);
    $router->get('/relatorios/pdf-despesas', [$relatorioController, 'pdfSaidas']);
    $reportAliases = [
        '/reports' => '/relatorios',
        '/reports/index' => '/relatorios',
        '/reports/annual' => '/relatorios/anual',
        '/reports/expenses' => '/relatorios/despesas',
        '/reports/export-excel' => '/relatorios/exportar-excel',
        '/reports/generate-expenses-pdf' => '/relatorios/pdf-despesas',
        '/reports/generate-pdf' => '/relatorios/pdf',
    ];
    foreach ($reportAliases as $path => $target) {
        $router->get($path, $redirect($target));
    }

    // Gestão local; o papel administrador da igreja não pode ser concedido pela UI.
    $utilizadorController = \App\Controllers\Igreja\UtilizadorController::class;
    $router->get('/utilizadores', [$utilizadorController, 'index']);
    $router->get('/utilizadores/novo', [$utilizadorController, 'createForm']);
    $router->post('/utilizadores/novo', [$utilizadorController, 'create']);
    $router->get('/utilizadores/editar', [$utilizadorController, 'editByQuery']);
    $router->post('/utilizadores/editar', [$utilizadorController, 'updateByQuery']);
    $router->post('/utilizadores/apagar', [$utilizadorController, 'remover']);
    $router->post('/utilizadores/alterar-estado', [$utilizadorController, 'toggleStatus']);
    $router->post('/utilizadores/redefinir-senha', [$utilizadorController, 'enviarLinkSenha']);
    $router->get('/utilizadores/{id}/editar', [$utilizadorController, 'editForm']);
    $router->post('/utilizadores/{id}/editar', [$utilizadorController, 'update']);
    $router->post('/utilizadores/{id}/estado', [$utilizadorController, 'toggleStatus']);
    $router->post('/utilizadores/{id}/apagar', [$utilizadorController, 'remover']);
    $router->post('/utilizadores/{id}/redefinir-senha', [$utilizadorController, 'enviarLinkSenha']);
    $userAliases = [
        '/users' => '/utilizadores',
        '/users/index' => '/utilizadores',
        '/users/add' => '/utilizadores/novo',
        '/users/edit' => '/utilizadores/editar',
        '/users/delete' => '/utilizadores',
        '/users/reset-password' => '/utilizadores',
        '/users/toggle-status' => '/utilizadores',
    ];
    foreach ($userAliases as $path => $target) {
        $router->get($path, $redirect($target));
    }
    $router->get('/users/edit/{id}', $redirect('/utilizadores/{id}/editar'));

    // Auditoria visível apenas a administradores locais, sempre tenant-scoped.
    $auditoriaController = \App\Controllers\Igreja\AuditoriaController::class;
    $router->get('/auditoria', [$auditoriaController, 'index']);
    $router->get('/auditoria/pdf', [$auditoriaController, 'pdf']);
    $router->get('/audit', $redirect('/auditoria'));
    $router->get('/audit/index', $redirect('/auditoria'));
    $router->get('/audit/generate-pdf', $redirect('/auditoria/pdf'));

    // Backups locais são JSON tenant-scoped; SQL executável e restauro ficam indisponíveis.
    $backupController = \App\Controllers\Igreja\BackupController::class;
    $router->get('/copias-de-seguranca', [$backupController, 'index']);
    $router->post('/copias-de-seguranca/exportar', [$backupController, 'exportar']);
    $router->get('/copias-de-seguranca/exportar-excel', $redirect('/copias-de-seguranca'));
    $router->get('/copias-de-seguranca/exportar-sql', $redirect('/copias-de-seguranca'));
    $router->get('/backups', $redirect('/copias-de-seguranca'));
    $router->get('/backups/index', $redirect('/copias-de-seguranca'));
    $router->get('/backups/export-excel', $redirect('/copias-de-seguranca'));
    $router->get('/backups/export-sql', $redirect('/copias-de-seguranca'));

    // Perfil pessoal; a alteração de senha continua no fluxo autenticado dedicado.
    $perfilController = \App\Controllers\Igreja\PerfilController::class;
    $router->get('/perfil', [$perfilController, 'index']);
    $router->post('/perfil', [$perfilController, 'actualizar']);
    $router->get('/profile', $redirect('/perfil'));
    $router->get('/profile/index', $redirect('/perfil'));

    $configuracaoController = \App\Controllers\Igreja\ConfiguracaoController::class;
    $router->get('/configuracoes', [$configuracaoController, 'index']);
    $router->post('/configuracoes', [$configuracaoController, 'actualizar']);
    $router->get('/settings', $redirect('/configuracoes'));
    $router->get('/settings/index', $redirect('/configuracoes'));

    // Os restantes caminhos ingleses redireccionam apenas os pedidos de leitura;
    // POST de autenticação já está ligado directamente aos controllers MVC acima.
    $englishReadAliases = [
        '/logout' => '/sair',
        '/forgot-password' => '/recuperar-senha',
        '/reset-password' => '/resetar-senha',
        '/dashboard' => '/painel',
        '/dashboard/index' => '/painel',
    ];
    foreach ($englishReadAliases as $path => $target) {
        $router->get($path, $redirect($target));
    }
};
