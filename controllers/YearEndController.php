<?php
// controllers/YearEndController.php
// Controlador do modulo "year-end" - arquitectura MVC.
// Logica original dos antigos modules/year-end/*.php preservada,
// agora separada em Model (dados) / Controller (fluxo) / View (HTML).

require_once BASE_PATH . '/models/YearEndModel.php';
require_once BASE_PATH . '/models/AuditModel.php';
use Dompdf\Dompdf;
use Dompdf\Options;

class YearEndController extends Controller
{
    protected string $modulo = 'year_end';

    /** Antes: modules/year-end/functions.php */
    public function functions(): void
    {
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
    }

    /** Antes: modules/year-end/generate-pdf.php */
    public function generatePdf(): void
    {
    require_once __DIR__ . '/../../includes/pdf-theme.php';
    // modules/year-end/generate-pdf.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('year_end');

    }


    use Dompdf\Dompdf;
    use Dompdf\Options;

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');
    $categoria = isset($_GET['categoria']) ? $_GET['categoria'] : 'todos';

    if ($ano < 2026 || $ano > 2100) $ano = date('Y');

    // ============================================
    // BUSCAR MEMBROS
    // ============================================
    $where = "m.ativo = 1 AND m.categoria IN ('mama', 'papa', 'jovem')";
    $params = [':ano' => $ano];

    if ($categoria != 'todos') {
        $where .= " AND m.categoria = :categoria";
        $params[':categoria'] = $categoria;
    }

    $stmt = $conn->prepare("
        SELECT 
            m.id,
            m.nome_completo,
            m.categoria,
            m.telefone,
            c.id as pagamento_id,
            c.valor,
            c.data_pagamento
        FROM membros m
        LEFT JOIN contribuicao_fim_ano c ON m.id = c.membro_id AND c.ano = :ano
        WHERE $where
        ORDER BY 
            CASE m.categoria
                WHEN 'mama' THEN 1
                WHEN 'papa' THEN 2
                WHEN 'jovem' THEN 3
            END,
            m.nome_completo ASC
    ");
    $stmt->execute($params);
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // ESTATÍSTICAS
    // ============================================
    $total_membros = count($membros);
    $total_pagaram = 0;
    $total_arrecadado = 0;

    foreach ($membros as $m) {
        if ($m['pagamento_id']) {
            $total_pagaram++;
            $total_arrecadado += $m['valor'];
        }
    }

    $categoria_cores = [
        'mama'  => ['label' => 'Mamã', 'color' => '#b5527a'],
        'papa'  => ['label' => 'Papá', 'color' => '#3f7d4e'],
        'jovem' => ['label' => 'Jovem', 'color' => '#7a5ca8']
    ];

    $titulo = $categoria == 'todos' ? 'CONTRIBUIÇÃO DE FIM DE ANO - TODOS OS MEMBROS' : 'CONTRIBUIÇÃO DE FIM DE ANO - ' . strtoupper(getCategoriaLabel($categoria)['label']);

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
            .pago { color: #3f7d4e; font-weight: bold; }
            .pendente { color: #b5412f; font-weight: bold; }
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
            .resumo {
                margin-top: 15px;
                font-size: 10px;
            }
            .resumo td {
                border: none;
                padding: 2px 10px;
            }
        ' . pdfBrandCss() . '
        </style>
    </head>
    <body>

    <div class="header">
        ' . pdfCabecalho() . '
        <p class="doc-title">' . $titulo . ' - ' . $ano . '</p>
        <p>Data de emissão: ' . date('d/m/Y H:i') . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align:left;">Nome</th>
                ' . ($categoria == 'todos' ? '<th style="text-align:left;">Categoria</th>' : '') . '
                <th style="text-align:left;">Telefone</th>
                <th style="text-align:center;">Valor (Kz)</th>
                <th style="text-align:center;">Data Pagamento</th>
                <th style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($membros)) {
        $html .= '
            <tr>
                <td colspan="' . ($categoria == 'todos' ? 7 : 6) . '" style="text-align:center;padding:30px;color:#a39485;">
                    Nenhum membro encontrado.
                </td>
            </tr>';
    } else {
        foreach ($membros as $index => $membro) {
            $cat = $categoria_cores[$membro['categoria']] ?? ['label' => $membro['categoria'], 'color' => '#gray-500'];
            $ja_pagou = !empty($membro['pagamento_id']);

            $html .= '
            <tr>
                <td class="text-center">' . ($index + 1) . '</td>
                <td style="font-weight:bold;">' . htmlspecialchars($membro['nome_completo']) . '</td>';

            if ($categoria == 'todos') {
                $html .= '<td><span class="badge-categoria" style="background:' . $cat['color'] . ';">' . $cat['label'] . '</span></td>';
            }

            $html .= '
                <td>' . htmlspecialchars($membro['telefone'] ?? '-') . '</td>
                <td class="text-center">' . ($ja_pagou ? number_format($membro['valor'], 2, ',', '.') : '—') . '</td>
                <td class="text-center">' . ($ja_pagou ? date('d/m/Y', strtotime($membro['data_pagamento'])) : '—') . '</td>
                <td class="text-center">' . ($ja_pagou ? '<span class="pago">✓ Pago</span>' : '<span class="pendente">✗ Pendente</span>') . '</td>
            </tr>';
        }
    }

    $html .= '
            <tr class="total-row">
                <td colspan="' . ($categoria == 'todos' ? 4 : 3) . '" class="text-right">TOTAL GERAL:</td>
                <td class="text-center">' . number_format($total_arrecadado, 2, ',', '.') . ' Kz</td>
                <td class="text-center" colspan="2">' . $total_pagaram . '/' . $total_membros . ' pagaram</td>
            </tr>
        </tbody>
    </table>

    <div class="resumo">
        <table>
            <tr>
                <td><strong>Resumo:</strong></td>
                <td>Total de membros: ' . $total_membros . '</td>
                <td>Pagaram: ' . $total_pagaram . '</td>
                <td>Não pagaram: ' . ($total_membros - $total_pagaram) . '</td>
                <td>Total arrecadado: ' . number_format($total_arrecadado, 2, ',', '.') . ' Kz</td>
            </tr>
        </table>
    </div>

    <div class="assinatura">
        <div>
            <div class="line">_________________</div>
            <p style="font-size:10px;margin-top:5px;">Assinatura do Responsável da Cota</p>
            <p style="font-size:10px;margin-top:2px;font-weight:bold;">' . htmlspecialchars($_SESSION['user_nome'] ?? '-') . '</p>
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

    $nome_arquivo = 'contribuicao_fim_ano_' . ($categoria == 'todos' ? 'todos' : $categoria) . '_' . $ano . '.pdf';
    $dompdf->stream($nome_arquivo, array("Attachment" => false));
    exit;
    ?>
    }

    /** Antes: modules/year-end/get-saldo.php */
    public function getSaldo(): void
    {
    // modules/year-end/get-saldo.php

    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }


    if (!podeAcessarModulo('year_end')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sem permissão para este módulo']);
        exit;
    }

    $conn = db();

    $membro_id = intval($_GET['membro_id'] ?? 0);
    $ano = intval($_GET['ano'] ?? 0);

    if ($membro_id <= 0 || $ano <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    $saldo_restante = getSaldoRestanteFimAno($membro_id, $ano);

    header('Content-Type: application/json');
    echo json_encode(['saldo_restante' => number_format($saldo_restante, 2, ',', '.')]);
    ?>
    }

    /** Antes: modules/year-end/index.php */
    public function index(): void
    {
    // modules/year-end/index.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('year_end');
    $page_title = 'Contribuição Fim de Ano - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));
    $categoria = isset($_GET['categoria']) ? $_GET['categoria'] : 'todos';

    $categorias_validas = ['todos', 'mama', 'papa', 'jovem'];
    if (!in_array($categoria, $categorias_validas)) {
        $categoria = 'todos';
    }

    // ============================================
    // BUSCAR MEMBROS
    // ============================================
    $categoria_cores = [
        'mama'  => ['label' => 'Mamã', 'color' => '#b5527a'],
        'papa'  => ['label' => 'Papá', 'color' => '#3f7d4e'],
        'jovem' => ['label' => 'Jovem', 'color' => '#7a5ca8']
    ];

    $where = "m.ativo = 1 AND m.categoria IN ('mama', 'papa', 'jovem')";
    $params = [];

    if ($categoria != 'todos') {
        $where .= " AND m.categoria = :categoria";
        $params[':categoria'] = $categoria;
    }

    $sql = "SELECT 
                m.id,
                m.nome_completo,
                m.categoria,
                m.telefone
            FROM membros m
            WHERE $where
            ORDER BY 
                CASE m.categoria
                    WHEN 'mama' THEN 1
                    WHEN 'papa' THEN 2
                    WHEN 'jovem' THEN 3
                END,
                m.nome_completo ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // CALCULAR DADOS DE CADA MEMBRO
    // ============================================
    $total_membros = count($membros);
    $total_quitados = 0;
    $total_arrecadado = 0;
    $total_com_pagamento = 0;

    foreach ($membros as &$membro) {
        $total_pago = getTotalPagoFimAno($membro['id'], $ano);
        $membro['total_pago'] = $total_pago['total_pago'] ?? 0;
        $membro['qtd_pagamentos'] = $total_pago['qtd_pagamentos'] ?? 0;
        $membro['saldo_restante'] = getSaldoRestanteFimAno($membro['id'], $ano);
        $membro['esta_quitado'] = $membro['total_pago'] >= 5000;
        $membro['pagamentos'] = getPagamentosFimAno($membro['id'], $ano);

        if ($membro['total_pago'] > 0) {
            $total_com_pagamento++;
            $total_arrecadado += $membro['total_pago'];
        }

        if ($membro['esta_quitado']) {
            $total_quitados++;
        }
    }
    unset($membro);

    // Total de saídas já tiradas do fundo de contribuições neste ano
    $total_saidas_contribuicoes = getTotalSaidasFimAno($ano);
    $saldo_disponivel_contribuicoes = $total_arrecadado - $total_saidas_contribuicoes;

    // Resumo por categoria
    $resumo_categorias = [];
    $cats = ['mama', 'papa', 'jovem'];
    foreach ($cats as $cat) {
        $resumo_categorias[$cat] = getResumoFimAno($ano, $cat);
    }

    $cat_info = $categoria == 'todos' ? ['label' => 'Todos os Membros', 'icon' => 'fa-users', 'color' => '#c99a2e'] : getCategoriaLabel($categoria);
    }

    /** Antes: modules/year-end/register.php */
    public function register(): void
    {
    // modules/year-end/register.php

    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }


    if (!podeAcessarModulo('year_end')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sem permissão para este módulo']);
        exit;
    }

    $conn = db();

    $membro_id = intval($_POST['membro_id'] ?? 0);
    $ano = intval($_POST['ano'] ?? 0);
    $valor = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0')));

    if ($membro_id <= 0 || $ano <= 0 || $valor <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    try {
        // Verificar se o valor não ultrapassa o saldo restante
        $saldo_restante = getSaldoRestanteFimAno($membro_id, $ano);
        if ($valor > $saldo_restante) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => 'Valor excede o saldo restante de ' . number_format($saldo_restante, 2, ',', '.') . ' Kz'
            ]);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO contribuicao_fim_ano (membro_id, ano, valor, data_pagamento, registrado_por) 
            VALUES (:membro_id, :ano, :valor, CURDATE(), :registrado_por)
        ");
        $stmt->execute([
            ':membro_id' => $membro_id,
            ':ano' => $ano,
            ':valor' => $valor,
            ':registrado_por' => $_SESSION['user_id']
        ]);

        $stmt_nome = $conn->prepare("SELECT nome_completo FROM membros WHERE id = :id");
        $stmt_nome->execute([':id' => $membro_id]);
        $nome_membro_log = $stmt_nome->fetch(PDO::FETCH_ASSOC)['nome_completo'] ?? "ID $membro_id";

        registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'registrar', 'year-end', "Registou contribuição de fim de ano de " . number_format($valor, 2, ',', '.') . " Kz para {$nome_membro_log} (ano $ano)");

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    ?>
    }

    /** Antes: modules/year-end/remove.php */
    public function remove(): void
    {
    // modules/year-end/remove.php

    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }


    if (!podeAcessarModulo('year_end')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sem permissão para este módulo']);
        exit;
    }
    $conn = db();

    $membro_id = intval($_POST['membro_id'] ?? 0);
    $ano = intval($_POST['ano'] ?? 0);

    if ($membro_id <= 0 || $ano <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    try {
        $stmt_nome = $conn->prepare("SELECT nome_completo FROM membros WHERE id = :id");
        $stmt_nome->execute([':id' => $membro_id]);
        $nome_membro_log = $stmt_nome->fetch(PDO::FETCH_ASSOC)['nome_completo'] ?? "ID $membro_id";

        $stmt = $conn->prepare("DELETE FROM contribuicao_fim_ano WHERE membro_id = :membro_id AND ano = :ano");
        $stmt->execute([':membro_id' => $membro_id, ':ano' => $ano]);

        registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'remover', 'year-end', "Removeu contribuições de fim de ano de {$nome_membro_log} (ano $ano)");

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    ?>
    }

    /** Antes: modules/year-end/toggle-status.php */
    public function toggleStatus(): void
    {
    // modules/year-end/toggle-status.php

    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }


    if (!podeAcessarModulo('year_end')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sem permissão para este módulo']);
        exit;
    }
    $conn = db();

    $membro_id = intval($_POST['membro_id'] ?? 0);
    $ano = intval($_POST['ano'] ?? 0);
    $acao = intval($_POST['acao'] ?? 0);

    if ($membro_id <= 0 || $ano <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    $stmt_nome = $conn->prepare("SELECT nome_completo FROM membros WHERE id = :id");
    $stmt_nome->execute([':id' => $membro_id]);
    $nome_membro_log = $stmt_nome->fetch(PDO::FETCH_ASSOC)['nome_completo'] ?? "ID $membro_id";

    try {
        if ($acao == 1) {
            // Marcar como pago (apenas se não houver pagamentos registrados)
            $total = getTotalPagoFimAno($membro_id, $ano);
            if (($total['total_pago'] ?? 0) > 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Este membro já possui pagamentos registrados. Use o formulário para adicionar mais.']);
                exit;
            }

            $stmt = $conn->prepare("
                INSERT INTO contribuicao_fim_ano (membro_id, ano, valor, data_pagamento, registrado_por) 
                VALUES (:membro_id, :ano, 5000.00, CURDATE(), :registrado_por)
            ");
            $stmt->execute([
                ':membro_id' => $membro_id,
                ':ano' => $ano,
                ':registrado_por' => $_SESSION['user_id']
            ]);
            registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'marcar_pago', 'year-end', "Marcou como pago o fim de ano de {$nome_membro_log} (ano $ano)");
        } else {
            // Desmarcar (remover todos os pagamentos do membro para aquele ano)
            $stmt = $conn->prepare("DELETE FROM contribuicao_fim_ano WHERE membro_id = :membro_id AND ano = :ano");
            $stmt->execute([':membro_id' => $membro_id, ':ano' => $ano]);
            registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'desmarcar', 'year-end', "Desmarcou o fim de ano de {$nome_membro_log} (ano $ano)");
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    ?>
    }

}
