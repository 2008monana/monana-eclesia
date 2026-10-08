<?php
// modules/backups/export-excel.php
// Gera um backup .xlsx com uma folha por módulo (tabela), cada uma com
// os respectivos registos.
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /ipfva-gestao/modules/auth/login.php');
    exit;
}

if ($_SESSION['user_perfil'] != 'admin') {
    header('Location: /ipfva-gestao/modules/dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../modules/audit/functions.php';
require_once 'functions.php';
require_once 'xlsx-writer.php';

$conn = getConnection();
$tabelas = getListaTabelas($conn);

$xlsx = new SimpleXLSXWriter();

// Folha inicial com o resumo do backup
$stats = getEstatisticasBackup($conn);
$resumoLinhas = [];
foreach ($tabelas as $tabela) {
    $resumoLinhas[] = [
        getTabelaLabel($tabela),
        $tabela,
        $stats['por_tabela'][$tabela] ?? 0,
    ];
}
$xlsx->addSheet('Resumo', ['Módulo', 'Tabela', 'Total de Registos'], $resumoLinhas);

// Uma folha por tabela/módulo, com os seus registos
foreach ($tabelas as $tabela) {
    $stmt = $conn->query('SELECT * FROM `' . $tabela . '`');
    $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($linhas)) {
        $cabecalhos = array_keys($linhas[0]);
        $dados = $linhas;
    } else {
        // Mesmo sem registos, tenta obter os nomes das colunas da tabela
        $stmtCols = $conn->query('SHOW COLUMNS FROM `' . $tabela . '`');
        $cabecalhos = array_column($stmtCols->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $dados = [];
    }

    $xlsx->addSheet(getTabelaLabel($tabela), $cabecalhos, $dados);
}

// Regista no histórico de auditoria
registrarLog(
    $_SESSION['user_id'],
    $_SESSION['user_nome'],
    'exportar',
    'backups',
    'Gerou um backup completo da base de dados em formato Excel (' . count($tabelas) . ' módulos)'
);

$nomeArquivo = 'backup_ipfva_' . date('Y-m-d_His') . '.xlsx';
$xlsx->download($nomeArquivo);
?>
