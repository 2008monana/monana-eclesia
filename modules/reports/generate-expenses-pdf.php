<?php
require_once __DIR__ . '/../../includes/pdf-theme.php';
// modules/reports/generate-expenses-pdf.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('reports');
require_once '../../config/database.php';
require_once '../../config/url.php';
require_once 'functions.php';

// Verificar se o DOMPDF está instalado
if (!file_exists('../../vendor/autoload.php')) {
    die('Instale o DOMPDF: composer require dompdf/dompdf');
}

require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$conn = getConnection();

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