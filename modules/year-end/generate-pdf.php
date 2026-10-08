<?php
require_once __DIR__ . '/../../includes/pdf-theme.php';
// modules/year-end/generate-pdf.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('year_end');
require_once '../../config/database.php';
require_once '../../config/url.php';
require_once 'functions.php';

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