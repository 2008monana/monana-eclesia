<?php

declare(strict_types=1);

namespace App\Core;

/**
 * RBAC — verifica permissões (módulo + ação) do usuário logado.
 *
 * Regras:
 *   - Superadmin (guard 'supervisor') tem acesso a tudo (retorna true sempre).
 *   - User herda permissions das suas roles (user_roles → role_permissions).
 *   - User pode ter override em user_permissions (grant/deny).
 *   - Override deny prevalece sobre grant e sobre role.
 */
final class Rbac
{
    private static ?array $cache = null;

    /**
     * Verifica se o usuário atual tem a permissão "modulo.acao".
     * Ex.: Rbac::can('membros.ver')
     */
    public static function can(string $permission): bool
    {
        // Superadmin pode tudo
        if (Auth::check('supervisor')) {
            return true;
        }

        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $perms = self::userPermissions((int) $user['id']);
        return in_array($permission, $perms, true);
    }

    /**
     * Verifica se tem QUALQUER das permissões (útil para sidebar).
     */
    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (self::can($p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Carrega e retorna a lista de permissões "modulo.acao" do usuário.
     * Considera roles + overrides.
     */
    private static function userPermissions(int $userId): array
    {
        if (self::$cache !== null && (self::$cache['uid'] ?? null) === $userId) {
            return self::$cache['perms'];
        }

        // Permissões herdadas das roles
        $rows = Database::select(
            'SELECT DISTINCT p.modulo, p.acao
             FROM `user_roles` ur
             JOIN `role_permissions` rp ON rp.role_id = ur.role_id
             JOIN `permissions` p ON p.id = rp.permission_id
             WHERE ur.user_id = :uid',
            ['uid' => $userId]
        );

        $perms = [];
        foreach ($rows as $r) {
            $perms[] = $r['modulo'] . '.' . $r['acao'];
        }

        // Overrides (grant/deny)
        $overrides = Database::select(
            'SELECT p.modulo, p.acao, up.tipo
             FROM `user_permissions` up
             JOIN `permissions` p ON p.id = up.permission_id
             WHERE up.user_id = :uid',
            ['uid' => $userId]
        );

        foreach ($overrides as $o) {
            $key = $o['modulo'] . '.' . $o['acao'];
            if ($o['tipo'] === 'deny') {
                // Remove mesmo que exista
                $perms = array_values(array_diff($perms, [$key]));
            } elseif ($o['tipo'] === 'grant' && !in_array($key, $perms, true)) {
                $perms[] = $key;
            }
        }

        self::$cache = ['uid' => $userId, 'perms' => $perms];
        return $perms;
    }

    /**
     * Limpa o cache (chamar após atribuir/remover permissions).
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}