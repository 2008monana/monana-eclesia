<?php
// modules/users/toggle-status.php
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
$ativo = intval($_POST['ativo'] ?? 0);

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Não permitir desativar o próprio admin
if ($id == $_SESSION['user_id']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não pode alterar o próprio status']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE usuarios SET ativo = :ativo WHERE id = :id");
    $stmt->execute([':ativo' => $ativo, ':id' => $id]);

    $stmt = $conn->prepare("SELECT nome_completo FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $nome_usuario = $stmt->fetchColumn();
    registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], $ativo ? 'ativar' : 'desativar', 'users', ($ativo ? "Ativou" : "Desativou") . " o utilizador \"$nome_usuario\" (ID $id)");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>