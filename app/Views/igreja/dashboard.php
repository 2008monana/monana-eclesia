<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$churchName = (string) ($churchName ?? 'A tua igreja');
?>
<div class="page-head">
    <div>
        <h2><i class="fas fa-church" aria-hidden="true"></i>Bem-vindo(a), <?= e($churchName) ?></h2>
        <div class="sub">A tua conta está activa e associada exclusivamente a esta igreja.</div>
    </div>
</div>
<div class="panel">
    <h3><i class="fas fa-shield-halved" aria-hidden="true"></i>Preparação da área de gestão</h3>
    <div class="phase-notice" role="status">
        <strong>Os módulos de gestão ainda não foram migrados para o modelo multi-igreja.</strong><br>
        Por segurança, esta área não apresenta nem altera dados legados enquanto a migração por igreja não estiver concluída. A migração dos módulos actuais está prevista na Fase 5. O tenant desta sessão é obtido da autenticação, nunca do endereço ou de um formulário.
    </div>
    <div class="admin-actions dashboard-actions">
        <a class="admin-btn" href="<?= e(url('trocar-senha')) ?>"><i class="fas fa-key" aria-hidden="true"></i>Alterar senha</a>
        <span class="status-pill ok">Conta verificada</span>
    </div>
</div>
