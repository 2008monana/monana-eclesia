<?php
// modules/backups/export-sql.php
// Gera um backup .sql completo (estrutura + dados) de todas as tabelas.
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}

if ($_SESSION['user_perfil'] != 'admin') {
    redirect('dashboard');
    exit;
}

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../modules/audit/functions.php';
require_once 'functions.php';

$conn = getConnection();

$tabelas = getListaTabelas($conn);

$dataGeracao = date('d-M-Y \à\s H:i');
$sql = "-- ============================================\n";
$sql .= "-- BACKUP DA BASE DE DADOS\n";
$sql .= "-- Sistema de Gestão I.P.F.V.A - Calemba 2\n";
$sql .= "-- ============================================\n";
$sql .= "-- Base de dados: `" . DB_NAME . "`\n";
$sql .= "-- Gerado em: " . $dataGeracao . "\n";
$sql .= "-- Gerado por: " . $_SESSION['user_nome'] . "\n";
$sql .= "-- ============================================\n\n";
$sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
$sql .= "SET AUTOCOMMIT = 0;\n";
$sql .= "START TRANSACTION;\n";
$sql .= "SET time_zone = \"+00:00\";\n";
$sql .= "SET NAMES utf8mb4;\n\n";

foreach ($tabelas as $tabela) {
    // Estrutura
    $stmt = $conn->query('SHOW CREATE TABLE `' . $tabela . '`');
    $criar = $stmt->fetch(PDO::FETCH_ASSOC);
    $createSql = $criar['Create Table'] ?? '';

    $sql .= "-- --------------------------------------------------------\n\n";
    $sql .= "--\n-- Estrutura da tabela `$tabela`\n--\n\n";
    $sql .= "DROP TABLE IF EXISTS `$tabela`;\n";
    $sql .= $createSql . ";\n\n";

    // Dados
    $stmtDados = $conn->query('SELECT * FROM `' . $tabela . '`');
    $linhas = $stmtDados->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($linhas)) {
        $colunas = array_keys($linhas[0]);
        $colunasEscapadas = array_map(function ($c) { return "`$c`"; }, $colunas);

        $sql .= "--\n-- Extraindo dados da tabela `$tabela`\n--\n\n";
        $sql .= "INSERT INTO `$tabela` (" . implode(', ', $colunasEscapadas) . ") VALUES\n";

        $valoresLinhas = [];
        foreach ($linhas as $linha) {
            $valores = [];
            foreach ($linha as $valor) {
                if ($valor === null) {
                    $valores[] = 'NULL';
                } else {
                    $valores[] = $conn->quote($valor);
                }
            }
            $valoresLinhas[] = '(' . implode(', ', $valores) . ')';
        }

        $sql .= implode(",\n", $valoresLinhas) . ";\n\n";
    }
}

$sql .= "COMMIT;\n";

// Regista no histórico de auditoria
registrarLog(
    $_SESSION['user_id'],
    $_SESSION['user_nome'],
    'exportar',
    'backups',
    'Gerou um backup completo da base de dados em formato SQL (' . count($tabelas) . ' tabelas)'
);

$nomeArquivo = 'backup_ipfva_' . date('Y-m-d_His') . '.sql';

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Content-Length: ' . strlen($sql));
header('Cache-Control: max-age=0');

echo $sql;
exit;
?>
