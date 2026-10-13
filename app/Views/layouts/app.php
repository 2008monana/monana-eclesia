<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$title = (string) ($title ?? 'Painel');
$area = isset($area) && $area === 'superadmin' ? 'superadmin' : 'igreja';
$active = (string) ($active ?? ($area === 'superadmin' ? 'dashboard' : 'painel'));
$actorName = trim((string) ($actorName ?? ($area === 'superadmin' ? 'Superadmin' : 'Utilizador')));
if ($actorName === '') {
    $actorName = $area === 'superadmin' ? 'Superadmin' : 'Utilizador';
}
$actorRole = (string) ($actorRole ?? ($area === 'superadmin' ? 'superadmin' : ''));
$tenantName = trim((string) ($tenantName ?? 'A tua igreja'));
if ($tenantName === '') {
    $tenantName = 'A tua igreja';
}
$roleLabels = [
    'superadmin' => 'Superadmin',
    'admin_igreja' => 'Administração',
    'pastor' => 'Pastorado',
    'tesoureiro' => 'Tesouraria',
    'gestor' => 'Gestão',
];
$actorRoleLabel = isset($roleLabels[$actorRole]) ? $roleLabels[$actorRole] : 'Utilizador';
$sidebarIdentity = $area === 'superadmin' ? $actorName : $tenantName;
$actorInitial = 'M';
if (preg_match('/^./u', $actorName, $initialMatch) === 1 && isset($initialMatch[0])) {
    $actorInitial = strtoupper($initialMatch[0]);
}
$content = (string) ($content ?? '');
?>
<!doctype html>
<html lang="pt-AO">
<head>
    <?php include BASE_PATH . '/app/Views/partials/document-head.php'; ?>
    <style><?php include BASE_PATH . '/app/Views/partials/app-styles.php'; ?></style>
</head>
<body class="area-<?= e($area) ?>">
<a class="skip-link" href="#main-content">Saltar para o conteúdo principal</a>
<div class="app">
    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
    <?php include BASE_PATH . '/app/Views/partials/app-sidebar.php'; ?>
    <main class="main">
        <?php include BASE_PATH . '/app/Views/partials/app-topbar.php'; ?>
        <section class="content" id="main-content" tabindex="-1">
            <?= $content ?>
            <?php include BASE_PATH . '/app/Views/partials/app-footer.php'; ?>
        </section>
    </main>
</div>
<?php include BASE_PATH . '/app/Views/partials/app-scripts.php'; ?>
</body>
</html>
