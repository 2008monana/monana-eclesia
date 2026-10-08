<?php
// modules/users/reset-password.php
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
$senha = $_POST['senha'] ?? '';

if ($id <= 0 || empty($senha) || strlen($senha) < 6) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Senha inválida (mínimo 6 caracteres)']);
    exit;
}

try {
    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE usuarios SET senha_hash = :hash WHERE id = :id");
    $stmt->execute([':hash' => $hash, ':id' => $id]);

    $stmt = $conn->prepare("SELECT nome_completo FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $nome_usuario = $stmt->fetchColumn();
    registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'resetar_senha', 'users', "Redefiniu a senha do utilizador \"$nome_usuario\" (ID $id)");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>