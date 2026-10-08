<?php
// modules/year-end/functions.php

function getCategoriaLabel($categoria) {
    $labels = [
        'mama' => ['label' => 'Mamãs', 'icon' => 'fa-female', 'color' => '#b5527a'],
        'papa' => ['label' => 'Papás', 'icon' => 'fa-male', 'color' => '#3f7d4e'],
        'jovem' => ['label' => 'Jovens', 'icon' => 'fa-user-graduate', 'color' => '#7a5ca8']
    ];
    return $labels[$categoria] ?? ['label' => ucfirst($categoria), 'icon' => 'fa-user', 'color' => '#gray-500'];
}

function getTotalPagoFimAno($membro_id, $ano) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT SUM(valor) as total_pago, COUNT(*) as qtd_pagamentos
        FROM contribuicao_fim_ano 
        WHERE membro_id = :membro_id AND ano = :ano
    ");
    $stmt->execute([
        ':membro_id' => $membro_id,
        ':ano' => $ano
    ]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getSaldoRestanteFimAno($membro_id, $ano) {
    $total = getTotalPagoFimAno($membro_id, $ano);
    $total_pago = $total['total_pago'] ?? 0;
    return max(0, 5000 - $total_pago);
}

function getPagamentosFimAno($membro_id, $ano) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT id, valor, data_pagamento, registrado_por
        FROM contribuicao_fim_ano 
        WHERE membro_id = :membro_id AND ano = :ano
        ORDER BY data_pagamento ASC
    ");
    $stmt->execute([
        ':membro_id' => $membro_id,
        ':ano' => $ano
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Total já retirado (saídas) do fundo de contribuições de fim de ano,
 * num determinado ano. Usado para mostrar o card de "Saídas" na página
 * de Contribuição de Fim de Ano.
 */
function getTotalSaidasFimAno($ano) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(valor), 0) as total
        FROM saidas
        WHERE origem_fundo = 'contribuicoes' AND YEAR(data_saida) = :ano
    ");
    $stmt->execute([':ano' => $ano]);

    return (float) $stmt->fetch()['total'];
}

function getResumoFimAno($ano, $categoria = null) {
    global $conn;
    
    $where = "";
    // O PDO com emulação de prepares desligada (ver config/database.php)
    // NÃO permite repetir o mesmo parâmetro nomeado duas vezes na mesma
    // query - isso gerava um PDOException fatal sempre que esta função era
    // chamada, o que deixava a página de Contribuição de Fim de Ano em
    // branco (o erro acontecia antes de qualquer HTML ser enviado).
    // Por isso usamos :ano e :ano2 em vez de reutilizar :ano.
    $params = [':ano' => $ano, ':ano2' => $ano];
    
    if ($categoria) {
        $where = "AND m.categoria = :categoria";
        $params[':categoria'] = $categoria;
    }
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT m.id) as total_membros,
            COUNT(DISTINCT CASE WHEN c.id IS NOT NULL THEN m.id END) as membros_com_pagamento,
            SUM(c.valor) as total_arrecadado,
            COUNT(DISTINCT CASE WHEN (SELECT SUM(valor) FROM contribuicao_fim_ano WHERE membro_id = m.id AND ano = :ano2) >= 5000 THEN m.id END) as total_quitados
        FROM membros m
        LEFT JOIN contribuicao_fim_ano c ON m.id = c.membro_id AND c.ano = :ano
        WHERE m.ativo = 1 AND m.categoria IN ('mama', 'papa', 'jovem')
        $where
    ");
    $stmt->execute($params);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>