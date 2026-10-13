<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOStatement;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Consultas de membros sempre limitadas pelo igreja_id autenticado. */
class MembroRepository
{
    /** Devolve uma página de membros com filtros preparados e escopo do tenant. */
    public function listar(int $churchId, array $filters, int $limit, int $offset): array
    {
        list($where, $parameters) = $this->where($churchId, $filters);
        $statement = Database::connection()->prepare(
            'SELECT m.id, m.nome_completo, m.categoria, m.telefone, m.endereco, '
            . 'm.data_nascimento, m.ativo, m.criado_em '
            . 'FROM membros AS m WHERE ' . $where
            . ' ORDER BY m.nome_completo ASC, m.id ASC LIMIT :limite OFFSET :deslocamento'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limite', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->bindValue(':deslocamento', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(int $churchId, array $filters): int
    {
        list($where, $parameters) = $this->where($churchId, $filters);
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM membros AS m WHERE ' . $where);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    /** Contagens da igreja autenticada; categorias contam apenas membros activos. */
    public function estatisticas(int $churchId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) AS total, '
            . "COALESCE(SUM(CASE WHEN categoria = 'mama' AND ativo = 1 THEN 1 ELSE 0 END), 0) AS mamas, "
            . "COALESCE(SUM(CASE WHEN categoria = 'papa' AND ativo = 1 THEN 1 ELSE 0 END), 0) AS papas, "
            . "COALESCE(SUM(CASE WHEN categoria = 'jovem' AND ativo = 1 THEN 1 ELSE 0 END), 0) AS jovens, "
            . "COALESCE(SUM(CASE WHEN categoria = 'crianca' AND ativo = 1 THEN 1 ELSE 0 END), 0) AS criancas "
            . 'FROM membros WHERE igreja_id = :igreja_id'
        );
        $statement->execute([':igreja_id' => $churchId]);
        $stats = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($stats) ? $stats : ['total' => 0, 'mamas' => 0, 'papas' => 0, 'jovens' => 0, 'criancas' => 0];
    }

    public function encontrar(int $churchId, int $memberId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, nome_completo, categoria, telefone, endereco, data_nascimento, ativo, criado_em '
            . 'FROM membros WHERE id = :id AND igreja_id = :igreja_id LIMIT 1'
        );
        $statement->execute([':id' => $memberId, ':igreja_id' => $churchId]);
        $member = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($member) ? $member : null;
    }

    public function criar(int $churchId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO membros '
            . '(igreja_id, nome_completo, categoria, telefone, endereco, data_nascimento, ativo) '
            . 'VALUES (:igreja_id, :nome_completo, :categoria, :telefone, :endereco, :data_nascimento, 1)'
        );
        $this->executar($statement, [
            ':igreja_id' => $churchId,
            ':nome_completo' => $data['nome_completo'],
            ':categoria' => $data['categoria'],
            ':telefone' => $data['telefone'],
            ':endereco' => $data['endereco'],
            ':data_nascimento' => $data['data_nascimento'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function actualizar(int $churchId, int $memberId, array $data): bool
    {
        return Database::transaction(function (PDO $pdo) use ($churchId, $memberId, $data): bool {
            $find = $pdo->prepare(
                'SELECT id FROM membros WHERE id = :id AND igreja_id = :igreja_id LIMIT 1 FOR UPDATE'
            );
            $find->execute([':id' => $memberId, ':igreja_id' => $churchId]);
            if ($find->fetchColumn() === false) {
                return false;
            }

            $statement = $pdo->prepare(
                'UPDATE membros SET nome_completo = :nome_completo, categoria = :categoria, '
                . 'telefone = :telefone, endereco = :endereco, data_nascimento = :data_nascimento '
                . 'WHERE id = :id AND igreja_id = :igreja_id'
            );
            $this->executar($statement, [
                ':nome_completo' => $data['nome_completo'],
                ':categoria' => $data['categoria'],
                ':telefone' => $data['telefone'],
                ':endereco' => $data['endereco'],
                ':data_nascimento' => $data['data_nascimento'],
                ':id' => $memberId,
                ':igreja_id' => $churchId,
            ]);
            return true;
        });
    }

    /** Alterna o estado no servidor, sem aceitar o estado pretendido do cliente. */
    public function alternarEstado(int $churchId, int $memberId): ?array
    {
        return Database::transaction(function (PDO $pdo) use ($churchId, $memberId): ?array {
            $find = $pdo->prepare(
                'SELECT id, nome_completo, categoria, ativo FROM membros '
                . 'WHERE id = :id AND igreja_id = :igreja_id LIMIT 1 FOR UPDATE'
            );
            $find->execute([':id' => $memberId, ':igreja_id' => $churchId]);
            $member = $find->fetch(PDO::FETCH_ASSOC);
            if (!is_array($member)) {
                return null;
            }

            $newStatus = (int) ($member['ativo'] ?? 0) === 1 ? 0 : 1;
            $update = $pdo->prepare(
                'UPDATE membros SET ativo = :ativo WHERE id = :id AND igreja_id = :igreja_id'
            );
            $update->execute([
                ':ativo' => $newStatus,
                ':id' => $memberId,
                ':igreja_id' => $churchId,
            ]);
            $member['ativo'] = $newStatus;
            return $member;
        });
    }

    /**
     * Apaga apenas se não houver histórico financeiro; caso contrário,
     * desactiva o membro para preservar relações e registos antigos.
     */
    public function removerOuDesactivar(int $churchId, int $memberId): ?array
    {
        return Database::transaction(function (PDO $pdo) use ($churchId, $memberId): ?array {
            $find = $pdo->prepare(
                'SELECT id, nome_completo, categoria, ativo FROM membros '
                . 'WHERE id = :id AND igreja_id = :igreja_id LIMIT 1 FOR UPDATE'
            );
            $find->execute([':id' => $memberId, ':igreja_id' => $churchId]);
            $member = $find->fetch(PDO::FETCH_ASSOC);
            if (!is_array($member)) {
                return null;
            }

            $payments = $pdo->prepare(
                'SELECT COUNT(*) FROM cotas_diarias AS c '
                . 'INNER JOIN membros AS m ON m.id = c.membro_id '
                . 'WHERE m.igreja_id = :igreja_membro AND c.igreja_id = :igreja_cota '
                . 'AND c.membro_id = :membro_id'
            );
            $payments->execute([
                ':igreja_membro' => $churchId,
                ':igreja_cota' => $churchId,
                ':membro_id' => $memberId,
            ]);
            $quotaHistory = (int) $payments->fetchColumn();

            $contributions = $pdo->prepare(
                'SELECT COUNT(*) FROM contribuicao_fim_ano AS f '
                . 'INNER JOIN membros AS m ON m.id = f.membro_id '
                . 'WHERE m.igreja_id = :igreja_membro AND f.igreja_id = :igreja_contribuicao '
                . 'AND f.membro_id = :membro_id'
            );
            $contributions->execute([
                ':igreja_membro' => $churchId,
                ':igreja_contribuicao' => $churchId,
                ':membro_id' => $memberId,
            ]);
            $yearEndHistory = (int) $contributions->fetchColumn();

            $hasHistory = $quotaHistory > 0 || $yearEndHistory > 0;
            if ($hasHistory) {
                $update = $pdo->prepare(
                    'UPDATE membros SET ativo = 0 WHERE id = :id AND igreja_id = :igreja_id'
                );
                $update->execute([':id' => $memberId, ':igreja_id' => $churchId]);
                $member['ativo'] = 0;
                return ['membro' => $member, 'removido' => false, 'historico' => true];
            }

            $delete = $pdo->prepare(
                'DELETE FROM membros WHERE id = :id AND igreja_id = :igreja_id'
            );
            $delete->execute([':id' => $memberId, ':igreja_id' => $churchId]);
            return ['membro' => $member, 'removido' => true, 'historico' => false];
        });
    }

    /** Vincula cada valor com o tipo PDO adequado, incluindo NULL SQL real. */
    private function executar(PDOStatement $statement, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            if ($value === null) {
                $type = PDO::PARAM_NULL;
            } elseif (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } else {
                $type = PDO::PARAM_STR;
            }
            $statement->bindValue($name, $value, $type);
        }
        $statement->execute();
    }

    /** Constrói filtros SQL apenas a partir de chaves e valores enumerados. */
    private function where(int $churchId, array $filters): array
    {
        $conditions = ['m.igreja_id = :igreja_id'];
        $parameters = [':igreja_id' => $churchId];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $conditions[] = '(m.nome_completo LIKE :nome_pesquisa OR m.telefone LIKE :telefone_pesquisa)';
            $term = '%' . $query . '%';
            $parameters[':nome_pesquisa'] = $term;
            $parameters[':telefone_pesquisa'] = $term;
        }

        $category = (string) ($filters['categoria'] ?? '');
        if (in_array($category, ['mama', 'papa', 'jovem', 'crianca'], true)) {
            $conditions[] = 'm.categoria = :categoria';
            $parameters[':categoria'] = $category;
        }

        $status = (string) ($filters['estado'] ?? 'todos');
        if ($status === 'activo') {
            $conditions[] = 'm.ativo = 1';
        } elseif ($status === 'inactivo') {
            $conditions[] = 'm.ativo = 0';
        }

        return [implode(' AND ', $conditions), $parameters];
    }
}
