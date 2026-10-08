<?php
// modules/audit/functions.php

function registrarLog($usuario_id, $usuario_nome, $acao, $modulo, $descricao, $dados_anteriores = null, $dados_novos = null) {
    global $conn;
    
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $conn->prepare("
            INSERT INTO logs_auditoria 
            (usuario_id, usuario_nome, acao, modulo, descricao, dados_anteriores, dados_novos, ip, user_agent) 
            VALUES 
            (:usuario_id, :usuario_nome, :acao, :modulo, :descricao, :dados_anteriores, :dados_novos, :ip, :user_agent)
        ");
        
        $stmt->execute([
            ':usuario_id' => $usuario_id,
            ':usuario_nome' => $usuario_nome,
            ':acao' => $acao,
            ':modulo' => $modulo,
            ':descricao' => $descricao,
            ':dados_anteriores' => $dados_anteriores ? json_encode($dados_anteriores) : null,
            ':dados_novos' => $dados_novos ? json_encode($dados_novos) : null,
            ':ip' => $ip,
            ':user_agent' => $user_agent
        ]);
        
        return true;
    } catch (PDOException $e) {
        // Não interromper o sistema se o log falhar
        error_log("Erro ao registrar log: " . $e->getMessage());
        return false;
    }
}

function getAcaoLabel($acao) {
    $labels = [
        'login' => ['label' => 'Login', 'color' => '#3f7d4e', 'icon' => 'fa-sign-in-alt'],
        'logout' => ['label' => 'Logout', 'color' => '#6b7280', 'icon' => 'fa-sign-out-alt'],
        'criar' => ['label' => 'Criar', 'color' => '#8a6317', 'icon' => 'fa-plus'],
        'editar' => ['label' => 'Editar', 'color' => '#b9770e', 'icon' => 'fa-edit'],
        'excluir' => ['label' => 'Excluir', 'color' => '#b5412f', 'icon' => 'fa-trash'],
        'ativar' => ['label' => 'Ativar', 'color' => '#3f7d4e', 'icon' => 'fa-user-check'],
        'desativar' => ['label' => 'Desativar', 'color' => '#b5412f', 'icon' => 'fa-user-slash'],
        'remover' => ['label' => 'Remover', 'color' => '#b5412f', 'icon' => 'fa-undo'],
        'registrar' => ['label' => 'Registrar', 'color' => '#8a6317', 'icon' => 'fa-save'],
        'aprovar' => ['label' => 'Aprovar', 'color' => '#3f7d4e', 'icon' => 'fa-check'],
        'cancelar' => ['label' => 'Cancelar', 'color' => '#b5412f', 'icon' => 'fa-times'],
        'visualizar' => ['label' => 'Visualizar', 'color' => '#7a5ca8', 'icon' => 'fa-eye'],
        'gerar_pdf' => ['label' => 'Gerar PDF', 'color' => '#b5412f', 'icon' => 'fa-file-pdf'],
        'exportar' => ['label' => 'Exportar', 'color' => '#16a34a', 'icon' => 'fa-file-excel'],
        'resetar_senha' => ['label' => 'Resetar Senha', 'color' => '#b9770e', 'icon' => 'fa-key'],
        'marcar_pago' => ['label' => 'Marcar Pago', 'color' => '#3f7d4e', 'icon' => 'fa-check-circle'],
        'desmarcar' => ['label' => 'Desmarcar', 'color' => '#b5412f', 'icon' => 'fa-undo-alt'],
    ];
    return $labels[$acao] ?? ['label' => ucfirst($acao), 'color' => '#6b7280', 'icon' => 'fa-circle'];
}

function getModuloLabel($modulo) {
    $labels = [
        'auth' => 'Autenticação',
        'dashboard' => 'Dashboard',
        'members' => 'Membros',
        'registry' => 'Registo Diário',
        'lists' => 'Listas',
        'year-end' => 'Fim de Ano',
        'reports' => 'Relatórios',
        'expenses' => 'Saídas/Despesas',
        'users' => 'Utilizadores',
        'audit' => 'Auditoria',
        'backups' => 'Backups',
        'profile' => 'Perfil'
    ];
    return $labels[$modulo] ?? ucfirst($modulo);
}
?>