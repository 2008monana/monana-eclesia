<?php
// modules/year-end/register.php
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
require_once '../../modules/audit/functions.php';

$conn = getConnection();

$membro_id = intval($_POST['membro_id'] ?? 0);
$ano = intval($_POST['ano'] ?? 0);
$valor = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0')));

if ($membro_id <= 0 || $ano <= 0 || $valor <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    // Verificar se o valor não ultrapassa o saldo restante
    $saldo_restante = getSaldoRestanteFimAno($membro_id, $ano);
    if ($valor > $saldo_restante) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Valor excede o saldo restante de ' . number_format($saldo_restante, 2, ',', '.') . ' Kz'
        ]);
        exit;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO contribuicao_fim_ano (membro_id, ano, valor, data_pagamento, registrado_por) 
        VALUES (:membro_id, :ano, :valor, CURDATE(), :registrado_por)
    ");
    $stmt->execute([
        ':membro_id' => $membro_id,
        ':ano' => $ano,
        ':valor' => $valor,
        ':registrado_por' => $_SESSION['user_id']
    ]);

    $stmt_nome = $conn->prepare("SELECT nome_completo FROM membros WHERE id = :id");
    $stmt_nome->execute([':id' => $membro_id]);
    $nome_membro_log = $stmt_nome->fetch(PDO::FETCH_ASSOC)['nome_completo'] ?? "ID $membro_id";

    registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'registrar', 'year-end', "Registou contribuição de fim de ano de " . number_format($valor, 2, ',', '.') . " Kz para {$nome_membro_log} (ano $ano)");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>