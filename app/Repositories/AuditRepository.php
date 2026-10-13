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
