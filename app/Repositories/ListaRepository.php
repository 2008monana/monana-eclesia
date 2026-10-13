<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Leituras usadas nas grelhas mensais das listas de membros. */
final class ListaRepository
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

    public function membros(int $churchId, string $category): array
    {
        $sql = 'SELECT id, nome_completo, categoria, telefone FROM membros '
            . 'WHERE igreja_id = :igreja_id AND ativo = 1';
        $parameters = [':igreja_id' => $churchId];
        if ($category !== 'todos') {
            $sql .= ' AND categoria = :categoria';
            $parameters[':categoria'] = $category;
        }
        $sql .= ' ORDER BY nome_completo ASC, id ASC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Pagamentos da data real, agrupados para preencher as colunas de domingo. */
    public function pagamentosDoMes(int $churchId, string $start, string $end): array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.membro_id, c.data_pagamento, SUM(c.valor) AS valor '
            . 'FROM cotas_diarias AS c '
            . 'INNER JOIN membros AS m ON m.id = c.membro_id AND m.igreja_id = :igreja_membro '
            . 'WHERE c.igreja_id = :igreja_cota AND c.data_pagamento BETWEEN :inicio AND :fim '
            . 'GROUP BY c.membro_id, c.data_pagamento ORDER BY c.data_pagamento ASC'
        );
        $statement->execute([
            ':igreja_membro' => $churchId,
            ':igreja_cota' => $churchId,
            ':inicio' => $start,
            ':fim' => $end,
        ]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Saldo acumulado por membro, sempre limitado pela igreja e referência. */
    public function totaisPorReferencia(int $churchId, string $start, string $end): array
    {
        $statement = Database::connection()->prepare(
            'SELECT membro_id, COALESCE(SUM(valor), 0) AS total_pago FROM cotas_diarias '
            . 'WHERE igreja_id = :igreja_id AND mes_referencia >= :inicio AND mes_referencia <= :fim '
            . 'GROUP BY membro_id'
        );
        $statement->execute([':igreja_id' => $churchId, ':inicio' => $start, ':fim' => $end]);
        $totals = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $totals[(int) $row['membro_id']] = round((float) $row['total_pago'], 2);
        }
        return $totals;
    }
}
