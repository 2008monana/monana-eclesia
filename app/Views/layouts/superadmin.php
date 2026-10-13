<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$title = (string) ($title ?? 'Plataforma');
$active = (string) ($active ?? 'dashboard');
$actorName = (string) ($actorName ?? 'Superadmin');
?>
<!doctype html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#c99a2e">
    <?= csrf_meta() ?>
    <title><?= e($title) ?> — MonanaEclésia</title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style><?php readfile(BASE_PATH . '/assets/css/monana-theme.css'); ?>
        .platform-label{display:inline-flex;align-items:center;margin-left:9px;padding:4px 9px;border-radius:30px;background:var(--gold-50);color:var(--gold-700);font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
        .admin-table-wrap{overflow-x:auto}.admin-table{width:100%;border-collapse:collapse;font-size:12.5px}.admin-table th{text-align:left;color:var(--brown-500);font-size:10px;text-transform:uppercase;letter-spacing:.08em;padding:11px 9px;border-bottom:1px solid var(--line)}.admin-table td{padding:12px 9px;border-bottom:1px solid var(--line-2);vertical-align:top;color:var(--brown-700)}.admin-table tr:last-child td{border-bottom:0}.admin-table a{color:var(--gold-700);text-decoration:none;font-weight:600}.admin-table a:hover{text-decoration:underline}.admin-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}.admin-actions form{display:inline}.admin-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:8px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--brown-700);font:600 12px var(--font-ui);text-decoration:none;cursor:pointer}.admin-btn:hover{border-color:var(--gold-300);background:var(--gold-50)}.admin-btn.primary{border:0;background:var(--grad-gold);color:#fff}.admin-btn.danger{border-color:#f0cfc7;color:#a43122}.admin-btn.small{padding:6px 9px;font-size:11px}.admin-form{max-width:720px}.admin-form .field-group{margin-bottom:16px}.admin-form label{display:block;margin-bottom:7px;font-size:12px;font-weight:600;color:var(--brown-800)}.admin-form input,.admin-form select,.admin-form textarea{width:100%;min-height:42px;padding:10px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--brown-900);font-size:13px}.admin-form textarea{min-height:88px;resize:vertical}.admin-form .hint{margin-top:5px;color:var(--brown-500);font-size:11.5px;line-height:1.5}.admin-form .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.admin-form .form-grid .field-group{margin-bottom:2px}.status-pill{display:inline-block;padding:4px 8px;border-radius:20px;background:var(--gold-50);color:var(--gold-700);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}.status-pill.ok{background:#eaf3ec;color:#3f7d4e}.status-pill.bad{background:#fbebe7;color:#b5412f}.status-pill.pending{background:#fbf3df;color:#8a6317}.empty-note{padding:18px;color:var(--brown-500);font-size:13px}.panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.panel-head h3{margin:0}.breadcrumb{margin-bottom:18px;font-size:12px;color:var(--brown-500)}.breadcrumb a{color:var(--gold-700);text-decoration:none}.content{max-width:1600px}.danger-zone{padding:16px;border:1px solid #f0cfc7;border-radius:10px;background:#fffafa}.inline-notice{margin-top:12px}
        @media(max-width:768px){.admin-form .form-grid{grid-template-columns:1fr}.admin-table{min-width:700px}.platform-label{display:none}}
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="sb-brand">
            <img src="<?= e(url('assets/img/logo-mark.png')) ?>" alt="">
            <div class="wordmark">MONANA<b>ECLÉSIA</b></div>
        </div>
        <nav class="sb-nav" aria-label="Navegação da plataforma">
            <div class="sb-label">Plataforma</div>
            <a class="sb-item<?= $active === 'dashboard' ? ' active' : '' ?>" href="<?= e(url('superadmin')) ?>"><span class="ic"><i class="fas fa-chart-line"></i></span>Resumo</a>
            <a class="sb-item<?= $active === 'igrejas' ? ' active' : '' ?>" href="<?= e(url('superadmin/igrejas')) ?>"><span class="ic"><i class="fas fa-church"></i></span>Igrejas</a>
            <a class="sb-item<?= $active === 'planos' ? ' active' : '' ?>" href="<?= e(url('superadmin/planos')) ?>"><span class="ic"><i class="fas fa-layer-group"></i></span>Planos</a>
            <div class="sb-label">Conta</div>
            <a class="sb-item" href="<?= e(url('trocar-senha')) ?>"><span class="ic"><i class="fas fa-key"></i></span>Alterar senha</a>
            <form method="POST" action="<?= e(url('sessoes/terminar-todas')) ?>"><?= csrf_field() ?><button class="sb-item" type="submit" style="width:100%;border:0;background:transparent;text-align:left"><span class="ic"><i class="fas fa-shield-halved"></i></span>Terminar sessões</button></form>
            <form method="POST" action="<?= e(url('sair')) ?>">
                <?= csrf_field() ?>
                <button class="sb-item logout-btn" type="submit" style="width:100%;border:0;background:transparent;text-align:left"><span class="ic"><i class="fas fa-right-from-bracket"></i></span>Sair</button>
            </form>
        </nav>
        <div class="sb-foot"><div class="sb-church"><div class="av"><i class="fas fa-shield-halved"></i></div><div><div class="nm"><?= e($actorName) ?></div><div class="sub">Superadmin · Plataforma</div></div></div></div>
    </aside>
    <main class="main">
        <header class="topbar">
            <div class="left"><span>MonanaEclésia</span><span class="platform-label">Plataforma</span></div>
            <div class="right"><div class="who"><div class="av2"><?= e(strtoupper(substr($actorName, 0, 1))) ?></div><div class="user-info"><span class="t1"><?= e($actorName) ?></span><span class="t2">Superadmin</span></div></div></div>
        </header>
        <section class="content">
            <?= $content ?>
            <div class="footer-note">MonanaEclésia &middot; Plataforma integrada de gestão de igrejas &middot; Lei n.º 22/11 de Angola</div>
        </section>
    </main>
</div>
</body>
</html>
