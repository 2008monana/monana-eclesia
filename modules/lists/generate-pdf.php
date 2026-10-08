<?php
require_once __DIR__ . '/../../includes/pdf-theme.php';
// modules/lists/generate-pdf.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('lists');
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