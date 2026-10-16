<?php
// controllers/ListsController.php
// Controlador do modulo "lists" - arquitectura MVC.
// Logica original dos antigos modules/lists/*.php preservada,
// agora separada em Model (dados) / Controller (fluxo) / View (HTML).

require_once BASE_PATH . '/models/ListsModel.php';
require_once BASE_PATH . '/models/AuditModel.php';
use Dompdf\Dompdf;
use Dompdf\Options;

class ListsController extends Controller
{
    protected string $modulo = 'lists';

    /** Antes: modules/lists/functions.php */
    public function functions(): void
    {
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
    }

    /** Antes: modules/lists/generate-pdf.php */
    public function generatePdf(): void
    {
    require_once __DIR__ . '/../../includes/pdf-theme.php';
    // modules/lists/generate-pdf.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('lists');

    // Verificar se o DOMPDF está instalado
    }


    use Dompdf\Dompdf;
    use Dompdf\Options;

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $categoria = $_GET['categoria'] ?? 'todos';
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
    $ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));

    // Validar mês/ano
    if ($mes < 1 || $mes > 12) $mes = date('m');
    if ($ano < 2000 || $ano > 2100) $ano = date('Y');

    // ============================================
    // BUSCAR DOMINGOS DO MÊS
    // ============================================
    $domingos = getDomingosDoMes($mes, $ano);

    // ============================================
    // BUSCAR MEMBROS (TODOS OU POR CATEGORIA)
    // ============================================
    $categoria_cores = [
        'mama'    => ['label' => 'Mamã',    'color' => '#b5527a'],
        'papa'    => ['label' => 'Papá',    'color' => '#3f7d4e'],
        'jovem'   => ['label' => 'Jovem',   'color' => '#7a5ca8'],
        'crianca' => ['label' => 'Criança', 'color' => '#c99a2e']
    ];

    if ($categoria === 'todos') {
        $stmt = $conn->prepare("
            SELECT id, nome_completo, telefone, categoria
            FROM membros
            WHERE ativo = 1
            ORDER BY
                CASE categoria
                    WHEN 'mama'    THEN 1
                    WHEN 'papa'    THEN 2
                    WHEN 'jovem'   THEN 3
                    WHEN 'crianca' THEN 4
                END,
                nome_completo ASC
        ");
        $stmt->execute();
        $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $titulo = "LISTA DE TODOS OS MEMBROS";
        $mostrar_categoria = true;
    } else {
        // Validar categoria
        if (!in_array($categoria, ['mama', 'papa', 'jovem'])) {
            die('Categoria inválida');
        }

        $stmt = $conn->prepare("
            SELECT id, nome_completo, telefone
            FROM membros
            WHERE categoria = :categoria AND ativo = 1
            ORDER BY nome_completo ASC
        ");
        $stmt->execute([':categoria' => $categoria]);
        $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cat_info = getCategoriaLabel($categoria);
        $titulo = "LISTA DE " . strtoupper($cat_info['label']);
        $mostrar_categoria = false;
    }

    // ============================================
    // CALCULAR PAGAMENTOS E TOTAIS
    // ============================================
    $total_geral = 0;
    $membros_pagantes = 0;
    $total_membros = count($membros);

    foreach ($membros as &$membro) {
        $pagamentos = getPagamentosMembro($membro['id'], $mes, $ano);
        $membro['pagamentos'] = $pagamentos;

        $total_membro = 0;
        foreach ($pagamentos as $p) {
            $total_membro += $p['valor'];
        }
        $membro['total'] = $total_membro;

        if ($total_membro > 0) {
            $total_geral += $total_membro;
        }

        // Status baseado no mes_referencia (não no calendário) - ver
        // modules/lists/index.php para a mesma lógica usada na versão web.
        $status_mes = verificarStatusMes($membro['id'], $mes, $ano);
        $membro['quitado'] = $status_mes['quitado'];
        if ($membro['quitado']) {
            $membros_pagantes++;
        }
    }
    unset($membro);

    // ============================================
    // RESPONSÁVEL DA COTA
    // ============================================
    // Esta lista cobre o mês inteiro (vários dias/registos), por isso o
    // responsável que assina é quem tem sessão activa e está a gerar o
    // documento naquele momento.
    $responsavel_nome = $_SESSION['user_nome'] ?? '-';

    // ============================================
    // MONTAR HTML PARA O PDF
    // ============================================
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: "DejaVu Sans", sans-serif;
                font-size: 10px;
                padding: 20px;
            }
            .header {
                text-align: center;
                border-bottom: 2px solid #3a271a;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .header h1 {
                color: #3a271a;
                margin: 0;
                font-size: 18px;
            }
            .header h3 {
                color: #c99a2e;
                margin: 5px 0;
                font-size: 14px;
            }
            .header p {
                color: #7d6a5b;
                font-size: 11px;
                margin: 2px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 9px;
            }
            th {
                background: #3a271a;
                color: white;
                padding: 5px 6px;
                text-align: center;
                font-weight: bold;
            }
            td {
                padding: 4px 6px;
                border: 1px solid #ece4d2;
            }
            .text-center { text-align: center; }
            .text-right   { text-align: right; }
            .text-left    { text-align: left; }

            .pago {
                color: #3f7d4e;
                font-weight: bold;
            }
            .pendente {
                color: #b5412f;
                font-weight: bold;
            }

            .total-row {
                background: #fbf8f1;
                font-weight: bold;
                border-top: 2px solid #333;
            }

            .footer {
                text-align: center;
                margin-top: 30px;
                font-size: 9px;
                color: #7d6a5b;
                border-top: 1px solid #ece4d2;
                padding-top: 10px;
            }

            .assinatura {
                margin-top: 30px;
                display: flex;
                justify-content: center;
            }
            .assinatura div {
                text-align: center;
            }
            .assinatura .line {
                border-top: 1px solid #000;
                width: 200px;
                margin: 0 auto;
                padding-top: 5px;
            }

            .badge-categoria {
                display: inline-block;
                padding: 1px 8px;
                border-radius: 12px;
                color: #fff;
                font-weight: 600;
                font-size: 8px;
            }
        ' . pdfBrandCss() . '
        </style>
    </head>
    <body>

    <div class="header">
        ' . pdfCabecalho() . '
        <p class="doc-title">' . $titulo . ' - ' . nomeMesPT($mes) . '/' . $ano . '</p>
        <p>Data de emissão: ' . date('d/m/Y H:i') . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align:left;">Nome</th>';

    if ($mostrar_categoria) {
        $html .= '<th style="text-align:left;">Categoria</th>';
    }

    foreach ($domingos as $domingo) {
        $html .= '<th style="text-align:center;font-size:8px;">' . date('d/m', strtotime($domingo)) . '</th>';
    }

    $html .= '
                <th style="text-align:right;">Total (Kz)</th>
                <th style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($membros)) {
        $html .= '
            <tr>
                <td colspan="' . (count($domingos) + ($mostrar_categoria ? 4 : 3)) . '" style="text-align:center;padding:30px;color:#a39485;">
                    Nenhum membro encontrado.
                </td>
            </tr>';
    } else {
        foreach ($membros as $index => $membro) {
            $html .= '
            <tr>
                <td class="text-center">' . ($index + 1) . '</td>
                <td class="text-left" style="font-weight:bold;">' . htmlspecialchars($membro['nome_completo']) . '</td>';

            if ($mostrar_categoria) {
                $cat = $categoria_cores[$membro['categoria']] ?? ['label' => $membro['categoria'], 'color' => '#gray-500'];
                $html .= '<td><span class="badge-categoria" style="background:' . $cat['color'] . ';">' . $cat['label'] . '</span></td>';
            }

            foreach ($domingos as $domingo) {
                $valor = getStatusPagamento($membro['pagamentos'], $domingo);
                if ($valor !== null) {
                    $html .= '<td class="text-center" style="color:#3f7d4e;font-weight:bold;">' . number_format($valor, 2, ',', '.') . '</td>';
                } else {
                    $html .= '<td class="text-center" style="color:#ccc;">—</td>';
                }
            }

            $html .= '
                <td class="text-right" style="font-weight:bold;">' . number_format($membro['total'], 2, ',', '.') . '</td>
                <td class="text-center">' . ($membro['quitado'] ? '<span class="pago">✓ Pago</span>' : '<span class="pendente">✗ Pendente</span>') . '</td>
            </tr>';
        }
    }

    // ============================================
    // RODAPÉ DA TABELA (TOTAIS)
    // ============================================
    $colspan = count($domingos) + ($mostrar_categoria ? 3 : 2);

    $html .= '
            <tr class="total-row">
                <td colspan="' . $colspan . '" class="text-right" style="font-size:11px;">TOTAL GERAL:</td>
                <td class="text-right" style="font-size:11px;color:#3f7d4e;">' . number_format($total_geral, 2, ',', '.') . ' Kz</td>
                <td class="text-center" style="font-size:10px;">' . $membros_pagantes . '/' . $total_membros . ' pagaram</td>
            </tr>
        </tbody>
    </table>';

    // ============================================
    // RESUMO ADICIONAL
    // ============================================
    $html .= '
    <div style="margin-top:15px;">
        <table style="width:auto;border:none;">
            <tr>
                <td style="border:none;font-weight:bold;">Resumo:</td>
                <td style="border:none;">Total de membros: ' . $total_membros . '</td>
                <td style="border:none;">Pagaram: ' . $membros_pagantes . '</td>
                <td style="border:none;">Não pagaram: ' . ($total_membros - $membros_pagantes) . '</td>
            </tr>
        </table>
    </div>';

    // ============================================
    // ASSINATURAS
    // ============================================
    $html .= '
    <div class="assinatura">
        <div>
            <div class="line">_________________</div>
            <p style="font-size:10px;margin-top:5px;">Assinatura do Responsável da Cota</p>
            <p style="font-size:10px;margin-top:2px;font-weight:bold;">' . htmlspecialchars($responsavel_nome) . '</p>
        </div>
    </div>

    <div class="footer">
        <p>MonanaEclésia &middot; Plataforma Integrada de Gestão de Igrejas &mdash; I.P.F.V.A Calemba 2 &nbsp;|&nbsp; João 14:6</p>
    </div>

    </body>
    </html>';

    // ============================================
    // GERAR PDF
    // ============================================
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);

    // Orientação: landscape (paisagem) para caber muitos domingos
    $dompdf->setPaper('A4', 'landscape');

    $dompdf->render();

    // Nome do arquivo
    $nome_arquivo = 'lista_';
    if ($categoria === 'todos') {
        $nome_arquivo .= 'todos';
    } else {
        $nome_arquivo .= $categoria;
    }
    $nome_arquivo .= '_' . $mes . '_' . $ano . '.pdf';

    // Forçar download ou exibir no navegador
    $dompdf->stream($nome_arquivo, array("Attachment" => false));
    exit;
    }

    /** Antes: modules/lists/index.php */
    public function index(): void
    {
    // modules/lists/index.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('lists');
    $page_title = 'Lista de Todos os Membros - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    // ============================================
    // PARÂMETROS DE FILTRO
    // ============================================
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
    $ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));

    // Validar
    if ($mes < 1 || $mes > 12) $mes = date('m');

    // ============================================
    // BUSCAR DOMINGOS DO MÊS
    // ============================================
    $domingos = getDomingosDoMes($mes, $ano);

    // ============================================
    // BUSCAR TODOS OS MEMBROS ATIVOS (TODAS CATEGORIAS)
    // ============================================
    $stmt = $conn->prepare("
        SELECT id, nome_completo, telefone, categoria
        FROM membros
        WHERE ativo = 1
        ORDER BY
            CASE categoria
                WHEN 'mama'    THEN 1
                WHEN 'papa'    THEN 2
                WHEN 'jovem'   THEN 3
                WHEN 'crianca' THEN 4
            END,
            nome_completo ASC
    ");
    $stmt->execute();
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // CALCULAR PAGAMENTOS E TOTAIS
    // ============================================
    $categoria_cores = [
        'mama'    => ['label' => 'Mamã',    'color' => '#b5527a'],
        'papa'    => ['label' => 'Papá',    'color' => '#3f7d4e'],
        'jovem'   => ['label' => 'Jovem',   'color' => '#7a5ca8'],
        'crianca' => ['label' => 'Criança', 'color' => '#c99a2e']
    ];

    $total_membros = count($membros);
    $membros_pagantes = 0;
    $total_arrecadado = 0;

    foreach ($membros as &$membro) {
        $pagamentos = getPagamentosMembro($membro['id'], $mes, $ano);
        $membro['pagamentos'] = $pagamentos;

        $total_membro = 0;
        foreach ($pagamentos as $p) {
            $total_membro += $p['valor'];
        }
        $membro['total'] = $total_membro;

        if ($total_membro > 0) {
            $membros_pagantes++;
            $total_arrecadado += $total_membro;
        }

        // Status do mês baseado no mes_referencia (não no calendário): um
        // pagamento recebido este mês mas usado para saldar um mês anterior
        // em dívida NÃO quita este mês - continua pendente aqui.
        $status_mes = verificarStatusMes($membro['id'], $mes, $ano);
        $membro['quitado'] = $status_mes['quitado'];

        // Se este mês não tem valor pago mas já foi quitado por um pagamento
        // feito noutro mês (ex: dívida de Janeiro paga em Agosto), guarda essa
        // informação para mostrar o selo "Quitado (pago em ...)".
        $membro['quitacao_posterior'] = null;
        if ($membro['quitado'] && $total_membro == 0) {
            $pagamentos_quitacao = getPagamentoQuitacaoMes($membro['id'], $mes, $ano);
            if (!empty($pagamentos_quitacao)) {
                $membro['quitacao_posterior'] = $pagamentos_quitacao[0];
            }
        }
    }
    unset($membro);

    // ============================================
    // NOME DO MÊS PARA EXIBIÇÃO
    // ============================================
    $mes_exibicao = nomeMesPT($mes);
    }

    /** Antes: modules/lists/jovens.php */
    public function jovens(): void
    {
    // modules/lists/jovens.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('lists');
    $page_title = 'Lista de Jovens - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
    $ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));
    $categoria = 'jovem';

    if ($mes < 1 || $mes > 12) $mes = date('m');

    // ============================================
    // BUSCAR DOMINGOS DO MÊS
    // ============================================
    $domingos = getDomingosDoMes($mes, $ano);

    // ============================================
    // BUSCAR MEMBROS DA CATEGORIA
    // ============================================
    $stmt = $conn->prepare("
        SELECT id, nome_completo, telefone, categoria
        FROM membros
        WHERE categoria = :categoria AND ativo = 1
        ORDER BY nome_completo ASC
    ");
    $stmt->execute([':categoria' => $categoria]);
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // CALCULAR DADOS DE CADA MEMBRO
    // ============================================
    $categoria_cores = [
        'mama'  => ['label' => 'Mamã', 'color' => '#b5527a'],
        'papa'  => ['label' => 'Papá', 'color' => '#3f7d4e'],
        'jovem' => ['label' => 'Jovem', 'color' => '#7a5ca8']
    ];

    $total_membros = count($membros);
    $total_saldo_geral = 0;
    $total_quitados = 0;
    $total_devendo = 0;

    foreach ($membros as &$membro) {
        // Pagamentos do mês (para mostrar nos domingos)
        $pagamentos = getPagamentosMembro($membro['id'], $mes, $ano);
        $membro['pagamentos'] = $pagamentos;

        // Total pago no mês
        $total_mes = 0;
        foreach ($pagamentos as $p) {
            $total_mes += $p['valor'];
        }
        $membro['total_mes'] = $total_mes;

        // Se este mês não tem valor em nenhum domingo mas já foi quitado por
        // um pagamento feito noutro mês (ex: dívida de Janeiro paga em Agosto),
        // guarda essa informação para mostrar o selo "Quitado (pago em ...)"
        $membro['quitacao_posterior'] = null;
        if ($total_mes == 0) {
            $pagamentos_quitacao = getPagamentoQuitacaoMes($membro['id'], $mes, $ano);
            if (!empty($pagamentos_quitacao)) {
                $membro['quitacao_posterior'] = $pagamentos_quitacao[0];
            }
        }

        // Saldo acumulado
        $saldo_info = calcularSaldoMembro($membro['id'], $mes, $ano);
        $membro['saldo'] = $saldo_info['saldo'];
        $membro['total_pago'] = $saldo_info['total_pago'];
        $membro['deveria_pagar'] = $saldo_info['deveria_pagar'];
        $membro['meses_quitados'] = $saldo_info['meses_quitados'];
        $membro['meses_devendo'] = $saldo_info['meses_devendo'];

        $total_saldo_geral += $saldo_info['saldo'];
        if ($saldo_info['saldo'] >= 0) {
            $total_quitados++;
        } else {
            $total_devendo++;
        }
    }
    unset($membro);

    // ============================================
    // NOME DO MÊS
    // ============================================
    $mes_exibicao = nomeMesPT($mes);
    $cat_info = getCategoriaLabel($categoria);
    }

    /** Antes: modules/lists/mamas.php */
    public function mamas(): void
    {
    // modules/lists/mamas.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('lists');
    $page_title = 'Lista de Mamãs - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
    $ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));
    $categoria = 'mama';

    if ($mes < 1 || $mes > 12) $mes = date('m');

    // ============================================
    // BUSCAR DOMINGOS DO MÊS
    // ============================================
    $domingos = getDomingosDoMes($mes, $ano);

    // ============================================
    // BUSCAR MEMBROS DA CATEGORIA
    // ============================================
    $stmt = $conn->prepare("
        SELECT id, nome_completo, telefone, categoria
        FROM membros
        WHERE categoria = :categoria AND ativo = 1
        ORDER BY nome_completo ASC
    ");
    $stmt->execute([':categoria' => $categoria]);
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // CALCULAR DADOS DE CADA MEMBRO
    // ============================================
    $categoria_cores = [
        'mama'  => ['label' => 'Mamã', 'color' => '#b5527a'],
        'papa'  => ['label' => 'Papá', 'color' => '#3f7d4e'],
        'jovem' => ['label' => 'Jovem', 'color' => '#7a5ca8']
    ];

    $total_membros = count($membros);
    $total_saldo_geral = 0;
    $total_quitados = 0;
    $total_devendo = 0;

    foreach ($membros as &$membro) {
        // Pagamentos do mês (para mostrar nos domingos)
        $pagamentos = getPagamentosMembro($membro['id'], $mes, $ano);
        $membro['pagamentos'] = $pagamentos;

        // Total pago no mês
        $total_mes = 0;
        foreach ($pagamentos as $p) {
            $total_mes += $p['valor'];
        }
        $membro['total_mes'] = $total_mes;

        // Se este mês não tem valor em nenhum domingo mas já foi quitado por
        // um pagamento feito noutro mês (ex: dívida de Janeiro paga em Agosto),
        // guarda essa informação para mostrar o selo "Quitado (pago em ...)"
        $membro['quitacao_posterior'] = null;
        if ($total_mes == 0) {
            $pagamentos_quitacao = getPagamentoQuitacaoMes($membro['id'], $mes, $ano);
            if (!empty($pagamentos_quitacao)) {
                $membro['quitacao_posterior'] = $pagamentos_quitacao[0];
            }
        }

        // Saldo acumulado
        $saldo_info = calcularSaldoMembro($membro['id'], $mes, $ano);
        $membro['saldo'] = $saldo_info['saldo'];
        $membro['total_pago'] = $saldo_info['total_pago'];
        $membro['deveria_pagar'] = $saldo_info['deveria_pagar'];
        $membro['meses_quitados'] = $saldo_info['meses_quitados'];
        $membro['meses_devendo'] = $saldo_info['meses_devendo'];

        $total_saldo_geral += $saldo_info['saldo'];
        if ($saldo_info['saldo'] >= 0) {
            $total_quitados++;
        } else {
            $total_devendo++;
        }
    }
    unset($membro);

    // ============================================
    // NOME DO MÊS
    // ============================================
    $mes_exibicao = nomeMesPT($mes);
    $cat_info = getCategoriaLabel($categoria);
    }

    /** Antes: modules/lists/papas.php */
    public function papas(): void
    {
    // modules/lists/papas.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('lists');
    $page_title = 'Lista de Papás - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
    $ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));
    $categoria = 'papa';

    if ($mes < 1 || $mes > 12) $mes = date('m');

    // ============================================
    // BUSCAR DOMINGOS DO MÊS
    // ============================================
    $domingos = getDomingosDoMes($mes, $ano);

    // ============================================
    // BUSCAR MEMBROS DA CATEGORIA
    // ============================================
    $stmt = $conn->prepare("
        SELECT id, nome_completo, telefone, categoria
        FROM membros
        WHERE categoria = :categoria AND ativo = 1
        ORDER BY nome_completo ASC
    ");
    $stmt->execute([':categoria' => $categoria]);
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // CALCULAR DADOS DE CADA MEMBRO
    // ============================================
    $categoria_cores = [
        'mama'  => ['label' => 'Mamã', 'color' => '#b5527a'],
        'papa'  => ['label' => 'Papá', 'color' => '#3f7d4e'],
        'jovem' => ['label' => 'Jovem', 'color' => '#7a5ca8']
    ];

    $total_membros = count($membros);
    $total_saldo_geral = 0;
    $total_quitados = 0;
    $total_devendo = 0;

    foreach ($membros as &$membro) {
        // Pagamentos do mês (para mostrar nos domingos)
        $pagamentos = getPagamentosMembro($membro['id'], $mes, $ano);
        $membro['pagamentos'] = $pagamentos;

        // Total pago no mês
        $total_mes = 0;
        foreach ($pagamentos as $p) {
            $total_mes += $p['valor'];
        }
        $membro['total_mes'] = $total_mes;

        // Se este mês não tem valor em nenhum domingo mas já foi quitado por
        // um pagamento feito noutro mês (ex: dívida de Janeiro paga em Agosto),
        // guarda essa informação para mostrar o selo "Quitado (pago em ...)"
        $membro['quitacao_posterior'] = null;
        if ($total_mes == 0) {
            $pagamentos_quitacao = getPagamentoQuitacaoMes($membro['id'], $mes, $ano);
            if (!empty($pagamentos_quitacao)) {
                $membro['quitacao_posterior'] = $pagamentos_quitacao[0];
            }
        }

        // Saldo acumulado
        $saldo_info = calcularSaldoMembro($membro['id'], $mes, $ano);
        $membro['saldo'] = $saldo_info['saldo'];
        $membro['total_pago'] = $saldo_info['total_pago'];
        $membro['deveria_pagar'] = $saldo_info['deveria_pagar'];
        $membro['meses_quitados'] = $saldo_info['meses_quitados'];
        $membro['meses_devendo'] = $saldo_info['meses_devendo'];

        $total_saldo_geral += $saldo_info['saldo'];
        if ($saldo_info['saldo'] >= 0) {
            $total_quitados++;
        } else {
            $total_devendo++;
        }
    }
    unset($membro);

    // ============================================
    // NOME DO MÊS
    // ============================================
    $mes_exibicao = nomeMesPT($mes);
    $cat_info = getCategoriaLabel($categoria);
    }

}
