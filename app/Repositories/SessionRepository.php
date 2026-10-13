<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Registo server-side das sessões, com hash do ID e revogação centralizada. */
class SessionRepository
{
    public function create(int $userId, ?int $churchId, int $version, string $sessionHash, string $ip, string $userAgent, int $lifetime): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO sessoes (id, usuario_id, igreja_id, versao_sessao, ip, user_agent, ultima_actividade, expira_em) '
            . 'VALUES (:id, :usuario_id, :igreja_id, :versao, :ip, :user_agent, NOW(), :expira_em)'
        );
        $statement->execute([
            ':id' => $sessionHash,
            ':usuario_id' => $userId,
            ':igreja_id' => $churchId,
            ':versao' => $version,
            ':ip' => substr($ip, 0, 45),
            ':user_agent' => substr($userAgent, 0, 500),
            ':expira_em' => gmdate('Y-m-d H:i:s', time() + max(300, $lifetime)),
        ]);
    }

    public function isActive(string $sessionHash, int $userId, int $version): bool
    {
        $pdo = Database::connection();
        $check = $pdo->prepare(
            'SELECT id FROM sessoes WHERE id = :id AND usuario_id = :usuario_id '
            . 'AND versao_sessao = :versao AND revogada_em IS NULL AND expira_em > NOW() LIMIT 1'
        );
        $check->execute([
            ':id' => $sessionHash,
            ':usuario_id' => $userId,
            ':versao' => $version,
        ]);
        if ($check->fetchColumn() === false) {
            return false;
        }

        $update = $pdo->prepare(
            'UPDATE sessoes SET ultima_actividade = NOW(), expira_em = :expira_em '
            . 'WHERE id = :id AND usuario_id = :usuario_id AND versao_sessao = :versao '
            . 'AND revogada_em IS NULL AND expira_em > NOW()'
        );
        $update->execute([
            ':expira_em' => gmdate('Y-m-d H:i:s', time() + \App\Core\Session::lifetime()),
            ':id' => $sessionHash,
            ':usuario_id' => $userId,
            ':versao' => $version,
        ]);
        if ($update->rowCount() === 1) {
            return true;
        }
        $confirm = $pdo->prepare(
            'SELECT id FROM sessoes WHERE id = :id AND usuario_id = :usuario_id '
            . 'AND versao_sessao = :versao AND revogada_em IS NULL AND expira_em > NOW() LIMIT 1'
        );
        $confirm->execute([':id' => $sessionHash, ':usuario_id' => $userId, ':versao' => $version]);
        return $confirm->fetchColumn() !== false;
    }

    public function revokeCurrent(int $userId, string $sessionHash): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE sessoes SET revogada_em = NOW() WHERE id = :id AND usuario_id = :usuario_id AND revogada_em IS NULL'
        );
        $statement->execute([':id' => $sessionHash, ':usuario_id' => $userId]);
    }

    public function revokeAll(int $userId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE sessoes SET revogada_em = NOW() WHERE usuario_id = :usuario_id AND revogada_em IS NULL'
        );
        $statement->execute([':usuario_id' => $userId]);
    }

    public function revokeAllInTransaction(PDO $pdo, int $userId): void
    {
        $statement = $pdo->prepare(
            'UPDATE sessoes SET revogada_em = NOW() WHERE usuario_id = :usuario_id AND revogada_em IS NULL'
        );
        $statement->execute([':usuario_id' => $userId]);
    }

    public function revokeAllForChurchInTransaction(PDO $pdo, int $churchId): void
    {
        $statement = $pdo->prepare(
            'UPDATE sessoes SET revogada_em = NOW() WHERE igreja_id = :igreja_id AND revogada_em IS NULL'
        );
        $statement->execute([':igreja_id' => $churchId]);
    }

    public function pruneExpired(): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM sessoes WHERE expira_em < :limite OR revogada_em < :limite'
        );
        $statement->execute([':limite' => gmdate('Y-m-d H:i:s', time() - 86400)]);
    }
}
