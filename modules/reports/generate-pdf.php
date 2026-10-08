<?php
require_once __DIR__ . '/../../includes/pdf-theme.php';
// modules/reports/generate-pdf.php
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