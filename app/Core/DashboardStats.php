<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

final class DashboardStats
{
    private const MESES = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    public static function forTenant(int $igrejaId): array
    {
        // Intervalos de datas (usam índices, ao contrário de MONTH()/YEAR())
        $hoje       = new DateTimeImmutable('today');
        $inicioMes  = $hoje->modify('first day of this month');
        $inicioProx = $inicioMes->modify('+1 month');
        $inicio6m   = $inicioMes->modify('-5 months');

        // Membros activos
        $membros = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `members`
             WHERE `igreja_id` = :i AND `status` = \'activo\' AND `deletado_em` IS NULL',
            ['i' => $igrejaId]
        )['n'] ?? 0);

        // Entradas e despesas por mês (últimos 6 meses) — 1 única query
        $linhas = Database::select(
            'SELECT DATE_FORMAT(`data`, \'%Y-%m\') AS ym, `tipo`, COALESCE(SUM(`valor_kz`), 0) AS total
             FROM `transactions`
             WHERE `igreja_id` = :i AND `data` >= :ini AND `data` < :fim
             GROUP BY ym, `tipo`',
            ['i' => $igrejaId, 'ini' => $inicio6m->format('Y-m-d'), 'fim' => $inicioProx->format('Y-m-d')]
        );

        $porMes = [];
        foreach ($linhas as $l) {
            $porMes[$l['ym']][$l['tipo']] = (float) $l['total'];
        }

        $evolucao = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $inicioMes->modify("-{$i} months"); // sempre dia 1: sem saltos de mês
            $k = $m->format('Y-m');
            $evolucao[] = [
                'label'    => self::MESES[(int) $m->format('n') - 1],
                'entradas' => $porMes[$k]['entrada'] ?? 0.0,
                'saidas'   => $porMes[$k]['despesa'] ?? 0.0,
            ];
        }

        $mesAtual    = $inicioMes->format('Y-m');
        $ofertasMes  = $porMes[$mesAtual]['entrada'] ?? 0.0;
        $despesasMes = $porMes[$mesAtual]['despesa'] ?? 0.0;

        // Departamentos e células activos
        $departamentos = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `departments` WHERE `igreja_id` = :i AND `ativo` = 1',
            ['i' => $igrejaId]
        )['n'] ?? 0);

        $celulas = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `cells` WHERE `igreja_id` = :i AND `ativo` = 1',
            ['i' => $igrejaId]
        )['n'] ?? 0);

        // Distribuição por departamento (para rosca)
        $distribuicao = Database::select(
            'SELECT d.`nome`, d.`cor`, COUNT(dm.`member_id`) AS total
             FROM `departments` d
             LEFT JOIN `department_members` dm ON dm.department_id = d.id
             WHERE d.`igreja_id` = :i AND d.`ativo` = 1
             GROUP BY d.id, d.`nome`, d.`cor`
             ORDER BY total DESC',
            ['i' => $igrejaId]
        );

        // Próximos eventos (5)
        $proximosEventos = Database::select(
            'SELECT `id`, `titulo`, `data`, `hora`, `local`, `tipo`
             FROM `events`
             WHERE `igreja_id` = :i AND `data` >= CURDATE()
             ORDER BY `data` ASC, `hora` ASC
             LIMIT 5',
            ['i' => $igrejaId]
        );

        return [
            'membros'          => $membros,
            'ofertas_mes'      => (float) $ofertasMes,
            'despesas_mes'     => (float) $despesasMes,
            'departamentos'    => $departamentos,
            'celulas'          => $celulas,
            'distribuicao'     => $distribuicao,
            'evolucao'         => $evolucao,
            'proximos_eventos' => $proximosEventos,
        ];
    }
}
