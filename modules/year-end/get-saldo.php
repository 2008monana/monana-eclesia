<?php
// modules/year-end/get-saldo.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}


require_once '../../config/session.php';
if (!podeAcessarModulo('year_end')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Sem permissão para este módulo']);
    exit;
}
require_once '../../config/database.php';
require_once 'functions.php';

$conn = getConnection();

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