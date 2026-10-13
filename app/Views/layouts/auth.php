<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$title = (string) ($title ?? 'Autenticação');
$single = !empty($single);
$content = (string) ($content ?? '');
?>
<!doctype html>
<html lang="pt-AO">
<head>
    <?php include BASE_PATH . '/app/Views/partials/document-head.php'; ?>
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
