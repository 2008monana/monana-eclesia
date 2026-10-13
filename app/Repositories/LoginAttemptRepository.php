<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Regista tentativas para limitação simultânea por email e endereço IP. */
class LoginAttemptRepository
{
    public function failuresForEmail(string $email, int $minutes): int
    {
        return $this->count('email', strtolower(trim($email)), $minutes);
    }

    public function failuresForIp(string $ip, int $minutes): int
    {
        return $this->count('ip', $ip, $minutes);
    }

    public function record(string $email, string $ip, bool $success): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO tentativas_login (email, ip, sucesso, tentada_em) VALUES (:email, :ip, :sucesso, NOW())'
        );
        $statement->execute([
            ':email' => substr(strtolower(trim($email)), 0, 191),
            ':ip' => substr($ip !== '' ? $ip : 'desconhecido', 0, 45),
            ':sucesso' => $success ? 1 : 0,
        ]);
    }

    /** Apaga eventos velhos para limitar crescimento e retenção de dados pessoais. */
    public function prune(int $days = 90): void
    {
        $days = max(1, min(365, $days));
        $statement = Database::connection()->prepare(
            'DELETE FROM tentativas_login WHERE tentada_em < :limite'
        );
        $statement->execute([':limite' => gmdate('Y-m-d H:i:s', time() - ($days * 86400))]);
    }

    private function count(string $column, string $value, int $minutes): int
    {
        if (!in_array($column, ['email', 'ip'], true)) {
            throw new \InvalidArgumentException('Chave de rate-limit inválida.');
        }
        $minutes = max(1, min(1440, $minutes));
        $since = gmdate('Y-m-d H:i:s', time() - ($minutes * 60));
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM tentativas_login WHERE `' . $column . '` = :valor '
            . 'AND sucesso = 0 AND tentada_em >= :desde'
        );
        $statement->execute([':valor' => $value, ':desde' => $since]);
        return (int) $statement->fetchColumn();
    }
}
