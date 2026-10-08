<?php
// modules/registry/delete.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}


require_once '../../config/session.php';
if (!podeAcessarModulo('registry')) {
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

// Verificar permissão (apenas admin ou editor)
if (!in_array($_SESSION['user_perfil'], ['admin', 'editor'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permissão negada']);
    exit;
}

// Buscar registro para verificar a data
$stmt = $conn->prepare("
    SELECT c.data_pagamento, c.valor, m.nome_completo
    FROM cotas_diarias c
    JOIN membros m ON c.membro_id = m.id
    WHERE c.id = :id
");
$stmt->execute([':id' => $id]);
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registro) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
    exit;
}

// ============================================
// VERIFICAR SE É O DIA ATUAL (BLOQUEAR EXCLUSÃO DE DIAS PASSADOS)
// ============================================
$hoje = date('Y-m-d');
$data_registro = date('Y-m-d', strtotime($registro['data_pagamento']));

if ($data_registro != $hoje) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não é possível excluir registos de dias passados. Apenas o dia atual pode ser excluído.']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM cotas_diarias WHERE id = :id");
    $stmt->execute([':id' => $id]);

    registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'excluir', 'registry', "Excluiu o registo de cota de {$registro['nome_completo']} - " . number_format($registro['valor'], 2, ',', '.') . " Kz (pago em " . date('d/m/Y', strtotime($registro['data_pagamento'])) . ")");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>