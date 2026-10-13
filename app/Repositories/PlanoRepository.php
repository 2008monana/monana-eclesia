<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Catálogo de planos da plataforma, configurável pelo Superadmin. */
class PlanoRepository
{
    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT p.*, (SELECT COUNT(*) FROM igrejas i WHERE i.plano_id = p.id) AS igrejas_associadas '
            . 'FROM planos p';
        if ($activeOnly) {
            $sql .= ' WHERE p.ativo = 1';
        }
        $sql .= ' ORDER BY p.ativo DESC, p.nome ASC';
        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT p.*, (SELECT COUNT(*) FROM igrejas i WHERE i.plano_id = p.id) AS igrejas_associadas '
            . 'FROM planos p WHERE p.id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function activeExists(int $id): bool
    {
        $statement = Database::connection()->prepare('SELECT id FROM planos WHERE id = :id AND ativo = 1 LIMIT 1');
        $statement->execute([':id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    public function activeExistsInTransaction(\PDO $pdo, int $id): bool
    {
        $statement = $pdo->prepare('SELECT id FROM planos WHERE id = :id AND ativo = 1 LIMIT 1 FOR UPDATE');
        $statement->execute([':id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    public function create(array $values): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO planos (nome, limite_membros, limite_utilizadores, preco_kz, ativo) '
            . 'VALUES (:nome, :membros, :utilizadores, :preco, :ativo)'
        );
        $statement->execute([
            ':nome' => $values['nome'],
            ':membros' => (int) $values['limite_membros'],
            ':utilizadores' => (int) $values['limite_utilizadores'],
            ':preco' => (string) $values['preco_kz'],
            ':ativo' => (int) $values['ativo'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $values): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE planos SET nome = :nome, limite_membros = :membros, '
            . 'limite_utilizadores = :utilizadores, preco_kz = :preco, ativo = :ativo WHERE id = :id'
        );
        $statement->execute([
            ':nome' => $values['nome'],
            ':membros' => (int) $values['limite_membros'],
            ':utilizadores' => (int) $values['limite_utilizadores'],
            ':preco' => (string) $values['preco_kz'],
            ':ativo' => (int) $values['ativo'],
            ':id' => $id,
        ]);
        return $statement->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $statement = Database::connection()->prepare('DELETE FROM planos WHERE id = :id');
        $statement->execute([':id' => $id]);
        return $statement->rowCount() === 1;
    }
}
