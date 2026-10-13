<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Agregações financeiras sempre limitadas ao tenant e ao período pedido. */
final class RelatorioRepository
{
    public function resumo(int $churchId, string $start, string $end): array
    {
        $quotas = $this->sum('cotas_diarias', 'data_pagamento', $churchId, $start, $end);
        $yearEnd = $this->sum('contribuicao_fim_ano', 'data_pagamento', $churchId, $start, $end);
        $expenses = Database::connection()->prepare(
            'SELECT origem_fundo, COALESCE(SUM(valor), 0) AS total FROM saidas '
            . 'WHERE igreja_id = :igreja_id AND data_saida BETWEEN :inicio AND :fim GROUP BY origem_fundo'
        );
        $expenses->execute([':igreja_id' => $churchId, ':inicio' => $start, ':fim' => $end]);
        $spent = ['cotas' => 0.0, 'contribuicoes' => 0.0];
        foreach ($expenses->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $fund = (string) ($row['origem_fundo'] ?? '');
            if (isset($spent[$fund])) {
                $spent[$fund] = round((float) $row['total'], 2);
            }
        }
        return [
            'cotas' => $quotas,
            'contribuicoes' => $yearEnd,
            'saidas_cotas' => $spent['cotas'],
            'saidas_contribuicoes' => $spent['contribuicoes'],
            'saldo_cotas' => round($quotas - $spent['cotas'], 2),
            'saldo_contribuicoes' => round($yearEnd - $spent['contribuicoes'], 2),
        ];
    }

    public function serieMensal(int $churchId, int $year): array
    {
        $statement = Database::connection()->prepare(
            'SELECT MONTH(data_pagamento) AS mes, SUM(valor) AS total FROM cotas_diarias '
            . 'WHERE igreja_id = :igreja_id AND YEAR(data_pagamento) = :ano GROUP BY MONTH(data_pagamento)'
        );
        $statement->execute([':igreja_id' => $churchId, ':ano' => $year]);
        $quotas = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $quotas[(int) $row['mes']] = (float) $row['total'];
        }
        $yearEnd = Database::connection()->prepare(
            'SELECT MONTH(data_pagamento) AS mes, SUM(valor) AS total FROM contribuicao_fim_ano '
            . 'WHERE igreja_id = :igreja_id AND YEAR(data_pagamento) = :ano GROUP BY MONTH(data_pagamento)'
        );
        $yearEnd->execute([':igreja_id' => $churchId, ':ano' => $year]);
        $contributions = [];
        foreach ($yearEnd->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $contributions[(int) $row['mes']] = (float) $row['total'];
        }
        $expenses = Database::connection()->prepare(
            'SELECT MONTH(data_saida) AS mes, origem_fundo, SUM(valor) AS total FROM saidas '
            . 'WHERE igreja_id = :igreja_id AND YEAR(data_saida) = :ano '
            . 'GROUP BY MONTH(data_saida), origem_fundo'
        );
        $expenses->execute([':igreja_id' => $churchId, ':ano' => $year]);
        $spent = [];
        foreach ($expenses->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $spent[(int) $row['mes']][(string) $row['origem_fundo']] = (float) $row['total'];
        }
        $series = [];
        for ($month = 1; $month <= 12; $month++) {
            $series[] = [
                'mes' => $month,
                'cotas' => round((float) ($quotas[$month] ?? 0), 2),
                'contribuicoes' => round((float) ($contributions[$month] ?? 0), 2),
                'saidas_cotas' => round((float) ($spent[$month]['cotas'] ?? 0), 2),
                'saidas_contribuicoes' => round((float) ($spent[$month]['contribuicoes'] ?? 0), 2),
            ];
        }
        return $series;
    }

    public function despesasPorCategoria(int $churchId, string $start, string $end): array
    {
        $statement = Database::connection()->prepare(
            'SELECT origem_fundo, categoria, SUM(valor) AS total FROM saidas '
            . 'WHERE igreja_id = :igreja_id AND data_saida BETWEEN :inicio AND :fim '
            . 'GROUP BY origem_fundo, categoria ORDER BY origem_fundo, categoria'
        );
        $statement->execute([':igreja_id' => $churchId, ':inicio' => $start, ':fim' => $end]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function sum(string $table, string $dateColumn, int $churchId, string $start, string $end): float
    {
        $allowedTables = ['cotas_diarias', 'contribuicao_fim_ano'];
        $allowedColumns = ['data_pagamento'];
        if (!in_array($table, $allowedTables, true) || !in_array($dateColumn, $allowedColumns, true)) {
            throw new \InvalidArgumentException('Origem de relatório não permitida.');
        }
        $statement = Database::connection()->prepare(
            'SELECT COALESCE(SUM(valor), 0) FROM ' . $table
            . ' WHERE igreja_id = :igreja_id AND ' . $dateColumn . ' BETWEEN :inicio AND :fim'
        );
        $statement->execute([':igreja_id' => $churchId, ':inicio' => $start, ':fim' => $end]);
        return round((float) $statement->fetchColumn(), 2);
    }
}
