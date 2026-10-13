<?php
namespace App\Repositories;

use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Permissões por módulo sempre associadas ao utilizador e ao inquilino. */
class PermissionRepository
{
    public function createDefaults(PDO $pdo, int $userId, int $churchId, array $modules): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO permissoes_modulo (usuario_id, igreja_id, modulo, permitido) '
            . 'VALUES (:usuario_id, :igreja_id, :modulo, 1)'
        );
        foreach (array_values(array_unique($modules)) as $module) {
            $statement->execute([
                ':usuario_id' => $userId,
                ':igreja_id' => $churchId,
                ':modulo' => (string) $module,
            ]);
        }
    }

    /** Substitui a lista de permissões dentro da mesma igreja e transacção. */
    public function substituir(PDO $pdo, int $userId, int $churchId, array $modules): void
    {
        $delete = $pdo->prepare(
            'DELETE FROM permissoes_modulo WHERE usuario_id = :usuario_id AND igreja_id = :igreja_id'
        );
        $delete->execute([':usuario_id' => $userId, ':igreja_id' => $churchId]);
        $this->createDefaults($pdo, $userId, $churchId, $modules);
    }

    /** Consulta por tenant explícito obtido por TenantMiddleware, nunca pelo cliente. */
    public function allowedModules(int $userId, int $churchId): array
    {
        $statement = \App\Core\Database::connection()->prepare(
            'SELECT modulo FROM permissoes_modulo '
            . 'WHERE usuario_id = :usuario_id AND igreja_id = :igreja_id AND permitido = 1 ORDER BY modulo'
        );
        $statement->execute([':usuario_id' => $userId, ':igreja_id' => $churchId]);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
