<?php
namespace App\Repositories;

use PDO;
use App\Core\Database;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Acesso à fila persistente; tokens ou URLs nunca são colocados em claro. */
class EmailQueueRepository
{
    public function create(
        ?int $churchId,
        ?int $userId,
        string $recipient,
        string $subject,
        string $html,
        string $text,
        string $template,
        ?string $encryptedData,
        ?PDO $connection = null
    ): int {
        $pdo = $connection ?: Database::connection();
        $statement = $pdo->prepare(
            'INSERT INTO email_fila '
            . '(igreja_id, usuario_id, destinatario, assunto, corpo_html, corpo_texto, template, dados_cifrados, estado, tentativas, agendado_para) '
            . 'VALUES (:igreja_id, :usuario_id, :destinatario, :assunto, :corpo_html, :corpo_texto, :template, :dados_cifrados, \'pendente\', 0, NOW())'
        );
        $statement->execute([
            ':igreja_id' => $churchId,
            ':usuario_id' => $userId,
            ':destinatario' => $recipient,
            ':assunto' => $subject,
            ':corpo_html' => $html,
            ':corpo_texto' => $text,
            ':template' => $template,
            ':dados_cifrados' => $encryptedData,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM email_fila WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function latest(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $statement = Database::connection()->query(
            'SELECT id, igreja_id, usuario_id, destinatario, assunto, template, estado, tentativas, erro, enviado_em, criado_em '
            . 'FROM email_fila ORDER BY criado_em DESC, id DESC LIMIT ' . $limit
        );
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function latestForChurch(int $churchId, int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $statement = Database::connection()->prepare(
            'SELECT id, igreja_id, usuario_id, destinatario, assunto, template, estado, tentativas, erro, enviado_em, criado_em '
            . 'FROM email_fila WHERE igreja_id = :igreja_id ORDER BY criado_em DESC, id DESC LIMIT ' . $limit
        );
        $statement->execute([':igreja_id' => $churchId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isReady(int $id): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT id FROM email_fila WHERE id = :id AND agendado_para <= UTC_TIMESTAMP() LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    public function markSent(int $id): void
    {
        $statement = Database::connection()->prepare(
            "UPDATE email_fila SET estado = 'enviado', tentativas = tentativas + 1, erro = NULL, enviado_em = NOW() WHERE id = :id"
        );
        $statement->execute([':id' => $id]);
    }

    public function markFailed(int $id, string $error): void
    {
        $statement = Database::connection()->prepare(
            "UPDATE email_fila SET estado = 'falhou', tentativas = tentativas + 1, erro = :erro WHERE id = :id"
        );
        $statement->execute([
            ':erro' => substr($error, 0, 2000),
            ':id' => $id,
        ]);
    }

    public function scheduleRetry(int $id): void
    {
        $statement = Database::connection()->prepare(
            "UPDATE email_fila SET estado = 'pendente', erro = NULL, enviado_em = NULL, agendado_para = NOW() WHERE id = :id"
        );
        $statement->execute([':id' => $id]);
    }

    public function renewPayload(int $id, string $html, string $text, string $encryptedData, ?PDO $connection = null): void
    {
        $pdo = $connection ?: Database::connection();
        $statement = $pdo->prepare(
            "UPDATE email_fila SET corpo_html = :html, corpo_texto = :text, dados_cifrados = :dados, estado = 'pendente', erro = NULL, enviado_em = NULL, agendado_para = NOW() WHERE id = :id"
        );
        $statement->execute([
            ':html' => $html,
            ':text' => $text,
            ':dados' => $encryptedData,
            ':id' => $id,
        ]);
    }

    /** Apaga payloads cifrados quando os tokens associados já foram consumidos. */
    public function wipeSecretsForUser(int $userId, array $templates): void
    {
        if ($templates === []) {
            return;
        }
        $placeholders = [];
        $parameters = [':usuario_id' => $userId];
        foreach (array_values($templates) as $index => $template) {
            $name = ':template_' . $index;
            $placeholders[] = $name;
            $parameters[$name] = (string) $template;
        }
        $statement = Database::connection()->prepare(
            'UPDATE email_fila SET dados_cifrados = NULL '
            . 'WHERE usuario_id = :usuario_id AND template IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($parameters);
    }
}
