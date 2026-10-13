<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$isPlatform = $area === 'superadmin';
$sectionLabel = $isPlatform ? 'MonanaEclésia' : 'Área da igreja';
?>
<header class="topbar">
    <div class="left">
        <button class="burger" id="sidebarToggle" type="button" aria-controls="appSidebar" aria-expanded="false" aria-label="Abrir menu">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
        <span class="area-label"><?= e($sectionLabel) ?></span>
        <?php if ($isPlatform): ?>
            <span class="platform-label">Plataforma</span>
        <?php else: ?>
            <span class="tenant-tag" title="<?= e($tenantName) ?>"><?= e($tenantName) ?></span>
        <?php endif; ?>
    </div>
    <div class="right">
        <div class="who" role="group" aria-label="<?= e($actorName . ', ' . $actorRoleLabel) ?>">
            <div class="av2" aria-hidden="true"><?= e($actorInitial) ?></div>
            <div class="user-info">
                <span class="t1"><?= e($actorName) ?></span>
                <span class="t2"><?= e($actorRoleLabel) ?></span>
            </div>
        </div>
    </div>
</header>
