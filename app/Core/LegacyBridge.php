<?php
namespace App\Core;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/**
 * Ponte temporária para as páginas procedurais ainda não migradas.
 * Apenas ficheiros enumerados podem ser incluídos; a ponte será retirada
 * módulo a módulo quando cada equivalente MVC estiver validado.
 */
final class LegacyBridge
{
    /** @var array<int,string> */
    private const ALLOWED_FILES = [
        'auth/login.php', 'auth/logout.php', 'auth/forgot-password.php', 'auth/reset-password.php',
        'dashboard/index.php',
        'members/index.php', 'members/add.php', 'members/edit.php', 'members/delete.php',
        'members/toggle-status.php', 'members/historico-pagamentos.php', 'members/view.php',
        'registry/index.php', 'registry/add.php', 'registry/edit.php', 'registry/delete.php',
        'registry/generate-pdf.php', 'registry/recalcular-referencias.php',
        'lists/index.php', 'lists/generate-pdf.php', 'lists/jovens.php', 'lists/mamas.php', 'lists/papas.php',
        'expenses/index.php', 'expenses/add.php', 'expenses/edit.php', 'expenses/delete.php',
        'year-end/index.php', 'year-end/register.php', 'year-end/remove.php',
        'year-end/toggle-status.php', 'year-end/get-saldo.php', 'year-end/generate-pdf.php',
        'reports/index.php', 'reports/annual.php', 'reports/expenses.php',
        'reports/export-excel.php', 'reports/generate-expenses-pdf.php', 'reports/generate-pdf.php',
        'users/index.php', 'users/add.php', 'users/edit.php', 'users/delete.php',
        'users/reset-password.php', 'users/toggle-status.php',
        'audit/index.php', 'audit/generate-pdf.php',
        'backups/index.php', 'backups/export-excel.php', 'backups/export-sql.php',
        'profile/index.php',
    ];

    /** Inclui uma página legada dentro do front controller, nunca por caminho livre. */
    public static function serve(string $relativeFile, Request $request, array $parameters = []): Response
    {
        if (!in_array($relativeFile, self::ALLOWED_FILES, true)) {
            return Response::html((new View())->render('errors/404'), 404);
        }

        $moduleRoot = realpath(BASE_PATH . '/modules');
        $file = realpath(BASE_PATH . '/modules/' . $relativeFile);
        if ($moduleRoot === false || $file === false || !is_file($file)
            || strpos($file, rtrim($moduleRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0
        ) {
            return Response::html((new View())->render('errors/404'), 404);
        }

        // As páginas antigas ainda não filtram `igreja_id`. O fallback é
        // fail-closed: apenas uma rota MVC migrada poderá reactivar cada módulo.
        \App\Core\Session::start();
        if ((int) \App\Core\Session::get('auth_user_id', 0) < 1) {
            return Response::redirect(url('login'));
        }
        $authenticatedUser = \App\Core\Session::get('_auth_user', []);
        if (!is_array($authenticatedUser)) {
            return Response::redirect(url('login'));
        }
        if (($authenticatedUser['papel'] ?? '') === 'superadmin') {
            return Response::redirect(url('superadmin'));
        }
        return Response::html((new View())->render('errors/modulo-pendente'), 503);

    }
}
