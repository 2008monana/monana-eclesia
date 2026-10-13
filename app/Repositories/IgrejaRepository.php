<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Repositório de igrejas; consultas globais ficam reservadas ao Superadmin. */
class IgrejaRepository
{
    public function create(PDO $pdo, array $church): int
    {
        $statement = $pdo->prepare(
            "INSERT INTO igrejas (plano_id, nome, slug, email_admin, telefone, cidade, estado) "
            . "VALUES (:plano_id, :nome, :slug, :email_admin, :telefone, :cidade, 'pendente')"
        );
        $statement->execute([
            ':plano_id' => (int) $church['plano_id'],
            ':nome' => (string) $church['nome'],
            ':slug' => (string) $church['slug'],
            ':email_admin' => strtolower(trim((string) $church['email_admin'])),
            ':telefone' => $church['telefone'] !== '' ? (string) $church['telefone'] : null,
            ':cidade' => $church['cidade'] !== '' ? (string) $church['cidade'] : null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function slugExists(PDO $pdo, string $slug): bool
    {
        $statement = $pdo->prepare('SELECT id FROM igrejas WHERE slug = :slug LIMIT 1');
        $statement->execute([':slug' => $slug]);
        return $statement->fetchColumn() !== false;
    }

    public function createConfiguration(PDO $pdo, int $churchId, string $churchName): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO configuracoes_igreja '
            . '(igreja_id, nome_exibicao, subtitulo, rodape, versiculo, logo_path, valor_cota, periodicidade_cota, '
            . 'inicio_cotas, inicio_contribuicoes, moeda, dias_expiracao_sessao) '
            . "VALUES (:igreja_id, :nome, NULL, NULL, NULL, NULL, 0.00, 'mensal', NULL, NULL, 'AOA', 30)"
        );
        $statement->execute([':igreja_id' => $churchId, ':nome' => $churchName]);
    }

    public function all(): array
    {
        $sql = 'SELECT i.id, i.nome, i.slug, i.email_admin, i.telefone, i.cidade, i.estado, i.criada_em, '
            . 'i.suspensa_em, i.encerrada_em, i.motivo_suspensao, p.nome AS plano_nome, '
            . '(SELECT COUNT(*) FROM usuarios u WHERE u.igreja_id = i.id) AS total_utilizadores, '
            . '(SELECT f.estado FROM email_fila f WHERE f.igreja_id = i.id ORDER BY f.id DESC LIMIT 1) AS email_estado '
            . 'FROM igrejas i INNER JOIN planos p ON p.id = i.plano_id '
            . 'ORDER BY i.criada_em DESC, i.id DESC';
        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT i.*, p.nome AS plano_nome FROM igrejas i '
            . 'INNER JOIN planos p ON p.id = i.plano_id WHERE i.id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function update(int $id, array $values): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE igrejas SET nome = :nome, plano_id = :plano_id, telefone = :telefone, cidade = :cidade '
            . "WHERE id = :id AND estado <> 'encerrada'"
        );
        $statement->execute([
            ':nome' => (string) $values['nome'],
            ':plano_id' => (int) $values['plano_id'],
            ':telefone' => $values['telefone'] !== '' ? (string) $values['telefone'] : null,
            ':cidade' => $values['cidade'] !== '' ? (string) $values['cidade'] : null,
            ':id' => $id,
        ]);
        if ($statement->rowCount() > 0) {
            return true;
        }
        $check = Database::connection()->prepare('SELECT estado FROM igrejas WHERE id = :id LIMIT 1');
        $check->execute([':id' => $id]);
        $state = $check->fetchColumn();
        return $state !== false && (string) $state !== 'encerrada';
    }

    public function setStatus(int $id, string $status, ?string $reason = null): bool
    {
        if ($status === 'suspensa') {
            $statement = Database::connection()->prepare(
                "UPDATE igrejas SET estado = 'suspensa', suspensa_em = NOW(), motivo_suspensao = :motivo WHERE id = :id AND estado = 'activa'"
            );
            $statement->execute([':motivo' => $reason, ':id' => $id]);
        } elseif ($status === 'activa') {
            $statement = Database::connection()->prepare(
                "UPDATE igrejas SET estado = 'activa', suspensa_em = NULL, motivo_suspensao = NULL WHERE id = :id AND estado = 'suspensa'"
            );
            $statement->execute([':id' => $id]);
        } elseif ($status === 'encerrada') {
            $statement = Database::connection()->prepare(
                "UPDATE igrejas SET estado = 'encerrada', encerrada_em = NOW(), motivo_suspensao = :motivo WHERE id = :id AND estado <> 'encerrada'"
            );
            $statement->execute([':motivo' => $reason, ':id' => $id]);
        } else {
            throw new \InvalidArgumentException('Estado de igreja não permitido.');
        }
        return $statement->rowCount() > 0;
    }

    public function setConfigurationDisplayName(int $churchId, string $name): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE configuracoes_igreja SET nome_exibicao = :nome WHERE igreja_id = :igreja_id'
        );
        $statement->execute([':nome' => $name, ':igreja_id' => $churchId]);
    }

    public function counts(): array
    {
        $statement = Database::connection()->query(
            "SELECT COUNT(*) AS total, SUM(estado = 'activa') AS activas, "
            . "SUM(estado = 'pendente') AS pendentes, SUM(estado = 'suspensa') AS suspensas, "
            . "SUM(estado = 'encerrada') AS encerradas FROM igrejas"
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : ['total' => 0, 'activas' => 0, 'pendentes' => 0, 'suspensas' => 0, 'encerradas' => 0];
    }
}
