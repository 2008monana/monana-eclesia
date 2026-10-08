<?php
// modules/auth/logout.php
session_start();

require_once '../../config/url.php';

// Verificar se há uma requisição AJAX
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (isset($_SESSION['user_id'])) {
    require_once '../../config/database.php';
    require_once '../../modules/audit/functions.php';
    $conn = getConnection();
    registrarLog(
        $_SESSION['user_id'],
        $_SESSION['user_nome'],
        'logout',
        'auth',
        "Logout realizado"
    );
}

session_destroy();

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'redirect' => url('modules/auth/login.php')]);
    exit;
}

redirect('modules/auth/login.php');