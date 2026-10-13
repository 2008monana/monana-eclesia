<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Tokens de uso único: persistem apenas o SHA-256, nunca a chave original. */
class TokenRepository
{
    public function invalidatePasswordTokens(PDO $pdo, int $userId): void
    {
        $statement = $pdo->prepare(
            'UPDATE tokens_senha SET usado_em = COALESCE(usado_em, NOW()) WHERE usuario_id = :id AND usado_em IS NULL'
        );
        $statement->execute([':id' => $userId]);
    }

    public function createPasswordToken(PDO $pdo, int $userId, string $hash, string $expiresAt, string $ip): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO tokens_senha (usuario_id, token_hash, expira_em, ip) '
            . 'VALUES (:usuario_id, :token_hash, :expira_em, :ip)'
        );
        $statement->execute([
            ':usuario_id' => $userId,
            ':token_hash' => $hash,
            ':expira_em' => $expiresAt,
            ':ip' => substr($ip, 0, 45),
        ]);
    }

    public function findPasswordToken(string $hash): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT t.usuario_id, t.expira_em, t.usado_em, u.nome_completo, u.email, u.igreja_id, u.ativo '
            . 'FROM tokens_senha t INNER JOIN usuarios u ON u.id = t.usuario_id '
            . 'LEFT JOIN igrejas i ON i.id = u.igreja_id '
            . "WHERE t.token_hash = :hash AND t.usado_em IS NULL AND t.expira_em > NOW() AND u.ativo = 1 "
            . "AND ((u.papel = 'superadmin' AND u.igreja_id IS NULL) OR (u.igreja_id IS NOT NULL AND i.estado = 'activa')) LIMIT 1"
        );
        $statement->execute([':hash' => $hash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function findPasswordTokenInTransaction(PDO $pdo, string $hash): ?array
    {
        $statement = $pdo->prepare(
            'SELECT t.usuario_id, u.ativo FROM tokens_senha t '
            . 'INNER JOIN usuarios u ON u.id = t.usuario_id '
            . 'LEFT JOIN igrejas i ON i.id = u.igreja_id '
            . "WHERE t.token_hash = :hash AND t.usado_em IS NULL AND t.expira_em > NOW() AND u.ativo = 1 "
            . "AND ((u.papel = 'superadmin' AND u.igreja_id IS NULL) OR (u.igreja_id IS NOT NULL AND i.estado = 'activa')) LIMIT 1 FOR UPDATE"
        );
        $statement->execute([':hash' => $hash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function consumePasswordToken(PDO $pdo, string $hash): ?int
    {
        $statement = $pdo->prepare(
            'SELECT usuario_id FROM tokens_senha WHERE token_hash = :hash '
            . 'AND usado_em IS NULL AND expira_em > NOW() LIMIT 1 FOR UPDATE'
        );
        $statement->execute([':hash' => $hash]);
        $userId = $statement->fetchColumn();
        if ($userId === false) {
            return null;
        }
        $consume = $pdo->prepare(
            'UPDATE tokens_senha SET usado_em = NOW() WHERE token_hash = :hash AND usado_em IS NULL AND expira_em > NOW()'
        );
        $consume->execute([':hash' => $hash]);
        return $consume->rowCount() === 1 ? (int) $userId : null;
    }

    public function invalidateActivationTokens(PDO $pdo, int $userId): void
    {
        $statement = $pdo->prepare(
            'UPDATE tokens_ativacao SET usado_em = COALESCE(usado_em, NOW()) WHERE usuario_id = :id AND usado_em IS NULL'
        );
        $statement->execute([':id' => $userId]);
    }

    public function createActivationToken(PDO $pdo, int $userId, string $hash, string $expiresAt): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO tokens_ativacao (usuario_id, token_hash, expira_em) '
            . 'VALUES (:usuario_id, :token_hash, :expira_em)'
        );
        $statement->execute([
            ':usuario_id' => $userId,
            ':token_hash' => $hash,
            ':expira_em' => $expiresAt,
        ]);
    }

    public function findActivationToken(string $hash): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT t.usuario_id, t.expira_em, u.nome_completo, u.email, u.igreja_id, u.ativo, u.email_verificado_em, i.nome AS igreja_nome, i.estado AS igreja_estado '
            . 'FROM tokens_ativacao t INNER JOIN usuarios u ON u.id = t.usuario_id '
            . 'INNER JOIN igrejas i ON i.id = u.igreja_id '
            . "WHERE t.token_hash = :hash AND t.usado_em IS NULL AND t.expira_em > NOW() AND i.estado = 'pendente' LIMIT 1"
        );
        $statement->execute([':hash' => $hash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function findActivationTokenInTransaction(PDO $pdo, string $hash): ?array
    {
        $statement = $pdo->prepare(
            'SELECT t.usuario_id, u.ativo, u.igreja_id, u.email_verificado_em, i.estado AS igreja_estado FROM tokens_ativacao t '
            . 'INNER JOIN usuarios u ON u.id = t.usuario_id '
            . 'INNER JOIN igrejas i ON i.id = u.igreja_id '
            . "WHERE t.token_hash = :hash AND t.usado_em IS NULL AND t.expira_em > NOW() AND i.estado = 'pendente' LIMIT 1 FOR UPDATE"
        );
        $statement->execute([':hash' => $hash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function consumeActivationToken(PDO $pdo, string $hash): ?int
    {
        $statement = $pdo->prepare(
            'SELECT usuario_id FROM tokens_ativacao WHERE token_hash = :hash '
            . 'AND usado_em IS NULL AND expira_em > NOW() LIMIT 1 FOR UPDATE'
        );
        $statement->execute([':hash' => $hash]);
        $userId = $statement->fetchColumn();
        if ($userId === false) {
            return null;
        }
        $consume = $pdo->prepare(
            'UPDATE tokens_ativacao SET usado_em = NOW() WHERE token_hash = :hash AND usado_em IS NULL AND expira_em > NOW()'
        );
        $consume->execute([':hash' => $hash]);
        return $consume->rowCount() === 1 ? (int) $userId : null;
    }
}
