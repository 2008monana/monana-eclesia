<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Consultas e escritas de utilizadores, sempre com prepared statements. */
class UsuarioRepository
{
    public function findByEmail(string $email): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT u.*, i.estado AS igreja_estado, i.nome AS igreja_nome '
            . 'FROM usuarios u LEFT JOIN igrejas i ON i.id = u.igreja_id '
            . 'WHERE LOWER(u.email) = :email LIMIT 1'
        );
        $statement->execute([':email' => strtolower(trim($email))]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($user) ? $user : null;
    }

    public function findForAuthentication(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT u.id, u.igreja_id, u.nome_completo, u.email, u.senha_hash, u.perfil, u.papel, '
            . 'u.modulos_permitidos, u.ativo, u.deve_trocar_senha, u.email_verificado_em, '
            . 'u.versao_sessao, u.ultimo_login, i.estado AS igreja_estado, i.nome AS igreja_nome '
            . 'FROM usuarios u LEFT JOIN igrejas i ON i.id = u.igreja_id '
            . 'WHERE u.id = :id LIMIT 1'
        );
        $statement->execute([':id' => $userId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($user) ? $user : null;
    }

    public function encontrarPerfilNaIgreja(int $churchId, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, igreja_id, nome_completo, email, perfil, papel, ativo, ultimo_login, criado_em '
            . 'FROM usuarios WHERE id = :id AND igreja_id = :igreja_id AND papel <> \'superadmin\' LIMIT 1'
        );
        $statement->execute([':id' => $userId, ':igreja_id' => $churchId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($user) ? $user : null;
    }

    public function actualizarNomeProprio(int $churchId, int $userId, string $name): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE usuarios SET nome_completo = :nome '
            . 'WHERE id = :id AND igreja_id = :igreja_id AND papel <> \'superadmin\' AND ativo = 1'
        );
        $statement->execute([':nome' => $name, ':id' => $userId, ':igreja_id' => $churchId]);
        if ($statement->rowCount() === 1) {
            return true;
        }
        $check = Database::connection()->prepare(
            'SELECT id FROM usuarios WHERE id = :id AND igreja_id = :igreja_id '
            . 'AND nome_completo = :nome AND ativo = 1 LIMIT 1'
        );
        $check->execute([':id' => $userId, ':igreja_id' => $churchId, ':nome' => $name]);
        return $check->fetchColumn() !== false;
    }

    public function findForPasswordChange(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT u.id, u.igreja_id, u.nome_completo, u.email, u.senha_hash, u.papel, '
            . 'u.ativo, u.deve_trocar_senha, i.estado AS igreja_estado '
            . 'FROM usuarios u LEFT JOIN igrejas i ON i.id = u.igreja_id '
            . 'WHERE u.id = :id LIMIT 1'
        );
        $statement->execute([':id' => $userId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($user) ? $user : null;
    }

    public function updatePasswordHash(int $userId, string $hash, bool $mustChange = false): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE usuarios SET senha_hash = :senha_hash, deve_trocar_senha = :deve_trocar_senha, '
            . 'versao_sessao = versao_sessao + 1 WHERE id = :id'
        );
        $statement->execute([
            ':senha_hash' => $hash,
            ':deve_trocar_senha' => $mustChange ? 1 : 0,
            ':id' => $userId,
        ]);
    }

    public function updatePasswordHashInTransaction(PDO $pdo, int $userId, string $hash): bool
    {
        $statement = $pdo->prepare(
            'UPDATE usuarios SET senha_hash = :senha_hash, deve_trocar_senha = 0, '
            . 'email_verificado_em = COALESCE(email_verificado_em, NOW()), '
            . 'versao_sessao = versao_sessao + 1 WHERE id = :id AND ativo = 1'
        );
        $statement->execute([':senha_hash' => $hash, ':id' => $userId]);
        return $statement->rowCount() === 1;
    }

    public function updateLogin(int $userId, string $hash = ''): void
    {
        if ($hash !== '') {
            $statement = Database::connection()->prepare(
                'UPDATE usuarios SET ultimo_login = NOW(), senha_hash = :senha_hash WHERE id = :id'
            );
            $statement->execute([':senha_hash' => $hash, ':id' => $userId]);
            return;
        }
        $statement = Database::connection()->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id');
        $statement->execute([':id' => $userId]);
    }

    /** Lista apenas utilizadores da igreja autenticada, com permissões agregadas. */
    public function listarDaIgreja(int $churchId, string $role = '', string $status = ''): array
    {
        $conditions = ['u.igreja_id = :igreja_id', "u.papel NOT IN ('superadmin', 'admin_igreja')"];
        $parameters = [':igreja_id' => $churchId];
        if ($role !== '' && in_array($role, ['admin_igreja', 'pastor', 'tesoureiro', 'secretario', 'lider'], true)) {
            $conditions[] = 'u.papel = :papel';
            $parameters[':papel'] = $role;
        }
        if ($status === 'activo') {
            $conditions[] = 'u.ativo = 1';
        } elseif ($status === 'inactivo') {
            $conditions[] = 'u.ativo = 0';
        }
        $statement = Database::connection()->prepare(
            'SELECT u.id, u.igreja_id, u.nome_completo, u.email, u.perfil, u.papel, u.ativo, '
            . 'u.email_verificado_em, u.ultimo_login, u.criado_em, '
            . 'GROUP_CONCAT(CASE WHEN p.permitido = 1 THEN p.modulo END ORDER BY p.modulo SEPARATOR \',\') AS permissoes '
            . 'FROM usuarios u LEFT JOIN permissoes_modulo p '
            . 'ON p.usuario_id = u.id AND p.igreja_id = u.igreja_id '
            . 'WHERE ' . implode(' AND ', $conditions) . ' '
            . 'GROUP BY u.id, u.igreja_id, u.nome_completo, u.email, u.perfil, u.papel, u.ativo, '
            . 'u.email_verificado_em, u.ultimo_login, u.criado_em ORDER BY u.criado_em DESC, u.id DESC'
        );
        $statement->execute($parameters);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lookup anti-IDOR: id e igreja são verificados na mesma consulta. */
    public function encontrarNaIgreja(int $churchId, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, igreja_id, nome_completo, email, perfil, papel, ativo, email_verificado_em '
            . 'FROM usuarios WHERE id = :id AND igreja_id = :igreja_id AND papel <> \'superadmin\' LIMIT 1'
        );
        $statement->execute([':id' => $userId, ':igreja_id' => $churchId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($user) ? $user : null;
    }

    public function emailExiste(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM usuarios WHERE LOWER(email) = :email';
        $parameters = [':email' => strtolower(trim($email))];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters[':except_id'] = $exceptId;
        }
        $statement = Database::connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);
        return $statement->fetchColumn() !== false;
    }

    public function criarNaIgreja(PDO $pdo, int $churchId, array $user): int
    {
        $statement = $pdo->prepare(
            'INSERT INTO usuarios (igreja_id, nome_completo, email, senha_hash, perfil, papel, '
            . 'modulos_permitidos, ativo, deve_trocar_senha, email_verificado_em, versao_sessao) '
            . 'VALUES (:igreja_id, :nome, :email, :senha_hash, :perfil, :papel, :modulos, 1, 1, NULL, 1)'
        );
        $statement->execute([
            ':igreja_id' => $churchId,
            ':nome' => $user['nome_completo'],
            ':email' => $user['email'],
            ':senha_hash' => $user['senha_hash'],
            ':perfil' => $user['papel'] === 'admin_igreja' ? 'admin' : 'gestor',
            ':papel' => $user['papel'],
            ':modulos' => implode(',', $user['modulos']),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function actualizarNaIgreja(PDO $pdo, int $churchId, int $userId, array $user): bool
    {
        $statement = $pdo->prepare(
            'UPDATE usuarios SET nome_completo = :nome, perfil = :perfil, papel = :papel, '
            . 'modulos_permitidos = :modulos, versao_sessao = versao_sessao + 1 '
            . 'WHERE id = :id AND igreja_id = :igreja_id AND papel <> \'superadmin\''
        );
        $statement->execute([
            ':nome' => $user['nome_completo'],
            ':perfil' => $user['papel'] === 'admin_igreja' ? 'admin' : 'gestor',
            ':papel' => $user['papel'],
            ':modulos' => implode(',', $user['modulos']),
            ':id' => $userId,
            ':igreja_id' => $churchId,
        ]);
        return $statement->rowCount() === 1;
    }

    public function definirEstado(int $churchId, int $userId, bool $active): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE usuarios SET ativo = :activo, versao_sessao = versao_sessao + 1 '
            . 'WHERE id = :id AND igreja_id = :igreja_id AND papel <> \'superadmin\''
        );
        $statement->execute([':activo' => $active ? 1 : 0, ':id' => $userId, ':igreja_id' => $churchId]);
        return $statement->rowCount() === 1;
    }

    public function contarAdministradoresActivos(int $churchId, ?int $exceptId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM usuarios WHERE igreja_id = :igreja_id AND papel = \'admin_igreja\' AND ativo = 1';
        $parameters = [':igreja_id' => $churchId];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters[':except_id'] = $exceptId;
        }
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    public function createChurchAdmin(PDO $pdo, array $user): int
    {
        $statement = $pdo->prepare(
            'INSERT INTO usuarios '
            . '(igreja_id, nome_completo, email, senha_hash, perfil, papel, modulos_permitidos, ativo, '
            . 'deve_trocar_senha, email_verificado_em, versao_sessao) '
            . "VALUES (:igreja_id, :nome, :email, :senha_hash, 'admin', 'admin_igreja', 'admin', 0, 1, NULL, 1)"
        );
        $statement->execute([
            ':igreja_id' => (int) $user['igreja_id'],
            ':nome' => (string) $user['nome_completo'],
            ':email' => (string) $user['email'],
            ':senha_hash' => (string) $user['senha_hash'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function createSuperadmin(PDO $pdo, string $name, string $email, string $passwordHash): int
    {
        $statement = $pdo->prepare(
            'INSERT INTO usuarios '
            . '(igreja_id, nome_completo, email, senha_hash, perfil, papel, modulos_permitidos, ativo, '
            . 'deve_trocar_senha, email_verificado_em, versao_sessao) '
            . "VALUES (NULL, :nome, :email, :senha_hash, 'admin', 'superadmin', NULL, 1, 0, NOW(), 1)"
        );
        $statement->execute([
            ':nome' => $name,
            ':email' => strtolower(trim($email)),
            ':senha_hash' => $passwordHash,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function updateActivation(PDO $pdo, int $userId, string $passwordHash): ?int
    {
        $find = $pdo->prepare('SELECT igreja_id FROM usuarios WHERE id = :id LIMIT 1 FOR UPDATE');
        $find->execute([':id' => $userId]);
        $churchId = $find->fetchColumn();
        if ($churchId === false) {
            return null;
        }

        $statement = $pdo->prepare(
            'UPDATE usuarios SET senha_hash = :senha_hash, ativo = 1, email_verificado_em = NOW(), '
            . 'deve_trocar_senha = 0, versao_sessao = versao_sessao + 1 '
            . 'WHERE id = :id AND ativo = 0 AND email_verificado_em IS NULL'
        );
        $statement->execute([':senha_hash' => $passwordHash, ':id' => $userId]);
        if ($statement->rowCount() !== 1) {
            return null;
        }
        return $churchId === null ? null : (int) $churchId;
    }

    public function activateChurchIfPending(PDO $pdo, int $churchId): bool
    {
        $statement = $pdo->prepare(
            "UPDATE igrejas SET estado = 'activa' WHERE id = :id AND estado = 'pendente'"
        );
        $statement->execute([':id' => $churchId]);
        return $statement->rowCount() === 1;
    }

    public function bootstrapStatus(PDO $pdo): ?array
    {
        $statement = $pdo->query('SELECT id, superadmin_criado_em FROM platform_bootstrap WHERE id = 1 LIMIT 1');
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function lockBootstrapRow(PDO $pdo): ?array
    {
        $statement = $pdo->query('SELECT id, superadmin_criado_em FROM platform_bootstrap WHERE id = 1 FOR UPDATE');
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function completeBootstrap(PDO $pdo): void
    {
        $statement = $pdo->prepare(
            'UPDATE platform_bootstrap SET superadmin_criado_em = NOW() WHERE id = 1 AND superadmin_criado_em IS NULL'
        );
        $statement->execute();
    }

    public function incrementSessionVersion(PDO $pdo, int $userId): void
    {
        $statement = $pdo->prepare('UPDATE usuarios SET versao_sessao = versao_sessao + 1 WHERE id = :id');
        $statement->execute([':id' => $userId]);
    }

    public function incrementSessionVersionsForChurch(PDO $pdo, int $churchId): void
    {
        $statement = $pdo->prepare(
            'UPDATE usuarios SET versao_sessao = versao_sessao + 1 WHERE igreja_id = :igreja_id'
        );
        $statement->execute([':igreja_id' => $churchId]);
    }

    public function getChurchAdmin(int $churchId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT id, igreja_id, nome_completo, email, ativo, email_verificado_em, papel "
            . "FROM usuarios WHERE igreja_id = :igreja_id AND papel = 'admin_igreja' "
            . 'ORDER BY id ASC LIMIT 1'
        );
        $statement->execute([':igreja_id' => $churchId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($user) ? $user : null;
    }
}
