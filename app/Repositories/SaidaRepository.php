<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOStatement;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Operações de saída e saldos por fundo dentro do tenant autenticado. */
final class SaidaRepository
{
    public function listar(int $churchId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT s.id, s.descricao, s.valor, s.data_saida, s.categoria, s.origem_fundo, '
            . 's.beneficiario, s.registrado_por, s.criado_em, u.nome_completo AS registrado_nome '
            . 'FROM saidas AS s LEFT JOIN usuarios AS u '
            . 'ON u.id = s.registrado_por AND u.igreja_id = :igreja_usuario '
            . 'WHERE s.igreja_id = :igreja_saida ORDER BY s.data_saida DESC, s.id DESC'
        );
        $statement->execute([':igreja_usuario' => $churchId, ':igreja_saida' => $churchId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function encontrar(int $churchId, int $expenseId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, descricao, valor, data_saida, categoria, origem_fundo, beneficiario, registrado_por, criado_em '
            . 'FROM saidas WHERE id = :id AND igreja_id = :igreja_id LIMIT 1'
        );
        $statement->execute([':id' => $expenseId, ':igreja_id' => $churchId]);
        $expense = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($expense) ? $expense : null;
    }

    public function totaisDespesas(int $churchId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT origem_fundo, COALESCE(SUM(valor), 0) AS total '
            . 'FROM saidas WHERE igreja_id = :igreja_id GROUP BY origem_fundo'
        );
        $statement->execute([':igreja_id' => $churchId]);
        $totals = ['cotas' => 0.0, 'contribuicoes' => 0.0];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $fund = (string) ($row['origem_fundo'] ?? '');
            if (array_key_exists($fund, $totals)) {
                $totals[$fund] = round((float) $row['total'], 2);
            }
        }
        return $totals;
    }

    public function totaisEntradas(int $churchId): array
    {
        $quotas = Database::connection()->prepare(
            'SELECT COALESCE(SUM(valor), 0) FROM cotas_diarias WHERE igreja_id = :igreja_id'
        );
        $quotas->execute([':igreja_id' => $churchId]);
        $yearEnd = Database::connection()->prepare(
            'SELECT COALESCE(SUM(valor), 0) FROM contribuicao_fim_ano WHERE igreja_id = :igreja_id'
        );
        $yearEnd->execute([':igreja_id' => $churchId]);
        return ['cotas' => (float) $quotas->fetchColumn(), 'contribuicoes' => (float) $yearEnd->fetchColumn()];
    }

    public function criar(int $churchId, int $actorId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO saidas '
            . '(igreja_id, descricao, valor, data_saida, categoria, origem_fundo, beneficiario, registrado_por) '
            . 'VALUES (:igreja_id, :descricao, :valor, :data_saida, :categoria, :origem_fundo, :beneficiario, :registrado_por)'
        );
        $this->executar($statement, [
            ':igreja_id' => $churchId,
            ':descricao' => $data['descricao'],
            ':valor' => $data['valor'],
            ':data_saida' => $data['data_saida'],
            ':categoria' => $data['categoria'],
            ':origem_fundo' => $data['origem_fundo'],
            ':beneficiario' => $data['beneficiario'],
            ':registrado_por' => $actorId,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function actualizar(int $churchId, int $expenseId, array $data): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE saidas SET descricao = :descricao, valor = :valor, data_saida = :data_saida, '
            . 'categoria = :categoria, origem_fundo = :origem_fundo, beneficiario = :beneficiario '
            . 'WHERE id = :id AND igreja_id = :igreja_id'
        );
        $this->executar($statement, [
            ':descricao' => $data['descricao'],
            ':valor' => $data['valor'],
            ':data_saida' => $data['data_saida'],
            ':categoria' => $data['categoria'],
            ':origem_fundo' => $data['origem_fundo'],
            ':beneficiario' => $data['beneficiario'],
            ':id' => $expenseId,
            ':igreja_id' => $churchId,
        ]);
        return true;
    }

    public function apagar(int $churchId, int $expenseId): bool
    {
        $statement = Database::connection()->prepare('DELETE FROM saidas WHERE id = :id AND igreja_id = :igreja_id');
        $statement->execute([':id' => $expenseId, ':igreja_id' => $churchId]);
        return $statement->rowCount() === 1;
    }

    private function executar(PDOStatement $statement, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $type = $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $statement->bindValue($name, $value, $type);
        }
        $statement->execute();
    }
}
