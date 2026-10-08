<?php
// modules/lists/functions.php

function getDomingosDoMes($mes, $ano) {
    $domingos = [];
    $data = new DateTime("$ano-$mes-01");
    $ultimo_dia = $data->format('t');
    
    for ($dia = 1; $dia <= $ultimo_dia; $dia++) {
        $data_atual = new DateTime("$ano-$mes-$dia");
        if ($data_atual->format('w') == 0) { // 0 = Domingo
            $domingos[] = $data_atual->format('Y-m-d');
        }
    }
    
    return $domingos;
}

function getCategoriaLabel($categoria) {
    $labels = [
        'mama' => ['label' => 'Mamãs', 'icon' => 'fa-female', 'color' => '#b5527a'],
        'papa' => ['label' => 'Papás', 'icon' => 'fa-male', 'color' => '#3f7d4e'],
        'jovem' => ['label' => 'Jovens', 'icon' => 'fa-user-graduate', 'color' => '#7a5ca8']
    ];
    return $labels[$categoria] ?? ['label' => ucfirst($categoria), 'icon' => 'fa-user', 'color' => '#gray-500'];
}

/**
 * Busca os pagamentos cujo mes_referencia é o mês/ano pedido - ou seja,
 * os pagamentos que contam para a dívida DESSE mês, independentemente
 * de terem sido feitos num domingo diferente (ex: dívida de Janeiro paga
 * só em Agosto). Usado só para mostrar o selo "Quitado (pago em ...)"
 * quando o mês antigo não tem nenhum valor nas colunas de domingo mas já
 * está quitado por um pagamento posterior.
 */
function getPagamentoQuitacaoMes($membro_id, $mes, $ano) {
    global $conn;

    $mes_referencia = sprintf('%04d-%02d-01', (int) $ano, (int) $mes);
    $inicio_mes_visto = $mes_referencia;
    $fim_mes_visto = date('Y-m-t', strtotime($inicio_mes_visto));

    // 1) Caso direto: existe um registo com mes_referencia igual a este mês,
    // mas pago fisicamente noutra data.
    $stmt = $conn->prepare("
        SELECT data_pagamento, valor
        FROM cotas_diarias
        WHERE membro_id = :membro_id
        AND mes_referencia = :mes_referencia
        AND data_pagamento NOT BETWEEN :inicio AND :fim
        ORDER BY data_pagamento ASC
    ");
    $stmt->execute([
        ':membro_id' => $membro_id,
        ':mes_referencia' => $mes_referencia,
        ':inicio' => $inicio_mes_visto,
        ':fim' => $fim_mes_visto
    ]);
    $direto = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($direto)) {
        return $direto;
    }

    // 2) Caso por adiantamento: este mês não tem registo próprio, mas já
    // está quitado no saldo acumulado (ver verificarStatusMes) porque um
    // pagamento maior, atribuído por inteiro a um mês anterior, "sobrou"
    // o suficiente para cobrir também este mês. Mostra esse pagamento
    // anterior como a origem da quitação.
    $saldo = calcularSaldoMembro($membro_id, $mes, $ano);
    if ($saldo['saldo'] >= 0) {
        $stmt2 = $conn->prepare("
            SELECT data_pagamento, valor
            FROM cotas_diarias
            WHERE membro_id = :membro_id
            AND mes_referencia < :mes_referencia
            AND YEAR(mes_referencia) = :ano
            ORDER BY mes_referencia DESC, data_pagamento DESC
            LIMIT 1
        ");
        $stmt2->execute([
            ':membro_id' => $membro_id,
            ':mes_referencia' => $mes_referencia,
            ':ano' => $ano
        ]);
        $origem = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($origem) {
            return [$origem];
        }
    }

    return [];
}

/**
 * Calcula o saldo de um membro até um determinado mês.
 *
 * As dívidas reiniciam a cada novo ano: a partir de Janeiro, qualquer
 * mês em dívida do ano anterior é ignorado e a contagem começa do zero.
 * Por isso a soma do que já foi pago e o número de meses devidos
 * consideram sempre apenas Janeiro do próprio $ano até $mes.
 */
function calcularSaldoMembro($membro_id, $mes, $ano) {
    global $conn;
    
    // Nunca considerar um ano anterior ao início das cotas.
    $ano = max((int) $ano, ANO_INICIO_COTAS);
    
    $data_inicio = "$ano-01-01";
    $data_limite = "$ano-$mes-01";
    
    $stmt = $conn->prepare("
        SELECT SUM(valor) as total_pago
        FROM cotas_diarias
        WHERE membro_id = :membro_id
        AND mes_referencia BETWEEN :data_inicio AND :data_limite
    ");
    $stmt->execute([
        ':membro_id' => $membro_id,
        ':data_inicio' => $data_inicio,
        ':data_limite' => $data_limite
    ]);
    $total_pago = $stmt->fetch()['total_pago'] ?? 0;
    
    // Sempre Janeiro do próprio ano - as dívidas de anos anteriores não contam.
    $meses_deveria = (int) $mes;
    
    $deveria_pagar = $meses_deveria * 1000;
    $saldo = $total_pago - $deveria_pagar;
    
    return [
        'total_pago' => $total_pago,
        'deveria_pagar' => $deveria_pagar,
        'saldo' => $saldo,
        'meses_quitados' => floor($total_pago / 1000),
        'meses_devendo' => max(0, $meses_deveria - floor($total_pago / 1000))
    ];
}

/**
 * Busca pagamentos de um membro em um mês específico.
 *
 * IMPORTANTE: filtra por data_pagamento (a data real do domingo em que o
 * dinheiro entrou), não por mes_referencia. O mes_referencia serve apenas
 * para controlo de dívida (calcularSaldoMembro) e pode apontar para um mês
 * mais antigo em dívida - se filtrássemos por ele, um pagamento feito hoje
 * mas usado para quitar um mês anterior desapareceria da coluna do domingo
 * de hoje na lista. Mesma lógica já usada em reports/functions.php.
 */
function getPagamentosMembro($membro_id, $mes, $ano) {
    global $conn;
    
    $inicio = sprintf('%04d-%02d-01', (int) $ano, (int) $mes);
    $fim = date('Y-m-t', strtotime($inicio));
    
    $stmt = $conn->prepare("
        SELECT data_pagamento, valor 
        FROM cotas_diarias 
        WHERE membro_id = :membro_id 
        AND data_pagamento BETWEEN :inicio AND :fim
        ORDER BY data_pagamento ASC
    ");
    $stmt->execute([
        ':membro_id' => $membro_id,
        ':inicio' => $inicio,
        ':fim' => $fim
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Verifica se o membro pagou em um determinado dia (domingo)
 * Retorna o valor pago ou null
 */
function getStatusPagamento($pagamentos, $data) {
    foreach ($pagamentos as $p) {
        $data_pagamento = date('Y-m-d', strtotime($p['data_pagamento']));
        if ($data_pagamento == $data) {
            return $p['valor'];
        }
    }
    return null;
}

/**
 * Valor fixo da cota mensal usado para determinar se um mês está quitado.
 * Mantido igual ao valor já usado em calcularSaldoMembro().
 */
function getCotaMensal() {
    return 1000;
}

/**
 * Calcula automaticamente o mês de referência para um novo pagamento:
 * devolve o mês mais antigo (a partir de Janeiro/2026) que o membro
 * ainda não tenha quitado. Se todos os meses até o mês atual já
 * estiverem quitados, devolve o mês atual.
 *
 * Isto substitui a escolha manual do "Mês Referência" no registo diário:
 * se o membro só pagou Agosto mas ainda deve Janeiro, o pagamento é
 * atribuído a Janeiro (o mês mais antigo em dívida), não a Agosto.
 *
 * IMPORTANTE: as dívidas reiniciam a cada novo ano. Quando o calendário
 * passa para Janeiro de um novo ano, qualquer mês em dívida do ano
 * anterior é ignorado - a procura pelo "mês mais antigo em dívida"
 * começa sempre em Janeiro do ano corrente, nunca em anos anteriores.
 */
function calcularProximoMesReferencia($membro_id) {
    global $conn;

    $cota_mensal = getCotaMensal();
    $ano_atual = (int) date('Y');
    $mes_atual = (int) date('m');

    // O ano de início da contagem é sempre o ano corrente (nunca antes
    // do início das cotas) - isto garante o reinício automático anual.
    $ano_inicio = max($ano_atual, ANO_INICIO_COTAS);
    $mes_inicio = 1;

    for ($y = $ano_inicio; $y <= $ano_atual; $y++) {
        $mes_ini_loop = ($y == $ano_inicio) ? $mes_inicio : 1;
        $mes_fim_loop = ($y == $ano_atual) ? $mes_atual : 12;

        for ($m = $mes_ini_loop; $m <= $mes_fim_loop; $m++) {
            $mes_referencia = sprintf('%04d-%02d-01', $y, $m);

            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(valor), 0) as total
                FROM cotas_diarias
                WHERE membro_id = :membro_id
                AND mes_referencia = :mes_referencia
            ");
            $stmt->execute([
                ':membro_id' => $membro_id,
                ':mes_referencia' => $mes_referencia
            ]);
            $total = (float) $stmt->fetch()['total'];

            if ($total < $cota_mensal) {
                return $mes_referencia;
            }
        }
    }

    // Todos os meses até hoje estão quitados - usa o mês atual
    return sprintf('%04d-%02d-01', $ano_atual, $mes_atual);
}

/**
 * Determina o mês de referência para UM pagamento total, sem nunca o
 * dividir entre dois meses.
 *
 * Ex: cota de 1000 Kz - se o membro deve Janeiro e Fevereiro e paga 2000 Kz
 * de uma só vez, isto devolve UM único registo: Janeiro (2000 Kz) - o mês
 * mais antigo em dívida recebe o pagamento inteiro, mesmo ultrapassando a
 * cota desse mês. O "adiantamento" fica dentro desse mesmo registo; é o
 * saldo acumulado (calcularSaldoMembro / verificarStatusMes) que reconhece
 * esse excedente e mostra Fevereiro como quitado também, sem precisar de
 * fragmentar o pagamento em dois registos. Isto evita que um único
 * pagamento real apareça partido em pedaços no registo diário e nas listas.
 *
 * Devolve um array com UM elemento ['mes_referencia' => 'YYYY-MM-01', 'valor' => float],
 * mantido em formato de array por compatibilidade com quem consome o resultado.
 */
function distribuirPagamentoPorMeses($membro_id, $valor_total) {
    global $conn;

    $cota_mensal = getCotaMensal();
    $ano_atual = (int) date('Y');

    // Início da contagem: sempre Janeiro do ano corrente (as dívidas de
    // anos anteriores não contam - mesma regra de calcularProximoMesReferencia).
    $ano_inicio = max($ano_atual, ANO_INICIO_COTAS);

    $y = $ano_inicio;
    $m = 1;
    $iteracoes_max = 240; // limite de segurança (20 anos à frente), evita loop infinito

    while ($iteracoes_max-- > 0) {
        $mes_referencia = sprintf('%04d-%02d-01', $y, $m);

        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(valor), 0) as total
            FROM cotas_diarias
            WHERE membro_id = :membro_id
            AND mes_referencia = :mes_referencia
        ");
        $stmt->execute([
            ':membro_id' => $membro_id,
            ':mes_referencia' => $mes_referencia
        ]);
        $ja_pago = (float) $stmt->fetch()['total'];

        if ($ja_pago < $cota_mensal - 0.001) {
            // Mês mais antigo em dívida encontrado: recebe o pagamento inteiro.
            return [[
                'mes_referencia' => $mes_referencia,
                'valor' => round((float) $valor_total, 2)
            ]];
        }

        $m++;
        if ($m > 12) {
            $m = 1;
            $y++;
        }
    }

    // Segurança: não deveria acontecer, mas evita perder o registo.
    return [[
        'mes_referencia' => sprintf('%04d-%02d-01', (int) date('Y'), (int) date('m')),
        'valor' => round((float) $valor_total, 2)
    ]];
}

/**
 * Verifica se um mês (mes/ano) específico do membro está quitado.
 *
 * IMPORTANTE (corrigido): usa o mesmo cálculo de saldo acumulado de
 * calcularSaldoMembro() - soma tudo o que tem mes_referencia até este mês
 * (inclusive) e compara com quantos meses já deveriam estar pagos. Isto
 * garante que um pagamento grande, atribuído por inteiro a um mês anterior
 * (sem ser dividido - ver distribuirPagamentoPorMeses), continue a marcar
 * corretamente os meses seguintes como quitados por adiantamento, mesmo
 * sem existir um registo próprio com mes_referencia igual a este mês.
 */
function verificarStatusMes($membro_id, $mes, $ano) {
    $saldo_info = calcularSaldoMembro($membro_id, $mes, $ano);

    return [
        'quitado' => $saldo_info['meses_quitados'] >= (int) $mes,
        'total_mes_referencia' => $saldo_info['total_pago']
    ];
}

/**
 * Função de debug para mostrar pagamentos (remova depois)
 */
function debugPagamentos($membro_id, $mes, $ano) {
    global $conn;
    $mes_ref = "$ano-$mes-01";
    $stmt = $conn->prepare("
        SELECT * FROM cotas_diarias 
        WHERE membro_id = :membro_id AND mes_referencia = :mes_ref
    ");
    $stmt->execute([':membro_id' => $membro_id, ':mes_ref' => $mes_ref]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>