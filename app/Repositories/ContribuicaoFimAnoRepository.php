<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Persistência de contribuições e saídas anuais, isolada por igreja. */
final class ContribuicaoFimAnoRepository
{
    public function inicio(int $churchId): ?string
    {
        $statement = Database::connection()->prepare(
            'SELECT inicio_contribuicoes FROM configuracoes_igreja WHERE igreja_id = :igreja_id LIMIT 1'
        );
        $statement->execute([':igreja_id' => $churchId]);
        $value = $statement->fetchColumn();
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function membros(int $churchId, string $category): array
    {
        $sql = 'SELECT id, nome_completo, categoria, telefone FROM membros '
            . 'WHERE igreja_id = :igreja_id AND ativo = 1 AND categoria IN (\'mama\',\'papa\',\'jovem\')';
        $parameters = [':igreja_id' => $churchId];
        if ($category !== 'todos') {
            $sql .= ' AND categoria = :categoria';
            $parameters[':categoria'] = $category;
        }
        $sql .= " ORDER BY CASE categoria WHEN 'mama' THEN 1 WHEN 'papa' THEN 2 ELSE 3 END, nome_completo ASC";
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function membro(int $churchId, int $memberId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, nome_completo, categoria FROM membros '
            . 'WHERE id = :id AND igreja_id = :igreja_id AND ativo = 1 '
            . "AND categoria IN ('mama','papa','jovem') LIMIT 1"
        );
        $statement->execute([':id' => $memberId, ':igreja_id' => $churchId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function totaisPorAno(int $churchId, int $year): array
    {
        $statement = Database::connection()->prepare(
            'SELECT membro_id, SUM(valor) AS total_pago, COUNT(*) AS quantidade '
            . 'FROM contribuicao_fim_ano WHERE igreja_id = :igreja_id AND ano = :ano GROUP BY membro_id'
        );
        $statement->execute([':igreja_id' => $churchId, ':ano' => $year]);
        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[(int) $row['membro_id']] = [
                'total_pago' => round((float) $row['total_pago'], 2),
                'quantidade' => (int) $row['quantidade'],
            ];
        }
        return $rows;
    }

    public function pagamentos(int $churchId, int $year): array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.id, c.membro_id, c.ano, c.valor, c.data_pagamento, c.registrado_por, '
            . 'm.nome_completo, m.categoria, u.nome_completo AS registrado_nome '
            . 'FROM contribuicao_fim_ano AS c '
            . 'INNER JOIN membros AS m ON m.id = c.membro_id AND m.igreja_id = :igreja_membro '
            . 'LEFT JOIN usuarios AS u ON u.id = c.registrado_por AND u.igreja_id = :igreja_usuario '
            . 'WHERE c.igreja_id = :igreja_contribuicao AND c.ano = :ano '
            . 'ORDER BY c.data_pagamento ASC, c.id ASC'
        );
        $statement->execute([
            ':igreja_membro' => $churchId,
            ':igreja_usuario' => $churchId,
            ':igreja_contribuicao' => $churchId,
            ':ano' => $year,
        ]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalArrecadado(int $churchId, int $year): float
    {
        $statement = Database::connection()->prepare(
            'SELECT COALESCE(SUM(valor), 0) FROM contribuicao_fim_ano '
            . 'WHERE igreja_id = :igreja_id AND ano = :ano'
        );
        $statement->execute([':igreja_id' => $churchId, ':ano' => $year]);
        return round((float) $statement->fetchColumn(), 2);
    }

    public function totalDespesas(int $churchId, int $year): float
    {
        $statement = Database::connection()->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM saidas WHERE igreja_id = :igreja_id "
            . "AND origem_fundo = 'contribuicoes' AND YEAR(data_saida) = :ano"
        );
        $statement->execute([':igreja_id' => $churchId, ':ano' => $year]);
        return round((float) $statement->fetchColumn(), 2);
    }

    public function criar(int $churchId, int $memberId, int $year, float $amount, int $actorId): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO contribuicao_fim_ano '
            . '(igreja_id, membro_id, ano, valor, data_pagamento, registrado_por) '
            . 'VALUES (:igreja_id, :membro_id, :ano, :valor, :data_pagamento, :registrado_por)'
        );
        $statement->execute([
            ':igreja_id' => $churchId,
            ':membro_id' => $memberId,
            ':ano' => $year,
            ':valor' => $amount,
            ':data_pagamento' => date('Y-m-d'),
            ':registrado_por' => $actorId,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function apagarUm(int $churchId, int $paymentId): bool
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM contribuicao_fim_ano WHERE id = :id AND igreja_id = :igreja_id'
        );
        $statement->execute([':id' => $paymentId, ':igreja_id' => $churchId]);
        return $statement->rowCount() === 1;
    }

    public function apagarAnoMembro(int $churchId, int $memberId, int $year): int
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM contribuicao_fim_ano WHERE igreja_id = :igreja_id AND membro_id = :membro_id AND ano = :ano'
        );
        $statement->execute([':igreja_id' => $churchId, ':membro_id' => $memberId, ':ano' => $year]);
        return $statement->rowCount();
    }
}
