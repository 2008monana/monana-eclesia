<?php
namespace App\Services;

use App\Repositories\PermissionRepository;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Consulta as permissões persistidas do utilizador dentro da igreja autenticada. */
final class ModuleAccessService
{
    /** @var PermissionRepository */
    private $permissions;

    public function __construct(?PermissionRepository $permissions = null)
    {
        $this->permissions = $permissions ?: new PermissionRepository();
    }

    /** A igreja é sempre recebida do contexto autenticado, nunca do pedido. */
    public function modules(int $userId, int $churchId): array
    {
        if ($userId < 1 || $churchId < 1) {
            return [];
        }
        return $this->permissions->allowedModules($userId, $churchId);
    }

    public function allows(int $userId, int $churchId, string $module): bool
    {
        return in_array($module, $this->modules($userId, $churchId), true);
    }
}
