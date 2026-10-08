<?php
// controllers/ReportsController.php
// Controlador do modulo "reports" - arquitectura MVC.
// Logica original dos antigos modules/reports/*.php preservada,
// agora separada em Model (dados) / Controller (fluxo) / View (HTML).

require_once BASE_PATH . '/models/ReportsModel.php';
require_once BASE_PATH . '/models/AuditModel.php';
use Dompdf\Dompdf;
use Dompdf\Options;

class ReportsController extends Controller
{
    protected string $modulo = 'reports';

    /** Antes: modules/reports/annual.php */
    public function annual(): void
    {
    // modules/reports/annual.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('reports');
    $page_title = 'Relatório Anual - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));

    $fundos_disponiveis = getFundosRelatorio();
    $fundo = isset($_GET['fundo']) ? $_GET['fundo'] : 'cotas';
    if (!array_key_exists($fundo, $fundos_disponiveis)) $fundo = 'cotas';

    // ============================================
    // DADOS DO RELATÓRIO ANUAL
    // ============================================
    $resumo_anual = getResumoAnual($ano, $fundo);
    $entradas_mensais = getEntradasMensais($ano, $fundo);
    $saidas_mensais = getSaidasMensais($ano, $fundo);

    $total_entradas = 0;
    $total_saidas = 0;
    $meses_com_movimento = 0;

    for ($mes = 1; $mes <= 12; $mes++) {
        if ($entradas_mensais[$mes] > 0 || $saidas_mensais[$mes] > 0) {
            $meses_com_movimento++;
        }
        $total_entradas += $entradas_mensais[$mes];
        $total_saidas += $saidas_mensais[$mes];
    }

    // Dados para gráficos
    $labels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    $entradas_dados = array_values($entradas_mensais);
    $saidas_dados = array_values($saidas_mensais);
    $saldos = [];
    for ($i = 0; $i < 12; $i++) {
        $saldos[] = $entradas_dados[$i] - $saidas_dados[$i];
    }
    }

    /** Antes: modules/reports/expenses.php */
    public function expenses(): void
    {
    // modules/reports/expenses.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('reports');
    $page_title = 'Relatório de Saídas - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : 0;
    $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';

    // O relatório de saídas nunca deve somar Cotas e Contribuições às cegas
    // num único total - por isso não existe opção "Todos" para o fundo,
    // apenas os dois fundos reais (mesmo critério do relatório financeiro).
    $fundos_disponiveis = getFundosRelatorio();
    $fundo = isset($_GET['fundo']) ? $_GET['fundo'] : 'cotas';
    if (!array_key_exists($fundo, $fundos_disponiveis)) $fundo = 'cotas';

    if ($ano < 2026 || $ano > 2100) $ano = date('Y');

    // ============================================
    // FILTROS
    // ============================================
    $where = "YEAR(s.data_saida) = :ano AND s.origem_fundo = :fundo";
    $params = [':ano' => $ano, ':fundo' => $fundo];

    if ($mes > 0) {
        $where .= " AND MONTH(s.data_saida) = :mes";
        $params[':mes'] = $mes;
    }

    if (!empty($categoria)) {
        $where .= " AND s.categoria = :categoria";
        $params[':categoria'] = $categoria;
    }

    // ============================================
    // BUSCAR SAÍDAS
    // ============================================
    $sql = "SELECT 
                s.*,
                u.nome_completo as registrado_nome
            FROM saidas s
            LEFT JOIN usuarios u ON s.registrado_por = u.id
            WHERE $where
            ORDER BY s.data_saida DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $saidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // ESTATÍSTICAS
    // ============================================
    $total_saidas = 0;
    $categorias_saidas = [];

    foreach ($saidas as $s) {
        $total_saidas += $s['valor'];
        $cat = $s['categoria'] ?? 'Outros';
        if (!isset($categorias_saidas[$cat])) {
            $categorias_saidas[$cat] = 0;
        }
        $categorias_saidas[$cat] += $s['valor'];
    }

    // Buscar categorias para o filtro (apenas se houver saídas)
    $categorias_list = [];
    try {
        $stmt_cat = $conn->prepare("SELECT DISTINCT categoria FROM saidas WHERE origem_fundo = :fundo ORDER BY categoria");
        $stmt_cat->execute([':fundo' => $fundo]);
        $categorias_list = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $categorias_list = [];
    }
    }

    /** Antes: modules/reports/export-excel.php */
    public function exportExcel(): void
    {
    // modules/reports/export-excel.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('reports');

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));
    $mes_inicio = isset($_GET['mes_inicio']) ? intval($_GET['mes_inicio']) : 1;
    $mes_fim = isset($_GET['mes_fim']) ? intval($_GET['mes_fim']) : 12;

    $fundos_disponiveis = getFundosRelatorio();
    $fundo = isset($_GET['fundo']) ? $_GET['fundo'] : 'cotas';
    if (!array_key_exists($fundo, $fundos_disponiveis)) $fundo = 'cotas';

    // ============================================
    // DADOS
    // ============================================
    $resumo_anual = getResumoAnual($ano, $fundo);

    // Cabeçalho do Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="relatorio_financeiro_' . $ano . '_' . $fundo . '.xls"');

    echo '<table border="1">';
    echo '<tr>
            <th colspan="5" style="font-size:16px;font-weight:bold;text-align:center;">IGREJA PENTECOSTAL FONTE DA VIDA EM ANGOLA - I.P.F.V.A CALEMBA 2</th>
          </tr>';
    echo '<tr>
            <th colspan="5" style="text-align:center;">RELATÓRIO FINANCEIRO - ' . $ano . ' - Fundo: ' . $fundos_disponiveis[$fundo] . '</th>
          </tr>';
    echo '<tr><td colspan="5"></td></tr>';
    echo '<tr>
            <th style="font-weight:bold;">Mês</th>
            <th style="font-weight:bold;">Entradas (Kz)</th>
            <th style="font-weight:bold;">Saídas (Kz)</th>
            <th style="font-weight:bold;">Saldo (Kz)</th>
            <th style="font-weight:bold;">Status</th>
          </tr>';

    $total_entradas = 0;
    $total_saidas = 0;

    for ($mes = $mes_inicio; $mes <= $mes_fim; $mes++) {
        $entradas = $resumo_anual[$mes]['entradas'] ?? 0;
        $saidas = $resumo_anual[$mes]['saidas'] ?? 0;
        $saldo = $entradas - $saidas;
        $total_entradas += $entradas;
        $total_saidas += $saidas;

        echo '<tr>
                <td>' . getNomeMes($mes) . '</td>
                <td>' . number_format($entradas, 2, ',', '.') . '</td>
                <td>' . number_format($saidas, 2, ',', '.') . '</td>
                <td>' . number_format($saldo, 2, ',', '.') . '</td>
                <td>' . ($saldo >= 0 ? 'Positivo' : 'Negativo') . '</td>
              </tr>';
    }

    echo '<tr style="font-weight:bold;background-color:#f0f0f0;">
            <td>TOTAL GERAL</td>
            <td>' . number_format($total_entradas, 2, ',', '.') . '</td>
            <td>' . number_format($total_saidas, 2, ',', '.') . '</td>
            <td>' . number_format($total_entradas - $total_saidas, 2, ',', '.') . '</td>
            <td>' . (($total_entradas - $total_saidas) >= 0 ? 'SUPERÁVIT' : 'DÉFICIT') . '</td>
          </tr>';

    echo '</table>';
    exit;
    ?>
    }

    /** Antes: modules/reports/functions.php */
    public function functions(): void
    {
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
    }

    /** Antes: modules/reports/generate-expenses-pdf.php */
    public function generateExpensesPdf(): void
    {
    require_once __DIR__ . '/../../includes/pdf-theme.php';
    // modules/reports/generate-expenses-pdf.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('reports');

    // Verificar se o DOMPDF está instalado
    }


    use Dompdf\Dompdf;
    use Dompdf\Options;

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : 0;
    $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';

    $fundos_disponiveis = getFundosRelatorio();
    $fundo = isset($_GET['fundo']) ? $_GET['fundo'] : 'cotas';
    if (!array_key_exists($fundo, $fundos_disponiveis)) $fundo = 'cotas';

    // ============================================
    // FILTROS
    // ============================================
    $where = "YEAR(s.data_saida) = :ano AND s.origem_fundo = :fundo";
    $params = [':ano' => $ano, ':fundo' => $fundo];

    if ($mes > 0) {
        $where .= " AND MONTH(s.data_saida) = :mes";
        $params[':mes'] = $mes;
    }

    if (!empty($categoria)) {
        $where .= " AND s.categoria = :categoria";
        $params[':categoria'] = $categoria;
    }

    // ============================================
    // BUSCAR SAÍDAS
    // ============================================
    $sql = "SELECT 
                s.*,
                u.nome_completo as registrado_nome
            FROM saidas s
            LEFT JOIN usuarios u ON s.registrado_por = u.id
            WHERE $where
            ORDER BY s.data_saida DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $saidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_saidas = 0;
    foreach ($saidas as $s) {
        $total_saidas += $s['valor'];
    }

    // Responsável que assina: se todas as saídas do período foram
    // registadas pela mesma pessoa, usa o nome dela; senão, quem gerou o PDF.
    $nomes_registrantes = array_unique(array_filter(array_column($saidas, 'registrado_nome')));
    $responsavel_nome = count($nomes_registrantes) === 1 ? reset($nomes_registrantes) : ($_SESSION['user_nome'] ?? '-');

    // ============================================
    // HTML PARA PDF
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
            .text-right { text-align: right; }
            .text-left { text-align: left; }
            .negativo { color: #b5412f; font-weight: bold; }
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
        ' . pdfBrandCss() . '
        </style>
    </head>
    <body>';

    if (empty($saidas)) {
        $html .= '
        <div class="header">
            ' . pdfCabecalho() . '
        <p class="doc-title">RELATÓRIO DE SAÍDAS</p>
            <p>Fundo: ' . $fundos_disponiveis[$fundo] . '</p>
            <p>Período: ' . $ano . ($mes > 0 ? ' - ' . getNomeMes($mes) : ' - Ano Completo') . '</p>
            <p>Data de emissão: ' . date('d/m/Y H:i') . '</p>
        </div>
        <div style="text-align:center;padding:50px;color:#a39485;font-size:14px;">
            <p>Nenhuma saída encontrada para o período selecionado.</p>
        </div>';
    } else {
        $html .= '
        <div class="header">
            ' . pdfCabecalho() . '
        <p class="doc-title">RELATÓRIO DE SAÍDAS</p>
            <p>Fundo: ' . $fundos_disponiveis[$fundo] . '</p>
            <p>Período: ' . $ano . ($mes > 0 ? ' - ' . getNomeMes($mes) : ' - Ano Completo') . '</p>
            <p>Data de emissão: ' . date('d/m/Y H:i') . '</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="text-align:left;">#</th>
                    <th style="text-align:left;">Data</th>
                    <th style="text-align:left;">Descrição</th>
                    <th style="text-align:left;">Categoria</th>
                    <th style="text-align:left;">Beneficiário</th>
                    <th style="text-align:right;">Valor (Kz)</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($saidas as $index => $s) {
            $html .= '
                <tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . date('d/m/Y', strtotime($s['data_saida'])) . '</td>
                    <td>' . htmlspecialchars($s['descricao']) . '</td>
                    <td>' . htmlspecialchars($s['categoria'] ?? 'Outros') . '</td>
                    <td>' . htmlspecialchars($s['beneficiario'] ?? '-') . '</td>
                    <td class="text-right" style="color:#b5412f;font-weight:bold;">' . number_format($s['valor'], 2, ',', '.') . '</td>
                </tr>';
        }

        $html .= '
                <tr class="total-row">
                    <td colspan="5" class="text-right" style="font-size:11px;">TOTAL GERAL:</td>
                    <td class="text-right" style="font-size:11px;color:#b5412f;font-weight:bold;">' . number_format($total_saidas, 2, ',', '.') . ' Kz</td>
                </tr>
            </tbody>
        </table>';
    }

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
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $nome_arquivo = 'relatorio_saidas_' . $ano . '.pdf';
    $dompdf->stream($nome_arquivo, array("Attachment" => false));
    exit;
    }

    /** Antes: modules/reports/generate-pdf.php */
    public function generatePdf(): void
    {
    require_once __DIR__ . '/../../includes/pdf-theme.php';
    // modules/reports/generate-pdf.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('reports');

    // Verificar se o DOMPDF está instalado
    }


    use Dompdf\Dompdf;
    use Dompdf\Options;

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));
    $mes_inicio = isset($_GET['mes_inicio']) ? intval($_GET['mes_inicio']) : 1;
    $mes_fim = isset($_GET['mes_fim']) ? intval($_GET['mes_fim']) : 12;

    $fundos_disponiveis = getFundosRelatorio();
    $fundo = isset($_GET['fundo']) ? $_GET['fundo'] : 'cotas';
    if (!array_key_exists($fundo, $fundos_disponiveis)) $fundo = 'cotas';

    // ============================================
    // DADOS DO RELATÓRIO
    // ============================================
    $resumo_anual = getResumoAnual($ano, $fundo);
    $total_entradas_periodo = 0;
    $total_saidas_periodo = 0;
    $dados_mensais = [];

    for ($mes = $mes_inicio; $mes <= $mes_fim; $mes++) {
        $entradas = $resumo_anual[$mes]['entradas'] ?? 0;
        $saidas = $resumo_anual[$mes]['saidas'] ?? 0;
        $total_entradas_periodo += $entradas;
        $total_saidas_periodo += $saidas;
        $dados_mensais[] = [
            'mes' => $mes,
            'nome_mes' => getNomeMes($mes),
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo' => $entradas - $saidas
        ];
    }

    // ============================================
    // HTML PARA PDF
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
            .text-right { text-align: right; }
            .text-left { text-align: left; }
            .positivo { color: #3f7d4e; font-weight: bold; }
            .negativo { color: #b5412f; font-weight: bold; }
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
        ' . pdfBrandCss() . '
        </style>
    </head>
    <body>

    <div class="header">
        ' . pdfCabecalho() . '
        <p class="doc-title">RELATÓRIO FINANCEIRO - ' . $ano . '</p>
        <p>Período: ' . getNomeMes($mes_inicio) . ' a ' . getNomeMes($mes_fim) . ' &nbsp;|&nbsp; Fundo: ' . $fundos_disponiveis[$fundo] . '</p>
        <p>Data de emissão: ' . date('d/m/Y H:i') . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="text-align:left;">Mês</th>
                <th style="text-align:right;">Entradas (Kz)</th>
                <th style="text-align:right;">Saídas (Kz)</th>
                <th style="text-align:right;">Saldo (Kz)</th>
                <th style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>';

    $total_entradas_mostrar = 0;
    $total_saidas_mostrar = 0;

    if (empty($dados_mensais)) {
        $html .= '
            <tr>
                <td colspan="5" style="text-align:center;padding:30px;color:#a39485;">
                    Nenhum dado encontrado para o período selecionado.
                </td>
            </tr>';
    } else {
        foreach ($dados_mensais as $dado):
            $total_entradas_mostrar += $dado['entradas'];
            $total_saidas_mostrar += $dado['saidas'];
            $saldo = $dado['saldo'];
            $cor = $saldo >= 0 ? '#3f7d4e' : '#b5412f';
            $status = $saldo >= 0 ? 'Positivo' : 'Negativo';

            $html .= '
            <tr>
                <td style="font-weight:bold;">' . $dado['nome_mes'] . '</td>
                <td class="text-right" style="color:#3f7d4e;">' . number_format($dado['entradas'], 2, ',', '.') . '</td>
                <td class="text-right" style="color:#b5412f;">' . number_format($dado['saidas'], 2, ',', '.') . '</td>
                <td class="text-right" style="font-weight:bold;color:' . $cor . ';">' . number_format($saldo, 2, ',', '.') . '</td>
                <td class="text-center">' . $status . '</td>
            </tr>';
        endforeach;
    }

    $html .= '
            <tr class="total-row">
                <td style="font-size:11px;">TOTAL GERAL</td>
                <td class="text-right" style="font-size:11px;color:#3f7d4e;">' . number_format($total_entradas_mostrar, 2, ',', '.') . '</td>
                <td class="text-right" style="font-size:11px;color:#b5412f;">' . number_format($total_saidas_mostrar, 2, ',', '.') . '</td>
                <td class="text-right" style="font-size:12px;color:' . (($total_entradas_mostrar - $total_saidas_mostrar) >= 0 ? '#3f7d4e' : '#b5412f') . ';">' . number_format($total_entradas_mostrar - $total_saidas_mostrar, 2, ',', '.') . '</td>
                <td class="text-center" style="font-size:10px;">' . (($total_entradas_mostrar - $total_saidas_mostrar) >= 0 ? 'SUPERÁVIT' : 'DÉFICIT') . '</td>
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
                <td style="border:none;">Total de Entradas: ' . number_format($total_entradas_mostrar, 2, ',', '.') . ' Kz</td>
                <td style="border:none;">Total de Saídas: ' . number_format($total_saidas_mostrar, 2, ',', '.') . ' Kz</td>
                <td style="border:none;">Saldo: ' . number_format($total_entradas_mostrar - $total_saidas_mostrar, 2, ',', '.') . ' Kz</td>
            </tr>
        </table>
    </div>';

    // ============================================
    // ASSINATURAS
    // ============================================
    $responsavel_nome = $_SESSION['user_nome'] ?? '-';
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
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $nome_arquivo = 'relatorio_financeiro_' . $ano . '.pdf';
    $dompdf->stream($nome_arquivo, array("Attachment" => false));
    exit;
    }

    /** Antes: modules/reports/index.php */
    public function index(): void
    {
    // modules/reports/index.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('reports');
    $page_title = 'Relatório Financeiro - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));
    $mes_inicio = isset($_GET['mes_inicio']) ? intval($_GET['mes_inicio']) : 1;
    $mes_fim = isset($_GET['mes_fim']) ? intval($_GET['mes_fim']) : 12;

    // Fundo a mostrar - nunca soma cotas e contribuições às cegas, o
    // utilizador escolhe qual dos dois fundos quer ver no relatório.
    $fundos_disponiveis = getFundosRelatorio();
    $fundo = isset($_GET['fundo']) ? $_GET['fundo'] : 'cotas';
    if (!array_key_exists($fundo, $fundos_disponiveis)) $fundo = 'cotas';

    if ($mes_inicio < 1 || $mes_inicio > 12) $mes_inicio = 1;
    if ($mes_fim < 1 || $mes_fim > 12) $mes_fim = 12;

    // ============================================
    // DADOS DO RELATÓRIO
    // ============================================
    $resumo_anual = getResumoAnual($ano, $fundo);
    $total_entradas_ano = 0;
    $total_saidas_ano = 0;

    // Calcular totais do período selecionado
    $total_entradas_periodo = 0;
    $total_saidas_periodo = 0;
    $dados_mensais = [];

    for ($mes = $mes_inicio; $mes <= $mes_fim; $mes++) {
        $entradas = $resumo_anual[$mes]['entradas'] ?? 0;
        $saidas = $resumo_anual[$mes]['saidas'] ?? 0;
        $saldo = $entradas - $saidas;

        $total_entradas_periodo += $entradas;
        $total_saidas_periodo += $saidas;

        $dados_mensais[] = [
            'mes' => $mes,
            'nome_mes' => getNomeMes($mes),
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo' => $saldo
        ];
    }

    // Dados para gráficos
    $entradas_mensais = getEntradasMensais($ano, $fundo);
    $saidas_mensais = getSaidasMensais($ano, $fundo);

    // Destacar meses com dados
    $meses_com_dados = [];
    for ($i = 1; $i <= 12; $i++) {
        if ($entradas_mensais[$i] > 0 || $saidas_mensais[$i] > 0) {
            $meses_com_dados[] = $i;
        }
    }
    }

}
