<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$user = is_array($user ?? null) ? $user : [];
$churchName = (string) ($user['igreja_nome'] ?? 'A tua igreja');
$userName = (string) ($user['nome_completo'] ?? 'Utilizador');
?>
<!doctype html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#c99a2e">
    <?= csrf_meta() ?>
    <title>Painel da igreja — MonanaEclésia</title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style><?php readfile(BASE_PATH . '/assets/css/monana-theme.css'); ?>
        .phase-notice{padding:20px;border:1px solid var(--gold-200);border-radius:var(--radius-lg);background:var(--gold-50);color:var(--brown-700);line-height:1.7}.phase-notice strong{color:var(--brown-900)}
        .tenant-tag{display:inline-block;margin-left:8px;padding:4px 9px;border-radius:20px;background:var(--gold-50);color:var(--gold-700);font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
        .content{max-width:1200px}
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="sb-brand"><img src="<?= e(url('assets/img/logo-mark.png')) ?>" alt=""><div class="wordmark">MONANA<b>ECLÉSIA</b></div></div>
        <nav class="sb-nav"><div class="sb-label">Igreja</div><a class="sb-item active" href="<?= e(url('painel')) ?>"><span class="ic"><i class="fas fa-house"></i></span>Painel</a><div class="sb-label">Conta</div><a class="sb-item" href="<?= e(url('trocar-senha')) ?>"><span class="ic"><i class="fas fa-key"></i></span>Alterar senha</a>
            <form method="POST" action="<?= e(url('sessoes/terminar-todas')) ?>"><?= csrf_field() ?><button class="sb-item" style="width:100%;border:0;background:transparent;text-align:left" type="submit"><span class="ic"><i class="fas fa-shield-halved"></i></span>Terminar sessões</button></form>
            <form method="POST" action="<?= e(url('sair')) ?>"><?= csrf_field() ?><button class="sb-item logout-btn" style="width:100%;border:0;background:transparent;text-align:left" type="submit"><span class="ic"><i class="fas fa-right-from-bracket"></i></span>Sair</button></form>
        </nav>
        <div class="sb-foot"><div class="sb-church"><div class="av"><?= e(strtoupper(substr($churchName, 0, 1))) ?></div><div><div class="nm"><?= e($churchName) ?></div><div class="sub"><?= e($user['papel'] ?? 'Utilizador') ?></div></div></div></div>
    </aside>
    <main class="main">
        <header class="topbar"><div class="left"><span>Área da igreja</span><span class="tenant-tag">Tenant #<?= e((int) ($tenantId ?? 0)) ?></span></div><div class="right"><div class="who"><div class="av2"><?= e(strtoupper(substr($userName, 0, 1))) ?></div><div class="user-info"><span class="t1"><?= e($userName) ?></span><span class="t2"><?= e($user['papel'] ?? '') ?></span></div></div></div></header>
        <section class="content">
            <div class="page-head"><div><h2><i class="fas fa-church" style="color:var(--gold-500)"></i>Bem-vindo(a), <?= e($churchName) ?></h2><div class="sub">A tua conta está activa e associada exclusivamente a esta igreja.</div></div></div>
            <div class="panel"><h3><i class="fas fa-shield-halved"></i>Preparação da área de gestão</h3>
                <div class="phase-notice"><strong>Os módulos de gestão ainda não foram migrados para o modelo multi-igreja.</strong><br>Por segurança, esta área não apresenta nem altera dados legados enquanto a migração por igreja não estiver concluída. A migração dos módulos actuais está prevista na Fase 5. O tenant desta sessão é obtido da autenticação, nunca do endereço ou de um formulário.</div>
                <div style="margin-top:20px" class="admin-actions"><a class="admin-btn" href="<?= e(url('trocar-senha')) ?>"><i class="fas fa-key"></i>Alterar senha</a><span class="status-pill ok">Conta verificada</span></div>
            </div>
            <div class="footer-note">MonanaEclésia &middot; <?= e($churchName) ?> &middot; Protecção de dados pessoais — Lei n.º 22/11 de Angola</div>
        </section>
    </main>
</div>
</body>
</html>
