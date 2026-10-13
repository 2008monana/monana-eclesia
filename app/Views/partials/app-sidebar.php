<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$isPlatform = $area === 'superadmin';
$navigationLabel = $isPlatform ? 'Navegação da plataforma' : 'Navegação da igreja';
?>
<aside class="sidebar" id="appSidebar" aria-label="Menu principal">
    <div class="sb-brand">
        <img src="<?= e(url('assets/img/logo-mark.png')) ?>" alt="">
        <div class="wordmark">MONANA<b>ECLÉSIA</b></div>
    </div>
    <nav class="sb-nav" aria-label="<?= e($navigationLabel) ?>">
        <?php if ($isPlatform): ?>
            <div class="sb-label">Plataforma</div>
            <a class="sb-item<?= $active === 'dashboard' ? ' active' : '' ?>" href="<?= e(url('superadmin')) ?>"<?= $active === 'dashboard' ? ' aria-current="page"' : '' ?>>
                <span class="ic"><i class="fas fa-chart-line" aria-hidden="true"></i></span>Resumo
            </a>
            <a class="sb-item<?= $active === 'igrejas' ? ' active' : '' ?>" href="<?= e(url('superadmin/igrejas')) ?>"<?= $active === 'igrejas' ? ' aria-current="page"' : '' ?>>
                <span class="ic"><i class="fas fa-church" aria-hidden="true"></i></span>Igrejas
            </a>
            <a class="sb-item<?= $active === 'planos' ? ' active' : '' ?>" href="<?= e(url('superadmin/planos')) ?>"<?= $active === 'planos' ? ' aria-current="page"' : '' ?>>
                <span class="ic"><i class="fas fa-layer-group" aria-hidden="true"></i></span>Planos
            </a>
        <?php else: ?>
            <div class="sb-label">Igreja</div>
            <a class="sb-item<?= $active === 'painel' ? ' active' : '' ?>" href="<?= e(url('painel')) ?>"<?= $active === 'painel' ? ' aria-current="page"' : '' ?>>
                <span class="ic"><i class="fas fa-house" aria-hidden="true"></i></span>Painel
            </a>
            <div class="sb-label">Módulos · Fase 5</div>
            <span class="sb-item is-disabled" aria-disabled="true">
                <span class="ic"><i class="fas fa-users" aria-hidden="true"></i></span>Membros
                <span class="nav-phase-badge">Em migração</span>
            </span>
            <span class="sb-item is-disabled" aria-disabled="true">
                <span class="ic"><i class="fas fa-book-open" aria-hidden="true"></i></span>Cotas e contribuições
                <span class="nav-phase-badge">Em migração</span>
            </span>
            <span class="sb-item is-disabled" aria-disabled="true">
                <span class="ic"><i class="fas fa-chart-pie" aria-hidden="true"></i></span>Relatórios
                <span class="nav-phase-badge">Em migração</span>
            </span>
            <div class="sb-note">Estes módulos serão activados depois da migração segura para cada igreja.</div>
        <?php endif; ?>

        <div class="sb-label">Conta</div>
        <a class="sb-item" href="<?= e(url('trocar-senha')) ?>">
            <span class="ic"><i class="fas fa-key" aria-hidden="true"></i></span>Alterar senha
        </a>
        <form method="POST" action="<?= e(url('sessoes/terminar-todas')) ?>">
            <?= csrf_field() ?>
            <button class="sb-item" type="submit">
                <span class="ic"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>Terminar sessões
            </button>
        </form>
        <form method="POST" action="<?= e(url('sair')) ?>">
            <?= csrf_field() ?>
            <button class="sb-item logout-btn" type="submit">
                <span class="ic"><i class="fas fa-right-from-bracket" aria-hidden="true"></i></span>Sair
            </button>
        </form>
    </nav>
    <div class="sb-foot">
        <div class="sb-church">
            <div class="av"><i class="fas <?= $isPlatform ? 'fa-shield-halved' : 'fa-church' ?>" aria-hidden="true"></i></div>
            <div>
                <div class="nm"><?= e($sidebarIdentity) ?></div>
                <div class="sub"><?= e($actorRoleLabel) ?><?= $isPlatform ? ' · Plataforma' : '' ?></div>
            </div>
        </div>
    </div>
</aside>
