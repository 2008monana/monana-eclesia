<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$title = (string) ($title ?? 'Autenticação');
$single = !empty($single);
?>
<!doctype html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c99a2e">
    <?= csrf_meta() ?>
    <title><?= e($title) ?> — MonanaEclésia</title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style><?php readfile(BASE_PATH . '/assets/css/monana-theme.css'); ?></style>
</head>
<body>
<div class="auth<?= $single ? ' single' : '' ?>">
    <?php if (!$single): ?>
        <aside class="auth-brand">
            <img class="logo" src="<?= e(url('assets/img/logo.png')) ?>" alt="MonanaEclésia — Plataforma Integrada de Gestão de Igrejas">
            <p class="tagline">Organizar para servir melhor.</p>
            <div class="legal">&copy; <?= e(date('Y')) ?> MonanaEclésia<br>Plataforma Integrada de Gestão de Igrejas.</div>
        </aside>
    <?php endif; ?>
    <main class="auth-panel">
        <div class="auth-card">
            <img class="mark-sm" src="<?= e(url('assets/img/logo.png')) ?>" alt="MonanaEclésia">
            <?= $content ?>
            <div class="auth-foot">MonanaEclésia &middot; Plataforma de gestão de igrejas<br>Lei n.º 22/11 de Angola &middot; Protecção de dados pessoais</div>
        </div>
    </main>
</div>
</body>
</html>
