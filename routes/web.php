<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/**
 * Rotas públicas novas em português. Até à migração de cada módulo, a rota
 * chama uma página procedural da allowlist; os endereços ingleses antigos
 * fazem 301 em GET/HEAD e mantêm POST apenas para compatibilidade transitória.
 */
return static function (\App\Core\Router $router): void {
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

    // Rotas canónicas em português; cada uma substitui a respectiva página antiga
    // quando o módulo for migrado nas fases seguintes.
    $portugueseRoutes = [
        '/login' => 'auth/login.php',
        '/sair' => 'auth/logout.php',
        '/recuperar-senha' => 'auth/forgot-password.php',
        '/resetar-senha' => 'auth/reset-password.php',
        '/painel' => 'dashboard/index.php',

        '/membros' => 'members/index.php',
        '/membros/novo' => 'members/add.php',
        '/membros/editar' => 'members/edit.php',
        '/membros/apagar' => 'members/delete.php',
        '/membros/alterar-estado' => 'members/toggle-status.php',
        '/membros/historico-pagamentos' => 'members/historico-pagamentos.php',
        '/membros/ver' => 'members/view.php',

        '/cotas' => 'registry/index.php',
        '/cotas/novo' => 'registry/add.php',
        '/cotas/editar' => 'registry/edit.php',
        '/cotas/apagar' => 'registry/delete.php',
        '/cotas/pdf' => 'registry/generate-pdf.php',
        '/cotas/recalcular-referencias' => 'registry/recalcular-referencias.php',

        '/listas' => 'lists/index.php',
        '/listas/pdf' => 'lists/generate-pdf.php',
        '/listas/jovens' => 'lists/jovens.php',
        '/listas/mamas' => 'lists/mamas.php',
        '/listas/papas' => 'lists/papas.php',

        '/saidas' => 'expenses/index.php',
        '/saidas/nova' => 'expenses/add.php',
        '/saidas/editar' => 'expenses/edit.php',
        '/saidas/apagar' => 'expenses/delete.php',

        '/contribuicoes-fim-de-ano' => 'year-end/index.php',
        '/contribuicoes-fim-de-ano/registar' => 'year-end/register.php',
        '/contribuicoes-fim-de-ano/remover' => 'year-end/remove.php',
        '/contribuicoes-fim-de-ano/alterar-estado' => 'year-end/toggle-status.php',
        '/contribuicoes-fim-de-ano/saldo' => 'year-end/get-saldo.php',
        '/contribuicoes-fim-de-ano/pdf' => 'year-end/generate-pdf.php',

        '/relatorios' => 'reports/index.php',
        '/relatorios/anual' => 'reports/annual.php',
        '/relatorios/despesas' => 'reports/expenses.php',
        '/relatorios/exportar-excel' => 'reports/export-excel.php',
        '/relatorios/pdf-despesas' => 'reports/generate-expenses-pdf.php',
        '/relatorios/pdf' => 'reports/generate-pdf.php',

        '/utilizadores' => 'users/index.php',
        '/utilizadores/novo' => 'users/add.php',
        '/utilizadores/editar' => 'users/edit.php',
        '/utilizadores/apagar' => 'users/delete.php',
        '/utilizadores/redefinir-senha' => 'users/reset-password.php',
        '/utilizadores/alterar-estado' => 'users/toggle-status.php',

        '/auditoria' => 'audit/index.php',
        '/auditoria/pdf' => 'audit/generate-pdf.php',

        '/copias-de-seguranca' => 'backups/index.php',
        '/copias-de-seguranca/exportar-excel' => 'backups/export-excel.php',
        '/copias-de-seguranca/exportar-sql' => 'backups/export-sql.php',
        '/perfil' => 'profile/index.php',
    ];

    foreach ($portugueseRoutes as $path => $file) {
        $handler = $legacy($file);
        $router->get($path, $handler);
        $router->post($path, $handler);
    }

    $portugueseIdRoutes = [
        '/membros/{id}' => 'members/view.php',
        '/membros/{id}/ver' => 'members/view.php',
        '/membros/{id}/editar' => 'members/edit.php',
        '/membros/{id}/apagar' => 'members/delete.php',
        '/membros/{id}/alterar-estado' => 'members/toggle-status.php',
        '/cotas/{id}/editar' => 'registry/edit.php',
        '/cotas/{id}/apagar' => 'registry/delete.php',
        '/saidas/{id}/editar' => 'expenses/edit.php',
        '/saidas/{id}/apagar' => 'expenses/delete.php',
        '/utilizadores/{id}/editar' => 'users/edit.php',
        '/utilizadores/{id}/apagar' => 'users/delete.php',
        '/utilizadores/{id}/alterar-estado' => 'users/toggle-status.php',
        '/contribuicoes-fim-de-ano/{id}/remover' => 'year-end/remove.php',
        '/contribuicoes-fim-de-ano/{id}/alterar-estado' => 'year-end/toggle-status.php',
    ];

    foreach ($portugueseIdRoutes as $path => $file) {
        $handler = $legacy($file);
        $router->get($path, $handler);
        $router->post($path, $handler);
    }

    // Rotas inglesas previamente publicadas: leitura faz 301 para pt-AO;
    // POST permanece ligado ao endpoint antigo até o formulário ser migrado.
    $englishAliases = [
        '/logout' => ['/sair', 'auth/logout.php'],
        '/forgot-password' => ['/recuperar-senha', 'auth/forgot-password.php'],
        '/reset-password' => ['/resetar-senha', 'auth/reset-password.php'],
        '/dashboard' => ['/painel', 'dashboard/index.php'],
        '/dashboard/index' => ['/painel', 'dashboard/index.php'],

        '/members' => ['/membros', 'members/index.php'],
        '/members/index' => ['/membros', 'members/index.php'],
        '/members/add' => ['/membros/novo', 'members/add.php'],
        '/members/edit' => ['/membros/editar', 'members/edit.php'],
        '/members/delete' => ['/membros/apagar', 'members/delete.php'],
        '/members/toggle-status' => ['/membros/alterar-estado', 'members/toggle-status.php'],
        '/members/historico-pagamentos' => ['/membros/historico-pagamentos', 'members/historico-pagamentos.php'],
        '/members/view' => ['/membros/ver', 'members/view.php'],

        '/registry' => ['/cotas', 'registry/index.php'],
        '/registry/index' => ['/cotas', 'registry/index.php'],
        '/registry/add' => ['/cotas/novo', 'registry/add.php'],
        '/registry/edit' => ['/cotas/editar', 'registry/edit.php'],
        '/registry/delete' => ['/cotas/apagar', 'registry/delete.php'],
        '/registry/generate-pdf' => ['/cotas/pdf', 'registry/generate-pdf.php'],
        '/registry/recalcular-referencias' => ['/cotas/recalcular-referencias', 'registry/recalcular-referencias.php'],

        '/lists' => ['/listas', 'lists/index.php'],
        '/lists/index' => ['/listas', 'lists/index.php'],
        '/lists/generate-pdf' => ['/listas/pdf', 'lists/generate-pdf.php'],
        '/lists/jovens' => ['/listas/jovens', 'lists/jovens.php'],
        '/lists/mamas' => ['/listas/mamas', 'lists/mamas.php'],
        '/lists/papas' => ['/listas/papas', 'lists/papas.php'],

        '/expenses' => ['/saidas', 'expenses/index.php'],
        '/expenses/index' => ['/saidas', 'expenses/index.php'],
        '/expenses/add' => ['/saidas/nova', 'expenses/add.php'],
        '/expenses/edit' => ['/saidas/editar', 'expenses/edit.php'],
        '/expenses/delete' => ['/saidas/apagar', 'expenses/delete.php'],

        '/year-end' => ['/contribuicoes-fim-de-ano', 'year-end/index.php'],
        '/year-end/index' => ['/contribuicoes-fim-de-ano', 'year-end/index.php'],
        '/year-end/register' => ['/contribuicoes-fim-de-ano/registar', 'year-end/register.php'],
        '/year-end/remove' => ['/contribuicoes-fim-de-ano/remover', 'year-end/remove.php'],
        '/year-end/toggle-status' => ['/contribuicoes-fim-de-ano/alterar-estado', 'year-end/toggle-status.php'],
        '/year-end/get-saldo' => ['/contribuicoes-fim-de-ano/saldo', 'year-end/get-saldo.php'],
        '/year-end/generate-pdf' => ['/contribuicoes-fim-de-ano/pdf', 'year-end/generate-pdf.php'],

        '/reports' => ['/relatorios', 'reports/index.php'],
        '/reports/index' => ['/relatorios', 'reports/index.php'],
        '/reports/annual' => ['/relatorios/anual', 'reports/annual.php'],
        '/reports/expenses' => ['/relatorios/despesas', 'reports/expenses.php'],
        '/reports/export-excel' => ['/relatorios/exportar-excel', 'reports/export-excel.php'],
        '/reports/generate-expenses-pdf' => ['/relatorios/pdf-despesas', 'reports/generate-expenses-pdf.php'],
        '/reports/generate-pdf' => ['/relatorios/pdf', 'reports/generate-pdf.php'],

        '/users' => ['/utilizadores', 'users/index.php'],
        '/users/index' => ['/utilizadores', 'users/index.php'],
        '/users/add' => ['/utilizadores/novo', 'users/add.php'],
        '/users/edit' => ['/utilizadores/editar', 'users/edit.php'],
        '/users/delete' => ['/utilizadores/apagar', 'users/delete.php'],
        '/users/reset-password' => ['/utilizadores/redefinir-senha', 'users/reset-password.php'],
        '/users/toggle-status' => ['/utilizadores/alterar-estado', 'users/toggle-status.php'],

        '/audit' => ['/auditoria', 'audit/index.php'],
        '/audit/index' => ['/auditoria', 'audit/index.php'],
        '/audit/generate-pdf' => ['/auditoria/pdf', 'audit/generate-pdf.php'],
        '/backups' => ['/copias-de-seguranca', 'backups/index.php'],
        '/backups/index' => ['/copias-de-seguranca', 'backups/index.php'],
        '/backups/export-excel' => ['/copias-de-seguranca/exportar-excel', 'backups/export-excel.php'],
        '/backups/export-sql' => ['/copias-de-seguranca/exportar-sql', 'backups/export-sql.php'],
        '/profile' => ['/perfil', 'profile/index.php'],
        '/profile/index' => ['/perfil', 'profile/index.php'],
    ];

    foreach ($englishAliases as $path => $targetAndFile) {
        $router->get($path, $redirect($targetAndFile[0]));
        $router->post($path, $legacy($targetAndFile[1]));
    }

    $englishIdAliases = [
        '/members/view/{id}' => ['/membros/{id}/ver', 'members/view.php'],
        '/members/edit/{id}' => ['/membros/{id}/editar', 'members/edit.php'],
        '/members/delete/{id}' => ['/membros/{id}/apagar', 'members/delete.php'],
        '/members/toggle-status/{id}' => ['/membros/{id}/alterar-estado', 'members/toggle-status.php'],
        '/registry/edit/{id}' => ['/cotas/{id}/editar', 'registry/edit.php'],
        '/registry/delete/{id}' => ['/cotas/{id}/apagar', 'registry/delete.php'],
        '/expenses/edit/{id}' => ['/saidas/{id}/editar', 'expenses/edit.php'],
        '/expenses/delete/{id}' => ['/saidas/{id}/apagar', 'expenses/delete.php'],
        '/users/edit/{id}' => ['/utilizadores/{id}/editar', 'users/edit.php'],
        '/users/delete/{id}' => ['/utilizadores/{id}/apagar', 'users/delete.php'],
        '/users/toggle-status/{id}' => ['/utilizadores/{id}/alterar-estado', 'users/toggle-status.php'],
        '/year-end/remove/{id}' => ['/contribuicoes-fim-de-ano/{id}/remover', 'year-end/remove.php'],
        '/year-end/toggle-status/{id}' => ['/contribuicoes-fim-de-ano/{id}/alterar-estado', 'year-end/toggle-status.php'],
    ];

    foreach ($englishIdAliases as $path => $targetAndFile) {
        $router->get($path, $redirect($targetAndFile[0]));
        $router->post($path, $legacy($targetAndFile[1]));
    }
};
