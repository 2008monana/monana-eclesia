<?php
// modules/reports/functions.php

function getNomeMes($mes) {
    $meses = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
        4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
        7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
        10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    ];
    return $meses[$mes] ?? $mes;
}

/**
 * Todas as funções abaixo aceitam um parâmetro $fundo ('cotas' ou
 * 'contribuicoes') para nunca misturar os dois fundos no mesmo número.
 *
 * - 'cotas': entradas vêm de cotas_diarias, saídas só as com
 *   origem_fundo = 'cotas'.
 * - 'contribuicoes': entradas vêm de contribuicao_fim_ano, saídas só as
 *   com origem_fundo = 'contribuicoes'.
 *
 * O relatório financeiro (modules/reports) deve sempre passar o fundo
 * explicitamente e nunca somar os dois sem indicar isso ao utilizador -
 * daí não existir um modo "todos" que os some às cegas.
 */

function getTotalEntradas($mes, $ano, $fundo = 'cotas') {
    global $conn;
    $inicio = "$ano-$mes-01";
    $fim = date('Y-m-t', strtotime($inicio));

    if ($fundo === 'contribuicoes') {
        // Entradas do fundo de contribuições de fim de ano.
        $stmt = $conn->prepare("
            SELECT SUM(valor) as total
            FROM contribuicao_fim_ano
            WHERE data_pagamento BETWEEN :inicio AND :fim
        ");
    } else {
        // Usa data_pagamento (data real em que o dinheiro entrou), não
        // mes_referencia (que só serve para controlo de dívida do membro).
        // Assim, um pagamento feito em Agosto conta como entrada de Agosto
        // no relatório, mesmo que tenha sido usado para quitar um mês antigo.
        $stmt = $conn->prepare("
            SELECT SUM(valor) as total 
            FROM cotas_diarias 
            WHERE data_pagamento BETWEEN :inicio AND :fim
        ");
    }
    $stmt->execute([':inicio' => $inicio, ':fim' => $fim]);
    return $stmt->fetch()['total'] ?? 0;
}

function getTotalSaidas($mes, $ano, $fundo = 'cotas') {
    global $conn;
    $inicio = "$ano-$mes-01";
    $fim = date('Y-m-t', strtotime($inicio));

    $stmt = $conn->prepare("
        SELECT SUM(valor) as total 
        FROM saidas 
        WHERE data_saida BETWEEN :inicio AND :fim
        AND origem_fundo = :fundo
    ");
    $stmt->execute([':inicio' => $inicio, ':fim' => $fim, ':fundo' => $fundo]);
    return $stmt->fetch()['total'] ?? 0;
}

function getEntradasPorCategoria($mes, $ano, $fundo = 'cotas') {
    global $conn;
    $inicio = "$ano-$mes-01";
    $fim = date('Y-m-t', strtotime($inicio));

    if ($fundo === 'contribuicoes') {
        $stmt = $conn->prepare("
            SELECT 
                m.categoria,
                SUM(cf.valor) as total
            FROM contribuicao_fim_ano cf
            JOIN membros m ON cf.membro_id = m.id
            WHERE cf.data_pagamento BETWEEN :inicio AND :fim
            GROUP BY m.categoria
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT 
                m.categoria,
                SUM(c.valor) as total
            FROM cotas_diarias c
            JOIN membros m ON c.membro_id = m.id
            WHERE c.data_pagamento BETWEEN :inicio AND :fim
            GROUP BY m.categoria
        ");
    }
    $stmt->execute([':inicio' => $inicio, ':fim' => $fim]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSaidasPorCategoria($mes, $ano, $fundo = 'cotas') {
    global $conn;
    $inicio = "$ano-$mes-01";
    $fim = date('Y-m-t', strtotime($inicio));
    
    $stmt = $conn->prepare("
        SELECT 
            categoria,
            SUM(valor) as total
        FROM saidas 
        WHERE data_saida BETWEEN :inicio AND :fim
        AND origem_fundo = :fundo
        GROUP BY categoria
    ");
    $stmt->execute([':inicio' => $inicio, ':fim' => $fim, ':fundo' => $fundo]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEntradasMensais($ano, $fundo = 'cotas') {
    global $conn;

    if ($fundo === 'contribuicoes') {
        $stmt = $conn->prepare("
            SELECT 
                MONTH(data_pagamento) as mes,
                SUM(valor) as total
            FROM contribuicao_fim_ano
            WHERE YEAR(data_pagamento) = :ano
            GROUP BY MONTH(data_pagamento)
            ORDER BY mes ASC
        ");
    } else {
        // Agrupa pelo mês real do pagamento (data_pagamento), não pelo
        // mes_referencia usado para controlo de dívida.
        $stmt = $conn->prepare("
            SELECT 
                MONTH(data_pagamento) as mes,
                SUM(valor) as total
            FROM cotas_diarias 
            WHERE YEAR(data_pagamento) = :ano
            GROUP BY MONTH(data_pagamento)
            ORDER BY mes ASC
        ");
    }
    $stmt->execute([':ano' => $ano]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preencher meses vazios
    $dados = array_fill(1, 12, 0);
    foreach ($resultados as $r) {
        $dados[$r['mes']] = $r['total'];
    }
    return $dados;
}

function getSaidasMensais($ano, $fundo = 'cotas') {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            MONTH(data_saida) as mes,
            SUM(valor) as total
        FROM saidas 
        WHERE YEAR(data_saida) = :ano
        AND origem_fundo = :fundo
        GROUP BY MONTH(data_saida)
        ORDER BY mes ASC
    ");
    $stmt->execute([':ano' => $ano, ':fundo' => $fundo]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $dados = array_fill(1, 12, 0);
    foreach ($resultados as $r) {
        $dados[$r['mes']] = $r['total'];
    }
    return $dados;
}

/**
 * Calcula o saldo disponível de um fundo específico ('cotas' ou 'contribuicoes').
 * Saldo = total de entradas do fundo - total de saídas já tiradas desse fundo.
 *
 * $excluir_saida_id: ao editar uma saída já existente, passa o ID dela para
 * que o próprio valor não seja descontado duas vezes do saldo.
 */
function getSaldoFundo($fundo, $excluir_saida_id = null) {
    global $conn;

    if ($fundo === 'contribuicoes') {
        $stmt = $conn->query("SELECT COALESCE(SUM(valor), 0) as total FROM contribuicao_fim_ano");
    } else {
        $fundo = 'cotas';
        $stmt = $conn->query("SELECT COALESCE(SUM(valor), 0) as total FROM cotas_diarias");
    }
    $entradas = (float) $stmt->fetch()['total'];

    $sql = "SELECT COALESCE(SUM(valor), 0) as total FROM saidas WHERE origem_fundo = :fundo";
    $params = [':fundo' => $fundo];
    if ($excluir_saida_id) {
        $sql .= " AND id != :excluir_id";
        $params[':excluir_id'] = $excluir_saida_id;
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $saidas_fundo = (float) $stmt->fetch()['total'];

    return $entradas - $saidas_fundo;
}

/**
 * Rótulo amigável para o fundo de origem de uma saída.
 */
function getFundoLabel($fundo) {
    $labels = [
        'cotas' => ['label' => 'Cotas', 'color' => '#c99a2e'],
        'contribuicoes' => ['label' => 'Contribuições', 'color' => '#7a5ca8']
    ];
    return $labels[$fundo] ?? ['label' => ucfirst($fundo), 'color' => '#6b7280'];
}

/**
 * Rótulos amigáveis para o seletor de fundo no relatório financeiro.
 */
function getFundosRelatorio() {
    return [
        'cotas' => 'Cotas',
        'contribuicoes' => 'Contribuições (fim de ano)'
    ];
}

function getResumoAnual($ano, $fundo = 'cotas') {
    $entradas = getEntradasMensais($ano, $fundo);
    $saidas = getSaidasMensais($ano, $fundo);
    
    $resumo = [];
    $total_entradas = 0;
    $total_saidas = 0;
    
    for ($mes = 1; $mes <= 12; $mes++) {
        $total_entradas += $entradas[$mes];
        $total_saidas += $saidas[$mes];
        $resumo[$mes] = [
            'entradas' => $entradas[$mes],
            'saidas' => $saidas[$mes],
            'saldo' => $entradas[$mes] - $saidas[$mes],
            'saldo_acumulado' => $total_entradas - $total_saidas
        ];
    }
    
    return $resumo;
}
?>