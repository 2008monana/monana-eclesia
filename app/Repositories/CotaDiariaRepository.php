<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOStatement;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Consultas de cotas diárias: cada consulta recebe o tenant autenticado. */
final class CotaDiariaRepository
{
    public function configuracao(int $churchId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT valor_cota, periodicidade_cota, inicio_cotas '
            . 'FROM configuracoes_igreja WHERE igreja_id = :igreja_id LIMIT 1'
        );
        $statement->execute([':igreja_id' => $churchId]);
        $config = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($config) ? $config : [];
    }

    public function membrosActivos(int $churchId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, nome_completo, categoria FROM membros '
            . 'WHERE igreja_id = :igreja_id AND ativo = 1 ORDER BY nome_completo ASC, id ASC'
        );
        $statement->execute([':igreja_id' => $churchId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function membroActivo(int $churchId, int $memberId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, nome_completo, categoria FROM membros '
            . 'WHERE id = :id AND igreja_id = :igreja_id AND ativo = 1 LIMIT 1'
        );
        $statement->execute([':id' => $memberId, ':igreja_id' => $churchId]);
        $member = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($member) ? $member : null;
    }

    public function listarPorData(int $churchId, string $date): array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.id, c.membro_id, c.data_pagamento, c.mes_referencia, c.valor, '
            . 'c.observacao, c.registrado_por, c.criado_em, m.nome_completo, m.categoria, '
            . 'u.nome_completo AS registrado_nome '
            . 'FROM cotas_diarias AS c '
            . 'INNER JOIN membros AS m ON m.id = c.membro_id AND m.igreja_id = :igreja_membro '
            . 'LEFT JOIN usuarios AS u ON u.id = c.registrado_por AND u.igreja_id = :igreja_usuario '
            . 'WHERE c.igreja_id = :igreja_cota AND c.data_pagamento = :data '
            . 'ORDER BY c.criado_em DESC, c.id DESC'
        );
        $statement->execute([
            ':igreja_membro' => $churchId,
            ':igreja_usuario' => $churchId,
            ':igreja_cota' => $churchId,
            ':data' => $date,
        ]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalPorReferencia(
        int $churchId,
        int $memberId,
        string $start,
        string $end,
        ?int $excludeId = null
    ): float {
        $sql = 'SELECT COALESCE(SUM(valor), 0) FROM cotas_diarias '
            . 'WHERE igreja_id = :igreja_id AND membro_id = :membro_id '
            . 'AND mes_referencia >= :inicio AND mes_referencia <= :fim';
        $parameters = [
            ':igreja_id' => $churchId,
            ':membro_id' => $memberId,
            ':inicio' => $start,
            ':fim' => $end,
        ];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :excluir_id';
            $parameters[':excluir_id'] = $excludeId;
        }
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return round((float) $statement->fetchColumn(), 2);
    }

    public function encontrar(int $churchId, int $paymentId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.id, c.membro_id, c.data_pagamento, c.mes_referencia, c.valor, '
            . 'c.observacao, c.registrado_por, c.criado_em, m.nome_completo, m.categoria '
            . 'FROM cotas_diarias AS c '
            . 'INNER JOIN membros AS m ON m.id = c.membro_id AND m.igreja_id = :igreja_membro '
            . 'WHERE c.id = :id AND c.igreja_id = :igreja_cota LIMIT 1'
        );
        $statement->execute([
            ':igreja_membro' => $churchId,
            ':id' => $paymentId,
            ':igreja_cota' => $churchId,
        ]);
        $payment = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($payment) ? $payment : null;
    }

    public function criar(int $churchId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO cotas_diarias '
            . '(igreja_id, membro_id, data_pagamento, mes_referencia, valor, observacao, registrado_por) '
            . 'VALUES (:igreja_id, :membro_id, :data_pagamento, :mes_referencia, :valor, :observacao, :registrado_por)'
        );
        $this->executar($statement, [
            ':igreja_id' => $churchId,
            ':membro_id' => (int) $data['membro_id'],
            ':data_pagamento' => $data['data_pagamento'],
            ':mes_referencia' => $data['mes_referencia'],
            ':valor' => $data['valor'],
            ':observacao' => $data['observacao'],
            ':registrado_por' => (int) $data['registrado_por'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function actualizar(int $churchId, int $paymentId, array $data): bool
    {
        return Database::transaction(function (PDO $pdo) use ($churchId, $paymentId, $data): bool {
            $find = $pdo->prepare(
                'SELECT id FROM cotas_diarias WHERE id = :id AND igreja_id = :igreja_id '
                . 'AND data_pagamento = :hoje LIMIT 1 FOR UPDATE'
            );
            $find->execute([
                ':id' => $paymentId,
                ':igreja_id' => $churchId,
                ':hoje' => $data['data_pagamento'],
            ]);
            if ($find->fetchColumn() === false) {
                return false;
            }

            $statement = $pdo->prepare(
                'UPDATE cotas_diarias SET membro_id = :membro_id, mes_referencia = :mes_referencia, '
                . 'valor = :valor, observacao = :observacao '
                . 'WHERE id = :id AND igreja_id = :igreja_id AND data_pagamento = :hoje'
            );
            $this->executar($statement, [
                ':membro_id' => (int) $data['membro_id'],
                ':mes_referencia' => $data['mes_referencia'],
                ':valor' => $data['valor'],
                ':observacao' => $data['observacao'],
                ':id' => $paymentId,
                ':igreja_id' => $churchId,
                ':hoje' => $data['data_pagamento'],
            ]);
            return true;
        });
    }

    public function apagarHoje(int $churchId, int $paymentId, string $today): bool
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM cotas_diarias WHERE id = :id AND igreja_id = :igreja_id AND data_pagamento = :hoje'
        );
        $statement->execute([':id' => $paymentId, ':igreja_id' => $churchId, ':hoje' => $today]);
        return $statement->rowCount() === 1;
    }

    /** Vincula explicitamente NULL, inteiros e valores decimais às instruções preparadas. */
    private function executar(PDOStatement $statement, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            if ($value === null) {
                $type = PDO::PARAM_NULL;
            } elseif (is_int($value)) {
                $type = PDO::PARAM_INT;
            } else {
                $type = PDO::PARAM_STR;
            }
            $statement->bindValue($name, $value, $type);
        }
        $statement->execute();
    }
}
