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
