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
            <?php if (in_array('members', $allowedModules, true) && $membersEnabled): ?>
                <a class="sb-item<?= $active === 'membros' ? ' active' : '' ?>" href="<?= e(url('membros')) ?>"<?= $active === 'membros' ? ' aria-current="page"' : '' ?>>
                    <span class="ic"><i class="fas fa-users" aria-hidden="true"></i></span>Membros
                </a>
            <?php else: ?>
                <span class="sb-item is-disabled" aria-disabled="true">
                    <span class="ic"><i class="fas fa-users" aria-hidden="true"></i></span>Membros
                    <span class="nav-phase-badge"><?= in_array('members', $allowedModules, true) ? 'Em migração' : 'Sem acesso' ?></span>
                </span>
            <?php endif; ?>
            <?php if (in_array('registry', $allowedModules, true) && $registryEnabled): ?>
                <a class="sb-item<?= $active === 'cotas' ? ' active' : '' ?>" href="<?= e(url('cotas')) ?>"<?= $active === 'cotas' ? ' aria-current="page"' : '' ?>>
                    <span class="ic"><i class="fas fa-book-open" aria-hidden="true"></i></span>Cotas
                </a>
            <?php else: ?>
                <span class="sb-item is-disabled" aria-disabled="true">
                    <span class="ic"><i class="fas fa-book-open" aria-hidden="true"></i></span>Cotas
                    <span class="nav-phase-badge"><?= in_array('registry', $allowedModules, true) ? 'Em migração' : 'Sem acesso' ?></span>
                </span>
            <?php endif; ?>
            <?php if (in_array('lists', $allowedModules, true) && $listsEnabled): ?>
                <a class="sb-item<?= $active === 'listas' ? ' active' : '' ?>" href="<?= e(url('listas')) ?>"<?= $active === 'listas' ? ' aria-current="page"' : '' ?>>
                    <span class="ic"><i class="fas fa-list-check" aria-hidden="true"></i></span>Listas
                </a>
            <?php else: ?>
                <span class="sb-item is-disabled" aria-disabled="true">
                    <span class="ic"><i class="fas fa-list-check" aria-hidden="true"></i></span>Listas
                    <span class="nav-phase-badge"><?= in_array('lists', $allowedModules, true) ? 'Em migração' : 'Sem acesso' ?></span>
                </span>
            <?php endif; ?>
            <?php if (in_array('expenses', $allowedModules, true) && $expensesEnabled): ?>
                <a class="sb-item<?= $active === 'saidas' ? ' active' : '' ?>" href="<?= e(url('saidas')) ?>"<?= $active === 'saidas' ? ' aria-current="page"' : '' ?>>
                    <span class="ic"><i class="fas fa-arrow-trend-down" aria-hidden="true"></i></span>Saídas
                </a>
            <?php else: ?>
                <span class="sb-item is-disabled" aria-disabled="true">
                    <span class="ic"><i class="fas fa-arrow-trend-down" aria-hidden="true"></i></span>Saídas
                    <span class="nav-phase-badge"><?= in_array('expenses', $allowedModules, true) ? 'Em migração' : 'Sem acesso' ?></span>
                </span>
            <?php endif; ?>
            <?php if (in_array('year_end', $allowedModules, true) && $yearEndEnabled): ?>
                <a class="sb-item<?= $active === 'fim-ano' ? ' active' : '' ?>" href="<?= e(url('contribuicoes-fim-de-ano')) ?>"<?= $active === 'fim-ano' ? ' aria-current="page"' : '' ?>>
                    <span class="ic"><i class="fas fa-gift" aria-hidden="true"></i></span>Fim de ano
                </a>
            <?php else: ?>
                <span class="sb-item is-disabled" aria-disabled="true">
                    <span class="ic"><i class="fas fa-gift" aria-hidden="true"></i></span>Fim de ano
                    <span class="nav-phase-badge"><?= in_array('year_end', $allowedModules, true) ? 'Em migração' : 'Sem acesso' ?></span>
                </span>
            <?php endif; ?>
            <?php if (in_array('reports', $allowedModules, true) && $reportsEnabled): ?>
                <a class="sb-item<?= $active === 'relatorios' ? ' active' : '' ?>" href="<?= e(url('relatorios')) ?>"<?= $active === 'relatorios' ? ' aria-current="page"' : '' ?>>
                    <span class="ic"><i class="fas fa-chart-pie" aria-hidden="true"></i></span>Relatórios
                </a>
            <?php else: ?>
                <span class="sb-item is-disabled" aria-disabled="true">
                    <span class="ic"><i class="fas fa-chart-pie" aria-hidden="true"></i></span>Relatórios
                    <span class="nav-phase-badge"><?= in_array('reports', $allowedModules, true) ? 'Em migração' : 'Sem acesso' ?></span>
                </span>
            <?php endif; ?>
            <?php if (in_array('users', $allowedModules, true) && $usersEnabled): ?>
                <a class="sb-item<?= $active === 'utilizadores' ? ' active' : '' ?>" href="<?= e(url('utilizadores')) ?>"<?= $active === 'utilizadores' ? ' aria-current="page"' : '' ?>>
                    <span class="ic"><i class="fas fa-users-gear" aria-hidden="true"></i></span>Utilizadores
                </a>
            <?php else: ?>
                <span class="sb-item is-disabled" aria-disabled="true">
                    <span class="ic"><i class="fas fa-users-gear" aria-hidden="true"></i></span>Utilizadores
                    <span class="nav-phase-badge"><?= in_array('users', $allowedModules, true) ? 'Em migração' : 'Sem acesso' ?></span>
                </span>
            <?php endif; ?>
            <?php if (in_array('audit', $allowedModules, true) && $auditEnabled): ?>
                <a class="sb-item<?= $active === 'auditoria' ? ' active' : '' ?>" href="<?= e(url('auditoria')) ?>"<?= $active === 'auditoria' ? ' aria-current="page"' : '' ?>>
                    <span class="ic"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>Auditoria
                </a>
            <?php else: ?>
                <span class="sb-item is-disabled" aria-disabled="true">
                    <span class="ic"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>Auditoria
                    <span class="nav-phase-badge"><?= in_array('audit', $allowedModules, true) ? 'Em migração' : 'Sem acesso' ?></span>
                </span>
            <?php endif; ?>
            <?php if (in_array('backups', $allowedModules, true) && $backupsEnabled): ?>
                <a class="sb-item<?= $active === 'backups' ? ' active' : '' ?>" href="<?= e(url('copias-de-seguranca')) ?>"<?= $active === 'backups' ? ' aria-current="page"' : '' ?>>
                    <span class="ic"><i class="fas fa-box-archive" aria-hidden="true"></i></span>Backups
                </a>
            <?php else: ?>
                <span class="sb-item is-disabled" aria-disabled="true">
                    <span class="ic"><i class="fas fa-box-archive" aria-hidden="true"></i></span>Backups
                    <span class="nav-phase-badge"><?= in_array('backups', $allowedModules, true) ? 'Em migração' : 'Sem acesso' ?></span>
                </span>
            <?php endif; ?>
            <?php if (in_array('settings', $allowedModules, true) && $settingsEnabled): ?>
                <a class="sb-item<?= $active === 'configuracoes' ? ' active' : '' ?>" href="<?= e(url('configuracoes')) ?>"<?= $active === 'configuracoes' ? ' aria-current="page"' : '' ?>>
                    <span class="ic"><i class="fas fa-sliders" aria-hidden="true"></i></span>Configurações
                </a>
            <?php else: ?>
                <span class="sb-item is-disabled" aria-disabled="true">
                    <span class="ic"><i class="fas fa-sliders" aria-hidden="true"></i></span>Configurações
                    <span class="nav-phase-badge"><?= in_array('settings', $allowedModules, true) ? 'Em migração' : 'Sem acesso' ?></span>
                </span>
            <?php endif; ?>
            <div class="sb-note">Os módulos geridos só ficam activos após validação tenant e autorização da igreja.</div>
        <?php endif; ?>

        <div class="sb-label">Conta</div>
        <?php if (!$isPlatform && in_array('profile', $allowedModules, true) && $profileEnabled): ?>
            <a class="sb-item<?= $active === 'perfil' ? ' active' : '' ?>" href="<?= e(url('perfil')) ?>"<?= $active === 'perfil' ? ' aria-current="page"' : '' ?>>
                <span class="ic"><i class="fas fa-user-circle" aria-hidden="true"></i></span>Meu perfil
            </a>
        <?php endif; ?>
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
