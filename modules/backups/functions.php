<?php
// modules/backups/functions.php

/** Nome amigável (em português) para cada tabela da base de dados. */
function getTabelaLabel($tabela)
{
    $labels = [
        'membros'              => 'Membros',
        'usuarios'             => 'Utilizadores',
        'cotas_diarias'        => 'Registo Diário de Cotas',
        'contribuicao_fim_ano' => 'Contribuição de Fim de Ano',
        'saidas'               => 'Saídas/Despesas',
        'logs_auditoria'       => 'Auditoria',
    ];
    return $labels[$tabela] ?? ucfirst(str_replace('_', ' ', $tabela));
}

/** Ícone FontAwesome associado a cada tabela, para a listagem. */
function getTabelaIcon($tabela)
{
    $icons = [
        'membros'              => 'fa-users',
        'usuarios'             => 'fa-user-cog',
        'cotas_diarias'        => 'fa-calendar-day',
        'contribuicao_fim_ano' => 'fa-gift',
        'saidas'               => 'fa-money-bill-wave',
        'logs_auditoria'       => 'fa-shield-alt',
    ];
    return $icons[$tabela] ?? 'fa-table';
}

/** Devolve a lista de todas as tabelas da base de dados actual. */
function getListaTabelas(PDO $conn)
{
    $stmt = $conn->query('SHOW TABLES');
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Devolve estatísticas gerais da base de dados: número de tabelas, total
 * de registos, tamanho aproximado em disco e contagem por tabela.
 */
function getEstatisticasBackup(PDO $conn)
{
    $tabelas = getListaTabelas($conn);
    $porTabela = [];
    $totalRegistos = 0;

    foreach ($tabelas as $tabela) {
        $stmt = $conn->query('SELECT COUNT(*) AS total FROM `' . $tabela . '`');
        $total = (int) $stmt->fetch()['total'];
        $porTabela[$tabela] = $total;
        $totalRegistos += $total;
    }

    $tamanhoBytes = 0;
    try {
        $stmt = $conn->prepare(
            "SELECT SUM(data_length + index_length) AS tamanho
             FROM information_schema.tables
             WHERE table_schema = :db"
        );
        $stmt->execute([':db' => DB_NAME]);
        $tamanhoBytes = (float) ($stmt->fetch()['tamanho'] ?? 0);
    } catch (PDOException $e) {
        $tamanhoBytes = 0;
    }

    return [
        'tabelas'         => $tabelas,
        'por_tabela'      => $porTabela,
        'total_tabelas'   => count($tabelas),
        'total_registos'  => $totalRegistos,
        'tamanho_bytes'   => $tamanhoBytes,
    ];
}

/** Formata um número de bytes de forma legível (KB, MB, ...). */
function formatarBytes($bytes, $casas = 1)
{
    if ($bytes <= 0) return '0 KB';
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    $i = max(0, min($i, count($unidades) - 1));
    return round($bytes / (1024 ** $i), $casas) . ' ' . $unidades[$i];
}

/** Devolve o registo do último backup (SQL ou Excel) feito no sistema, se existir. */
function getUltimoBackup(PDO $conn)
{
    $stmt = $conn->prepare(
        "SELECT * FROM logs_auditoria WHERE modulo = 'backups' ORDER BY criado_em DESC LIMIT 1"
    );
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
?>
