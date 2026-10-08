<?php
// modules/members/historico-pagamentos.php
// Devolve, em JSON, o histórico completo de pagamentos (cotas diárias e
// contribuição de fim de ano) de um membro - usado pelo card/modal de
// histórico nas listas de Membros, Mamãs, Papás e Jovens.
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once '../../config/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

// Acessível a quem tenha acesso a Membros OU a Listas (é usado nas duas áreas).
if (!podeAcessarModulo('members') && !podeAcessarModulo('lists')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão para consultar este histórico.']);
    exit;
}

require_once '../../config/database.php';
require_once '../lists/functions.php';

$membro_id = intval($_GET['membro_id'] ?? 0);

if ($membro_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Membro inválido.']);
    exit;
}

$conn = getConnection();

$stmt = $conn->prepare("SELECT id, nome_completo, categoria FROM membros WHERE id = :id");
$stmt->execute([':id' => $membro_id]);
$membro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$membro) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Membro não encontrado.']);
    exit;
}

// ------------------------------------------------------------
// Cotas diárias (pagamentos por domingo/dia, com mês de referência)
// ------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT id, data_pagamento, mes_referencia, valor, observacao
    FROM cotas_diarias
    WHERE membro_id = :membro_id
    ORDER BY data_pagamento DESC, id DESC
");
$stmt->execute([':membro_id' => $membro_id]);
$cotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_cotas = 0;
$anos_disponiveis = [];
$pagamentos = [];

// Total pago especificamente em cada mês de referência (não acumulado),
// usado para calcular corretamente a dívida em falta de cada mês.
$total_por_mes_referencia = [];

foreach ($cotas as $c) {
    $valor = (float) $c['valor'];
    $total_cotas += $valor;

    $chave_mes_ref = date('Y-n', strtotime($c['mes_referencia']));
    $total_por_mes_referencia[$chave_mes_ref] = ($total_por_mes_referencia[$chave_mes_ref] ?? 0) + $valor;

    $ano_pagamento = (int) date('Y', strtotime($c['data_pagamento']));
    $anos_disponiveis[$ano_pagamento] = true;

    $mes_ref_ts = strtotime($c['mes_referencia']);

    $pagamentos[] = [
        'id'                    => (int) $c['id'],
        'data_pagamento'        => $c['data_pagamento'],
        'data_pagamento_fmt'    => date('d/m/Y', strtotime($c['data_pagamento'])),
        'ano_pagamento'         => $ano_pagamento,
        'mes_referencia'        => $c['mes_referencia'],
        'mes_referencia_fmt'    => nomeMesPT((int) date('n', $mes_ref_ts)) . ' de ' . date('Y', $mes_ref_ts),
        'mes_referencia_ano'    => (int) date('Y', $mes_ref_ts),
        'mes_referencia_mes'    => (int) date('n', $mes_ref_ts),
        'valor'                 => $valor,
        'valor_fmt'             => number_format($valor, 2, ',', '.'),
        'observacao'            => $c['observacao'] ?: '',
    ];
}

krsort($anos_disponiveis);
$anos_disponiveis = array_keys($anos_disponiveis);

// ------------------------------------------------------------
// Meses pagos / meses em dívida (com base no mes_referencia real,
// não no mês do calendário em que o dinheiro entrou) - mesma regra
// de verificarStatusMes() usada nas Listas, mês a mês.
//
// Considera-se sempre o ano corrente (para mostrar a dívida em
// aberto até hoje) mais qualquer outro ano em que haja pagamentos,
// desde que não seja anterior ao início das cotas (as dívidas
// reiniciam a cada ano).
// ------------------------------------------------------------
$ano_atual_status = (int) date('Y');
$mes_atual_status = (int) date('n');

$anos_para_status = $anos_disponiveis;
if (!in_array($ano_atual_status, $anos_para_status)) {
    $anos_para_status[] = $ano_atual_status;
}
$anos_para_status = array_values(array_unique(array_filter(
    $anos_para_status,
    function ($a) { return $a >= ANO_INICIO_COTAS; }
)));
rsort($anos_para_status);

$meses_status = [];
foreach ($anos_para_status as $ano_s) {
    $mes_limite = ($ano_s === $ano_atual_status) ? $mes_atual_status : 12;
    $meses_ano = [];
    for ($m = 1; $m <= $mes_limite; $m++) {
        $status = verificarStatusMes($membro_id, $m, $ano_s);
        // Valor pago especificamente NESTE mês de referência (não o
        // acumulado desde Janeiro que verificarStatusMes() devolve).
        $total_mes = $total_por_mes_referencia["$ano_s-$m"] ?? 0;
        $meses_ano[] = [
            'mes'       => $m,
            'ano'       => $ano_s,
            'mes_nome'  => nomeMesPT($m),
            'quitado'   => $status['quitado'],
            'total'     => $total_mes,
            'total_fmt' => number_format($total_mes, 2, ',', '.'),
            'falta'     => max(0, 1000 - $total_mes),
        ];
    }
    $meses_status[] = [
        'ano'           => $ano_s,
        'meses'         => $meses_ano,
        'qtd_pagos'     => count(array_filter($meses_ano, function ($x) { return $x['quitado']; })),
        'qtd_em_divida' => count(array_filter($meses_ano, function ($x) { return !$x['quitado']; })),
    ];
}

// ------------------------------------------------------------
// Contribuição de fim de ano (informação complementar)
// ------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT id, ano, valor, data_pagamento
    FROM contribuicao_fim_ano
    WHERE membro_id = :membro_id
    ORDER BY ano DESC
");
$stmt->execute([':membro_id' => $membro_id]);
$contribuicoes_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_contribuicoes = 0;
$contribuicoes = [];
foreach ($contribuicoes_raw as $c) {
    $valor = (float) $c['valor'];
    $total_contribuicoes += $valor;
    $contribuicoes[] = [
        'ano'                => (int) $c['ano'],
        'valor'              => $valor,
        'valor_fmt'          => number_format($valor, 2, ',', '.'),
        'data_pagamento_fmt' => date('d/m/Y', strtotime($c['data_pagamento'])),
    ];
}

echo json_encode([
    'success' => true,
    'membro' => [
        'id'        => (int) $membro['id'],
        'nome'      => $membro['nome_completo'],
        'categoria' => $membro['categoria'],
    ],
    'pagamentos'         => $pagamentos,
    'contribuicoes'      => $contribuicoes,
    'anos_disponiveis'   => $anos_disponiveis,
    'meses_status'       => $meses_status,
    'totais' => [
        'total_cotas'          => $total_cotas,
        'total_cotas_fmt'      => number_format($total_cotas, 2, ',', '.'),
        'quantidade_cotas'     => count($pagamentos),
        'total_contribuicoes'     => $total_contribuicoes,
        'total_contribuicoes_fmt' => number_format($total_contribuicoes, 2, ',', '.'),
        'total_geral'          => $total_cotas + $total_contribuicoes,
        'total_geral_fmt'      => number_format($total_cotas + $total_contribuicoes, 2, ',', '.'),
    ],
], JSON_UNESCAPED_UNICODE);
exit;
?>