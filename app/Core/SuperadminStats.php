<?php

declare(strict_types=1);

namespace App\Core;

final class SuperadminStats
{
    public static function overview(): array
    {
        // Total de igrejas ativas
        $totalIgrejas = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `igrejas`
             WHERE `deletado_em` IS NULL AND `status` IN ("em_teste","activa")'
        )['n'] ?? 0);

        // Igrejas ativas
        $ativas = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `igrejas`
             WHERE `deletado_em` IS NULL AND `status` = "activa"'
        )['n'] ?? 0);

        // Em teste
        $emTeste = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `igrejas`
             WHERE `deletado_em` IS NULL AND `status` = "em_teste"'
        )['n'] ?? 0);

        // Suspensas/canceladas
        $suspensas = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `igrejas`
             WHERE `deletado_em` IS NULL AND `status` IN ("suspensa","cancelada")'
        )['n'] ?? 0);

        // Total de membros em toda a plataforma
        $totalMembros = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `members` WHERE `deletado_em` IS NULL'
        )['n'] ?? 0);

        // Total de utilizadores
        $totalUsers = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `users` WHERE `deletado_em` IS NULL'
        )['n'] ?? 0);

        // Receita do mês (pagamentos confirmados)
        $receitaMes = (float) (Database::first(
            'SELECT COALESCE(SUM(`valor_kz`), 0) AS s FROM `payments`
             WHERE `estado` = "confirmado"
               AND MONTH(`criado_em`) = MONTH(CURDATE())
               AND YEAR(`criado_em`) = YEAR(CURDATE())'
        )['s'] ?? 0);

        // Assinaturas ativas
        $assinaturasAtivas = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `subscriptions`
             WHERE `status` IN ("activa","trial","expirando")'
        )['n'] ?? 0);

        // Assinaturas expiradas/suspensas
        $assinaturasProblema = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `subscriptions`
             WHERE `status` IN ("expirada","em_tolerancia","suspensa","cancelada")'
        )['n'] ?? 0);

        // Distribuição por plano (para gráfico de rosca)
        $porPlano = Database::select(
            'SELECT p.nome, COUNT(s.id) AS total
             FROM `planos` p
             LEFT JOIN `subscriptions` s ON s.plano_id = p.id
                 AND s.status IN ("activa","trial","expirando")
             WHERE p.ativo = 1
             GROUP BY p.id
             ORDER BY p.ordem'
        );

        // Evolução de novas igrejas (últimos 6 meses)
        $evolucao = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts = strtotime("-{$i} months");
            $mes = (int) date('n', $ts);
            $ano = (int) date('Y', $ts);
            $label = date('M', $ts);

            $novas = (int) (Database::first(
                'SELECT COUNT(*) AS n FROM `igrejas`
                 WHERE MONTH(`criado_em`) = :m AND YEAR(`criado_em`) = :a',
                ['m' => $mes, 'a' => $ano]
            )['n'] ?? 0);

            $evolucao[] = ['label' => $label, 'total' => $novas];
        }

        // Últimas 5 igrejas criadas
        $ultimasIgrejas = Database::select(
            'SELECT i.*, p.nome AS plano_nome
             FROM `igrejas` i
             LEFT JOIN `planos` p ON p.id = i.plano_id
             WHERE i.deletado_em IS NULL
             ORDER BY i.id DESC
             LIMIT 5'
        );

        return [
            'total_igrejas'       => $totalIgrejas,
            'ativas'              => $ativas,
            'em_teste'            => $emTeste,
            'suspensas'           => $suspensas,
            'total_membros'       => $totalMembros,
            'total_users'         => $totalUsers,
            'receita_mes'         => $receitaMes,
            'assinaturas_ativas'  => $assinaturasAtivas,
            'assinaturas_problema'=> $assinaturasProblema,
            'por_plano'           => $porPlano,
            'evolucao'            => $evolucao,
            'ultimas_igrejas'     => $ultimasIgrejas,
        ];
    }
}