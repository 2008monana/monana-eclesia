<?php
require_once __DIR__ . '/../../includes/pdf-theme.php';
// modules/registry/generate-pdf.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('registry');
require_once '../../config/database.php';
require_once '../../config/url.php';

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
$data_filtro = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_filtro)) {
    $data_filtro = date('Y-m-d');
}

// ============================================
// BUSCAR REGISTROS DO DIA
// ============================================
$stmt = $conn->prepare("
    SELECT 
        c.*,
        m.nome_completo,
        m.categoria,
        u.nome_completo as registrado_nome
    FROM cotas_diarias c
    JOIN membros m ON c.membro_id = m.id
    LEFT JOIN usuarios u ON c.registrado_por = u.id
    WHERE DATE(c.data_pagamento) = :data
    ORDER BY c.criado_em DESC
");
$stmt->execute([':data' => $data_filtro]);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// CALCULAR TOTAIS
// ============================================
$total_geral = 0;
$total_mamas = 0;
$total_papas = 0;
$total_jovens = 0;
$total_membros_pagaram = [];

foreach ($registros as $r) {
    $total_geral += $r['valor'];
    
    if ($r['categoria'] == 'mama') {
        $total_mamas += $r['valor'];
    } elseif ($r['categoria'] == 'papa') {
        $total_papas += $r['valor'];
    } elseif ($r['categoria'] == 'jovem') {
        $total_jovens += $r['valor'];
    }
    
    if (!in_array($r['membro_id'], $total_membros_pagaram)) {
        $total_membros_pagaram[] = $r['membro_id'];
    }
}

$total_membros_pagaram = count($total_membros_pagaram);

// ============================================
// RESPONSÁVEL DA COTA
// ============================================
// O responsável que assina o PDF é sempre quem efetivamente registou as
// cotas naquele dia (não um cargo genérico). Se todos os registos do dia
// foram feitos pela mesma pessoa, usamos o nome dela; caso contrário (ou
// se não houver registos), caímos para o utilizador com sessão activa.
$nomes_registrantes = array_unique(array_filter(array_column($registros, 'registrado_nome')));
$responsavel_nome = count($nomes_registrantes) === 1 ? reset($nomes_registrantes) : ($_SESSION['user_nome'] ?? '-');

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
        .text-right { text-align: right; }
        .text-left { text-align: left; }
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
        .resumo-categorias {
            margin-top: 15px;
            display: flex;
            justify-content: space-around;
            font-size: 10px;
        }
        .resumo-categorias .cat {
            padding: 8px 16px;
            border-radius: 8px;
        }
        .resumo-categorias .cat .label {
            font-weight: bold;
        }
        .resumo-categorias .cat .valor {
            font-size: 14px;
            font-weight: bold;
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
<body>';

if (empty($registros)) {
    $html .= '
    <div class="header">
        ' . pdfCabecalho() . '
    <p class="doc-title">REGISTO DIÁRIO DE COTAS</p>
        <p>Data: ' . date('d/m/Y', strtotime($data_filtro)) . '</p>
        <p>Data de emissão: ' . date('d/m/Y H:i') . '</p>
    </div>
    <div style="text-align:center;padding:50px;color:#a39485;font-size:14px;">
        <p>Nenhum registro encontrado para esta data.</p>
    </div>';
} else {
    // Categorias para cores
    $categoria_cores = [
        'mama' => ['label' => 'Mamã', 'color' => '#b5527a'],
        'papa' => ['label' => 'Papá', 'color' => '#3f7d4e'],
        'jovem' => ['label' => 'Jovem', 'color' => '#7a5ca8']
    ];

    $html .= '
    <div class="header">
        ' . pdfCabecalho() . '
    <p class="doc-title">REGISTO DIÁRIO DE COTAS</p>
        <p>Data: ' . date('d/m/Y', strtotime($data_filtro)) . '</p>
        <p>Data de emissão: ' . date('d/m/Y H:i') . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="text-align:left;">#</th>
                <th style="text-align:left;">Membro</th>
                <th style="text-align:left;">Categoria</th>
                <th style="text-align:left;">Mês Referência</th>
                <th style="text-align:right;">Valor (Kz)</th>
                <th style="text-align:left;">Registrado por</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($registros as $index => $r) {
        $cat = $categoria_cores[$r['categoria']] ?? ['label' => $r['categoria'], 'color' => '#gray-500'];
        
        $html .= '
        <tr>
            <td>' . ($index + 1) . '</td>
            <td style="font-weight:bold;">' . htmlspecialchars($r['nome_completo']) . '</td>
            <td><span class="badge-categoria" style="background:' . $cat['color'] . ';">' . $cat['label'] . '</span></td>
            <td>' . date('m/Y', strtotime($r['mes_referencia'])) . '</td>
            <td class="text-right" style="color:#3f7d4e;font-weight:bold;">' . number_format($r['valor'], 2, ',', '.') . '</td>
            <td>' . htmlspecialchars($r['registrado_nome'] ?? '-') . '</td>
        </tr>';
    }

    $html .= '
        <tr class="total-row">
            <td colspan="4" class="text-right" style="font-size:11px;">TOTAL DO DIA:</td>
            <td class="text-right" style="font-size:11px;color:#3f7d4e;font-weight:bold;">' . number_format($total_geral, 2, ',', '.') . ' Kz</td>
            <td></td>
        </tr>
    </tbody>
    </table>

    <!-- Resumo por Categoria -->
    <div class="resumo-categorias">
        <div class="cat" style="background:#fce4ec;">
            <div class="label" style="color:#b5527a;"><i class="fas fa-female"></i> Mamãs</div>
            <div class="valor" style="color:#b5527a;">' . number_format($total_mamas, 2, ',', '.') . ' Kz</div>
        </div>
        <div class="cat" style="background:#eaf3ec;">
            <div class="label" style="color:#3f7d4e;"><i class="fas fa-male"></i> Papás</div>
            <div class="valor" style="color:#3f7d4e;">' . number_format($total_papas, 2, ',', '.') . ' Kz</div>
        </div>
        <div class="cat" style="background:#ede7f6;">
            <div class="label" style="color:#7a5ca8;"><i class="fas fa-user-graduate"></i> Jovens</div>
            <div class="valor" style="color:#7a5ca8;">' . number_format($total_jovens, 2, ',', '.') . ' Kz</div>
        </div>
    </div>
    
    <div style="margin-top:15px;font-size:10px;color:#7d6a5b;text-align:center;">
        <p><strong>Resumo:</strong> ' . $total_membros_pagaram . ' membros pagaram | Total: ' . number_format($total_geral, 2, ',', '.') . ' Kz</p>
    </div>';
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

// Orientação: portrait (retrato) para registo diário
$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

// Nome do arquivo
$nome_arquivo = 'registo_diario_' . date('Y-m-d', strtotime($data_filtro)) . '.pdf';

// Forçar download ou exibir no navegador
$dompdf->stream($nome_arquivo, array("Attachment" => false));
exit;