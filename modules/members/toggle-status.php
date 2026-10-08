<?php
// modules/members/toggle-status.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}


require_once '../../config/session.php';
if (!podeAcessarModulo('members')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Sem permissão para este módulo']);
    exit;
}
require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../modules/audit/functions.php';
$conn = getConnection();

$id = intval($_POST['id'] ?? 0);
$ativo = intval($_POST['ativo'] ?? 0);

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE membros SET ativo = :ativo WHERE id = :id");
    $stmt->execute([':ativo' => $ativo, ':id' => $id]);

    $stmt = $conn->prepare("SELECT nome_completo FROM membros WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $nome_membro = $stmt->fetchColumn();
    registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], $ativo ? 'ativar' : 'desativar', 'members', ($ativo ? "Ativou" : "Desativou") . " o membro \"$nome_membro\" (ID $id)");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>