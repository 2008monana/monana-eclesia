<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Agregações do painel limitadas ao tenant autenticado. */
final class DashboardRepository
{
    public function membrosActivos(int $churchId): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM membros WHERE igreja_id = :igreja_id AND ativo = 1');
        $statement->execute([':igreja_id' => $churchId]);
        return (int) $statement->fetchColumn();
    }

    public function cotasDoMes(int $churchId, string $start, string $end): array
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) AS quantidade, COALESCE(SUM(valor), 0) AS total FROM cotas_diarias '
            . 'WHERE igreja_id = :igreja_id AND data_pagamento >= :inicio AND data_pagamento < :fim'
        );
        $statement->execute([':igreja_id' => $churchId, ':inicio' => $start, ':fim' => $end]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? ['quantidade' => (int) $row['quantidade'], 'total' => round((float) $row['total'], 2)] : ['quantidade' => 0, 'total' => 0.0];
    }

    public function contribuicoesDoAno(int $churchId, int $year): float
    {
        $statement = Database::connection()->prepare(
            'SELECT COALESCE(SUM(valor), 0) FROM contribuicao_fim_ano WHERE igreja_id = :igreja_id AND ano = :ano'
        );
        $statement->execute([':igreja_id' => $churchId, ':ano' => $year]);
        return round((float) $statement->fetchColumn(), 2);
    }

    public function despesasDoMes(int $churchId, string $start, string $end): float
    {
        $statement = Database::connection()->prepare(
            'SELECT COALESCE(SUM(valor), 0) FROM saidas '
            . 'WHERE igreja_id = :igreja_id AND data_saida >= :inicio AND data_saida < :fim'
        );
        $statement->execute([':igreja_id' => $churchId, ':inicio' => $start, ':fim' => $end]);
        return round((float) $statement->fetchColumn(), 2);
    }

    public function actividadeRecente(int $churchId, int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $statement = Database::connection()->prepare(
            'SELECT id, usuario_nome, acao, modulo, descricao, criado_em FROM logs_auditoria '
            . 'WHERE igreja_id = :igreja_id ORDER BY criado_em DESC, id DESC LIMIT ' . $limit
        );
        $statement->execute([':igreja_id' => $churchId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
