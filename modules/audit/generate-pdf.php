<?php
require_once __DIR__ . '/../../includes/pdf-theme.php';
// modules/audit/generate-pdf.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_perfil'] != 'admin') {
    redirect('login');
    exit;
}

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
// PARÂMETROS DE FILTRO
// ============================================
$usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;
$modulo = isset($_GET['modulo']) ? $_GET['modulo'] : '';
$acao = isset($_GET['acao']) ? $_GET['acao'] : '';
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

// ============================================
// CONSTRUIR QUERY
// ============================================
$where = "1=1";
$params = [];

if ($usuario_id > 0) {
    $where .= " AND usuario_id = :usuario_id";
    $params[':usuario_id'] = $usuario_id;
}

if (!empty($modulo)) {
    $where .= " AND modulo = :modulo";
    $params[':modulo'] = $modulo;
}

if (!empty($acao)) {
    $where .= " AND acao = :acao";
    $params[':acao'] = $acao;
}

if (!empty($data_inicio)) {
    $where .= " AND DATE(criado_em) >= :data_inicio";
    $params[':data_inicio'] = $data_inicio;
}

if (!empty($data_fim)) {
    $where .= " AND DATE(criado_em) <= :data_fim";
    $params[':data_fim'] = $data_fim;
}

$sql = "SELECT * FROM logs_auditoria WHERE $where ORDER BY criado_em DESC LIMIT 500";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            font-size: 9px;
            padding: 15px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #3a271a;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h1 {
            color: #3a271a;
            margin: 0;
            font-size: 16px;
        }
        .header h3 {
            color: #c99a2e;
            margin: 3px 0;
            font-size: 12px;
        }
        .header p {
            color: #7d6a5b;
            font-size: 10px;
            margin: 2px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        th {
            background: #3a271a;
            color: white;
            padding: 4px 5px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 3px 5px;
            border: 1px solid #ece4d2;
        }
        .text-center { text-align: center; }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 8px;
            color: #7d6a5b;
            border-top: 1px solid #ece4d2;
            padding-top: 8px;
        }
        .badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 12px;
            font-size: 7px;
            font-weight: bold;
        }
    ' . pdfBrandCss() . '
    </style>
</head>
<body>

<div class="header">
    ' . pdfCabecalho() . '
    <p class="doc-title">RELATÓRIO DE AUDITORIA</p>
    <p>Data de emissão: ' . date('d/m/Y H:i') . '</p>
</div>';

if (empty($logs)) {
    $html .= '<p style="text-align:center;padding:30px;color:#a39485;">Nenhuma atividade registada para os filtros selecionados.</p>';
} else {
    $html .= '
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Data/Hora</th>
                <th>Utilizador</th>
                <th>Ação</th>
                <th>Módulo</th>
                <th>Descrição</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($logs as $index => $log) {
        $acao_info = getAcaoLabel($log['acao']);
        $html .= '
        <tr>
            <td>' . ($index + 1) . '</td>
            <td>' . date('d/m/Y H:i:s', strtotime($log['criado_em'])) . '</td>
            <td>' . htmlspecialchars($log['usuario_nome']) . '</td>
            <td><span class="badge" style="background:' . $acao_info['color'] . '20;color:' . $acao_info['color'] . ';">' . $acao_info['label'] . '</span></td>
            <td>' . getModuloLabel($log['modulo']) . '</td>
            <td>' . htmlspecialchars($log['descricao']) . '</td>
            <td>' . htmlspecialchars($log['ip']) . '</td>
        </tr>';
    }

    $html .= '
        </tbody>
    </table>

    <div style="margin-top:10px;font-size:8px;color:#7d6a5b;">
        <p><strong>Resumo:</strong> Total de registos: ' . count($logs) . '</p>
    </div>';
}

$html .= '
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
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$nome_arquivo = 'auditoria_' . date('Y-m-d') . '.pdf';
$dompdf->stream($nome_arquivo, array("Attachment" => false));
exit;
?>