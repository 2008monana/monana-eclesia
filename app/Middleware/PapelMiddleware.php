<?php
namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Autorização RBAC por papel; só aplica-se depois de AuthMiddleware. */
final class PapelMiddleware
{
    public const ROLES = [
        'superadmin',
        'admin_igreja',
        'pastor',
        'tesoureiro',
        'secretario',
        'lider',
    ];

    /** Verifica o papel guardado pela autenticação já revalidada na base. */
    public function handle(array $allowedRoles, ?string $module = null): ?Response
    {
        $user = Session::get('_auth_user', []);
        $role = is_array($user) ? (string) ($user['papel'] ?? '') : '';
        if (!in_array($role, $allowedRoles, true)) {
            return Response::text('Não tens permissão para aceder a esta área.', 403);
        }
        if ($module !== null) {
            $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
            $churchId = TenantMiddleware::id();
            if ($userId < 1 || $churchId === null
                || !in_array($module, (new \App\Repositories\PermissionRepository())->allowedModules($userId, $churchId), true)
            ) {
                return Response::text('Este módulo não está atribuído à tua conta.', 403);
            }
        }
        return null;
    }

    public static function allows(string $role, array $allowedRoles): bool
    {
        return in_array($role, $allowedRoles, true);
    }
}
