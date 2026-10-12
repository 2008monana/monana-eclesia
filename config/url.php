<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

$appConfiguration = \App\Core\Config::get('app', []);
$appBase = isset($appConfiguration['base_path']) ? (string) $appConfiguration['base_path'] : '';
if (!defined('APP_BASE')) {
    define('APP_BASE', $appBase);
}

$configuredBaseUrl = isset($appConfiguration['url']) ? trim((string) $appConfiguration['url']) : '';
if ($configuredBaseUrl !== '') {
    $base_url = rtrim($configuredBaseUrl, '/');
} else {
    $isHttps = isset($_SERVER['HTTPS'])
        && strtolower((string) $_SERVER['HTTPS']) !== 'off'
        && (string) $_SERVER['HTTPS'] !== '0';
    $protocol = $isHttps ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
        $host = 'localhost';
    }
    $base_url = $protocol . '://' . $host . rtrim(APP_BASE, '/');
}

/**
 * Normaliza URLs antigas para a rota amigável actualmente registada.
 * Mantém o comportamento que esperam os formulários ainda não migrados.
 */
if (!function_exists('esconder_php')) {
    function esconder_php($path)
    {
        $path = ltrim((string) $path, '/');
        $query = '';
        if (strpos($path, '?') !== false) {
            $parts = explode('?', $path, 2);
            $path = $parts[0];
            $query = '?' . $parts[1];
        }
        if (substr($path, -4) === '.php') {
            $path = substr($path, 0, -4);
        }

        if (strpos($path, 'modules/') === 0) {
            $path = substr($path, strlen('modules/'));
            if (substr($path, -6) === '/index') {
                $path = substr($path, 0, -6);
            }
        }

        // Os módulos antigos chamam url('modules/...'); os seus links passam
        // agora pelas rotas portuguesas sem alterar os ficheiros ainda legados.
        $legacyRoutes = [
            'auth/login' => 'login',
            'auth/logout' => 'sair',
            'auth/forgot-password' => 'recuperar-senha',
            'auth/reset-password' => 'resetar-senha',
            'logout' => 'sair',
            'forgot-password' => 'recuperar-senha',
            'reset-password' => 'resetar-senha',
            'dashboard' => 'painel',
            'dashboard/index' => 'painel',
            'members' => 'membros',
            'members/index' => 'membros',
            'members/add' => 'membros/novo',
            'members/edit' => 'membros/editar',
            'members/delete' => 'membros/apagar',
            'members/toggle-status' => 'membros/alterar-estado',
            'members/historico-pagamentos' => 'membros/historico-pagamentos',
            'members/view' => 'membros/ver',
            'registry' => 'cotas',
            'registry/index' => 'cotas',
            'registry/add' => 'cotas/novo',
            'registry/edit' => 'cotas/editar',
            'registry/delete' => 'cotas/apagar',
            'registry/generate-pdf' => 'cotas/pdf',
            'registry/recalcular-referencias' => 'cotas/recalcular-referencias',
            'lists' => 'listas',
            'lists/index' => 'listas',
            'lists/generate-pdf' => 'listas/pdf',
            'lists/jovens' => 'listas/jovens',
            'lists/mamas' => 'listas/mamas',
            'lists/papas' => 'listas/papas',
            'expenses' => 'saidas',
            'expenses/index' => 'saidas',
            'expenses/add' => 'saidas/nova',
            'expenses/edit' => 'saidas/editar',
            'expenses/delete' => 'saidas/apagar',
            'year-end' => 'contribuicoes-fim-de-ano',
            'year-end/index' => 'contribuicoes-fim-de-ano',
            'year-end/register' => 'contribuicoes-fim-de-ano/registar',
            'year-end/remove' => 'contribuicoes-fim-de-ano/remover',
            'year-end/toggle-status' => 'contribuicoes-fim-de-ano/alterar-estado',
            'year-end/get-saldo' => 'contribuicoes-fim-de-ano/saldo',
            'year-end/generate-pdf' => 'contribuicoes-fim-de-ano/pdf',
            'reports' => 'relatorios',
            'reports/index' => 'relatorios',
            'reports/annual' => 'relatorios/anual',
            'reports/expenses' => 'relatorios/despesas',
            'reports/export-excel' => 'relatorios/exportar-excel',
            'reports/generate-expenses-pdf' => 'relatorios/pdf-despesas',
            'reports/generate-pdf' => 'relatorios/pdf',
            'users' => 'utilizadores',
            'users/index' => 'utilizadores',
            'users/add' => 'utilizadores/novo',
            'users/edit' => 'utilizadores/editar',
            'users/delete' => 'utilizadores/apagar',
            'users/reset-password' => 'utilizadores/redefinir-senha',
            'users/toggle-status' => 'utilizadores/alterar-estado',
            'audit' => 'auditoria',
            'audit/index' => 'auditoria',
            'audit/generate-pdf' => 'auditoria/pdf',
            'backups' => 'copias-de-seguranca',
            'backups/index' => 'copias-de-seguranca',
            'backups/export-excel' => 'copias-de-seguranca/exportar-excel',
            'backups/export-sql' => 'copias-de-seguranca/exportar-sql',
            'profile' => 'perfil',
            'profile/index' => 'perfil',
        ];

        if (isset($legacyRoutes[$path])) {
            $path = $legacyRoutes[$path];
        }
        return $path . $query;
    }
}

if (!function_exists('asset')) {
    /** Gera URL absoluta para um ficheiro estático público. */
    function asset($path)
    {
        global $base_url;
        return rtrim($base_url, '/') . '/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('url')) {
    /** Gera URL absoluta para uma rota ou ficheiro estático. */
    function url($path)
    {
        global $base_url;
        return rtrim($base_url, '/') . '/' . esconder_php($path);
    }
}

if (!function_exists('url_relative')) {
    function url_relative($path)
    {
        return url($path);
    }
}

if (!function_exists('redirect')) {
    /** Redirecciona apenas para uma rota interna; evita open redirects. */
    function redirect($path)
    {
        $path = (string) $path;
        if (strpos($path, "\r") !== false || strpos($path, "\n") !== false) {
            http_response_code(400);
            exit;
        }
        header('Location: ' . url($path), true, 302);
        exit;
    }
}

if (!function_exists('debug_url')) {
    function debug_url()
    {
        global $base_url;
        echo '<h3>Configuração de URL</h3><p>'
            . htmlspecialchars($base_url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</p>';
    }
}
