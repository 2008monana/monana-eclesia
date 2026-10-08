<?php
// modules/expenses/delete.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}


require_once '../../config/session.php';
if (!podeAcessarModulo('expenses')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Sem permissão para este módulo']);
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
    // Verificar permissão (apenas admin ou editor)
    if (!in_array($_SESSION['user_perfil'], ['admin', 'editor'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permissão negada']);
        exit;
    }

    $stmt = $conn->prepare("SELECT descricao FROM saidas WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $descricao_saida = $stmt->fetchColumn();

    $stmt = $conn->prepare("DELETE FROM saidas WHERE id = :id");
    $stmt->execute([':id' => $id]);

    registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'excluir', 'expenses', "Excluiu a saída \"$descricao_saida\" (ID $id)");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>