<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Gravação centralizada de logs da aplicação. */
class AuditRepository
{
    /** Consulta apenas eventos que já têm o tenant da igreja da sessão. */
    public function listarDaIgreja(int $churchId, array $filters, int $limit = 500): array
    {
        $where = ['l.igreja_id = :igreja_id'];
        $parameters = [':igreja_id' => $churchId];
        if ((int) ($filters['usuario_id'] ?? 0) > 0) {
            $where[] = 'l.usuario_id = :usuario_id';
            $parameters[':usuario_id'] = (int) $filters['usuario_id'];
        }
        foreach (['modulo', 'acao'] as $field) {
            if (is_string($filters[$field] ?? null) && $filters[$field] !== '') {
                $where[] = 'l.' . $field . ' = :' . $field;
                $parameters[':' . $field] = $filters[$field];
            }
        }
        if (is_string($filters['inicio'] ?? null) && $filters['inicio'] !== '') {
            $where[] = 'l.criado_em >= :inicio';
            $parameters[':inicio'] = $filters['inicio'] . ' 00:00:00';
        }
        if (is_string($filters['fim_exclusivo'] ?? null) && $filters['fim_exclusivo'] !== '') {
            $where[] = 'l.criado_em < :fim_exclusivo';
            $parameters[':fim_exclusivo'] = $filters['fim_exclusivo'] . ' 00:00:00';
        }
        $limit = max(1, min(500, $limit));
        $statement = Database::connection()->prepare(
            'SELECT l.id, l.igreja_id, l.usuario_id, l.usuario_nome, l.acao, l.modulo, '
            . 'l.descricao, l.ip, l.criado_em FROM logs_auditoria l WHERE '
            . implode(' AND ', $where) . ' ORDER BY l.criado_em DESC, l.id DESC LIMIT ' . $limit
        );
        $statement->execute($parameters);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function utilizadoresDaIgreja(int $churchId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, nome_completo FROM usuarios WHERE igreja_id = :igreja_id '
            . "AND papel <> 'superadmin' ORDER BY nome_completo, id"
        );
        $statement->execute([':igreja_id' => $churchId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function modulosDaIgreja(int $churchId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT DISTINCT modulo FROM logs_auditoria WHERE igreja_id = :igreja_id ORDER BY modulo'
        );
        $statement->execute([':igreja_id' => $churchId]);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function acoesDaIgreja(int $churchId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT DISTINCT acao FROM logs_auditoria WHERE igreja_id = :igreja_id ORDER BY acao'
        );
        $statement->execute([':igreja_id' => $churchId]);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function totalDaIgreja(int $churchId): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM logs_auditoria WHERE igreja_id = :igreja_id');
        $statement->execute([':igreja_id' => $churchId]);
        return (int) $statement->fetchColumn();
    }

    public function ultimoLoginDaIgreja(int $churchId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT id, usuario_nome, criado_em FROM logs_auditoria "
            . "WHERE igreja_id = :igreja_id AND acao = 'login' ORDER BY criado_em DESC, id DESC LIMIT 1"
        );
        $statement->execute([':igreja_id' => $churchId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function insert(array $event, ?PDO $connection = null): void
    {
        $pdo = $connection ?: Database::connection();
        $statement = $pdo->prepare(
            'INSERT INTO logs_auditoria '
            . '(igreja_id, usuario_id, usuario_nome, acao, modulo, descricao, dados_anteriores, dados_novos, ip, user_agent) '
            . 'VALUES (:igreja_id, :usuario_id, :usuario_nome, :acao, :modulo, :descricao, :antes, :depois, :ip, :user_agent)'
        );
        $statement->execute([
            ':igreja_id' => $event['igreja_id'] ?? null,
            ':usuario_id' => $event['usuario_id'] ?? null,
            ':usuario_nome' => substr((string) ($event['usuario_nome'] ?? 'Sistema'), 0, 180),
            ':acao' => substr((string) ($event['acao'] ?? 'evento'), 0, 80),
            ':modulo' => substr((string) ($event['modulo'] ?? 'sistema'), 0, 80),
            ':descricao' => (string) ($event['descricao'] ?? ''),
            ':antes' => $this->encode($event['antes'] ?? null),
            ':depois' => $this->encode($event['depois'] ?? null),
            ':ip' => substr((string) ($event['ip'] ?? ''), 0, 45),
            ':user_agent' => substr((string) ($event['user_agent'] ?? ''), 0, 255),
        ]);
    }

    private function encode($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : null;
    }
}
