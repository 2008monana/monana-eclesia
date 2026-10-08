<?php
// modules/users/delete.php
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

// Não permitir eliminar o próprio admin
if ($id == $_SESSION['user_id']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não pode eliminar o próprio utilizador']);
    exit;
}

try {
    // Buscar dados do utilizador para o log
    $stmt = $conn->prepare("SELECT nome_completo, email, perfil FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Utilizador não encontrado']);
        exit;
    }
    
    // Registrar log antes de eliminar
    registrarLog(
        $_SESSION['user_id'],
        $_SESSION['user_nome'],
        'excluir',
        'users',
        "Utilizador '{$usuario['nome_completo']}' (Email: {$usuario['email']}, Perfil: {$usuario['perfil']}) foi eliminado permanentemente",
        $usuario,
        null
    );
    
    // Eliminar o utilizador
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>