<?php
// modules/year-end/toggle-status.php
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
require_once '../../config/url.php';
require_once '../../modules/audit/functions.php';
$conn = getConnection();

$membro_id = intval($_POST['membro_id'] ?? 0);
$ano = intval($_POST['ano'] ?? 0);
$acao = intval($_POST['acao'] ?? 0);

if ($membro_id <= 0 || $ano <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$stmt_nome = $conn->prepare("SELECT nome_completo FROM membros WHERE id = :id");
$stmt_nome->execute([':id' => $membro_id]);
$nome_membro_log = $stmt_nome->fetch(PDO::FETCH_ASSOC)['nome_completo'] ?? "ID $membro_id";

try {
    if ($acao == 1) {
        // Marcar como pago (apenas se não houver pagamentos registrados)
        $total = getTotalPagoFimAno($membro_id, $ano);
        if (($total['total_pago'] ?? 0) > 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Este membro já possui pagamentos registrados. Use o formulário para adicionar mais.']);
            exit;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO contribuicao_fim_ano (membro_id, ano, valor, data_pagamento, registrado_por) 
            VALUES (:membro_id, :ano, 5000.00, CURDATE(), :registrado_por)
        ");
        $stmt->execute([
            ':membro_id' => $membro_id,
            ':ano' => $ano,
            ':registrado_por' => $_SESSION['user_id']
        ]);
        registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'marcar_pago', 'year-end', "Marcou como pago o fim de ano de {$nome_membro_log} (ano $ano)");
    } else {
        // Desmarcar (remover todos os pagamentos do membro para aquele ano)
        $stmt = $conn->prepare("DELETE FROM contribuicao_fim_ano WHERE membro_id = :membro_id AND ano = :ano");
        $stmt->execute([':membro_id' => $membro_id, ':ano' => $ano]);
        registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'desmarcar', 'year-end', "Desmarcou o fim de ano de {$nome_membro_log} (ano $ano)");
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>