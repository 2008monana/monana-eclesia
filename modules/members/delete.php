<?php
// modules/members/delete.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_perfil'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permissão negada']);
    exit;
}

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../modules/audit/functions.php';
$conn = getConnection();

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT nome_completo FROM membros WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $nome_membro = $stmt->fetchColumn();

    // Verificar se existem registros de cotas
    $stmt = $conn->prepare("SELECT COUNT(*) FROM cotas_diarias WHERE membro_id = :id");
    $stmt->execute([':id' => $id]);
    $temCotas = $stmt->fetchColumn() > 0;
    
    if ($temCotas) {
        // Se tem cotas, apenas desativar
        $stmt = $conn->prepare("UPDATE membros SET ativo = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $mensagem = 'Membro desativado pois possui registros de cotas.';
        registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'desativar', 'members', "Desativou o membro \"$nome_membro\" (ID $id) por ter cotas associadas");
    } else {
        // Se não tem cotas, excluir permanentemente
        $stmt = $conn->prepare("DELETE FROM membros WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $mensagem = 'Membro excluído permanentemente.';
        registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'excluir', 'members', "Excluiu o membro \"$nome_membro\" (ID $id)");
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $mensagem]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>