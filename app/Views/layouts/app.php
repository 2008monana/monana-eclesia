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
$allowedModules = is_array($allowedModules ?? null) ? array_values(array_unique(array_map('strval', $allowedModules))) : [];
$membersEnabled = isset($membersEnabled)
    ? (bool) $membersEnabled
    : \App\Core\Config::bool('MEMBERS_MVC_ENABLED', false);
$registryEnabled = isset($registryEnabled)
    ? (bool) $registryEnabled
    : \App\Core\Config::bool('COTAS_MVC_ENABLED', false);
$listsEnabled = isset($listsEnabled)
    ? (bool) $listsEnabled
    : \App\Core\Config::bool('LISTS_MVC_ENABLED', false);
$expensesEnabled = isset($expensesEnabled)
    ? (bool) $expensesEnabled
    : \App\Core\Config::bool('EXPENSES_MVC_ENABLED', false);
$yearEndEnabled = isset($yearEndEnabled)
    ? (bool) $yearEndEnabled
    : \App\Core\Config::bool('YEAR_END_MVC_ENABLED', false);
$reportsEnabled = isset($reportsEnabled)
    ? (bool) $reportsEnabled
    : \App\Core\Config::bool('REPORTS_MVC_ENABLED', false);
$usersEnabled = isset($usersEnabled)
    ? (bool) $usersEnabled
    : \App\Core\Config::bool('USERS_MVC_ENABLED', false);
$auditEnabled = isset($auditEnabled)
    ? (bool) $auditEnabled
    : \App\Core\Config::bool('AUDIT_MVC_ENABLED', false);
$backupsEnabled = isset($backupsEnabled)
    ? (bool) $backupsEnabled
    : \App\Core\Config::bool('BACKUPS_MVC_ENABLED', false);
$profileEnabled = isset($profileEnabled)
    ? (bool) $profileEnabled
    : \App\Core\Config::bool('PROFILE_MVC_ENABLED', false);
$settingsEnabled = isset($settingsEnabled)
    ? (bool) $settingsEnabled
    : \App\Core\Config::bool('SETTINGS_MVC_ENABLED', false);
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
