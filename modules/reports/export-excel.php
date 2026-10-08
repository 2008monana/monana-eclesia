<?php
// modules/reports/export-excel.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /ipfva-gestao/modules/auth/login.php');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('reports');
require_once '../../config/database.php';
require_once 'functions.php';

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