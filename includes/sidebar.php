<?php
// includes/sidebar.php
// Este arquivo contém o sidebar fixo que será incluído em todas as páginas
// Certifique-se de que o arquivo config/url.php já foi incluído
require_once __DIR__ . '/../config/session.php';

// Rota actual (sem o prefixo APP_BASE nem query string), usada para
// assinalar o item ativo no menu. Ex.: /monana-eclesia/members -> "members"
$__uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$__base = trim(preg_replace('#^https?://[^/]+#', '', defined('APP_BASE') ? APP_BASE : ''), '/');
if ($__base !== '' && strpos(trim($__uri, '/'), $__base . '/') === 0) {
    $__uri = substr(trim($__uri, '/'), strlen($__base) + 1);
}
$GLOBALS['__rota_atual'] = trim($__uri, '/');
function rotaAtual() { return $GLOBALS['__rota_atual'] ?? ''; }
?>
<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <img src="<?= url('assets/img/logo-mark.png') ?>" alt="MonanaEclésia">
        <div class="wordmark">MONANA<b>ECLÉSIA</b></div>
    </div>

    <nav class="sb-nav">
        <div class="sb-label">Gestão</div>
        <!-- Dashboard -->
        <?php if (podeAcessarModulo('dashboard')): ?>
        <a href="<?= url('modules/dashboard/index.php') ?>" class="sb-item <?= strpos(rotaAtual(), 'dashboard') === 0 ? 'active' : '' ?>">
            <span class="ic"><i class="fas fa-home"></i></span> Dashboard
        </a>
        <?php endif; ?>

        <!-- Membros -->
        <?php if (podeAcessarModulo('members')): ?>
        <a href="<?= url('modules/members/index.php') ?>" class="sb-item <?= strpos(rotaAtual(), 'members') === 0 ? 'active' : '' ?>">
            <span class="ic"><i class="fas fa-users"></i></span> Membros
        </a>
        <?php endif; ?>

        <!-- Registo Diário -->
        <?php if (podeAcessarModulo('registry')): ?>
        <a href="<?= url('modules/registry/index.php') ?>" class="sb-item <?= strpos(rotaAtual(), 'registry') === 0 ? 'active' : '' ?>">
            <span class="ic"><i class="fas fa-calendar-day"></i></span> Registo Diário
        </a>
        <?php endif; ?>

        <!-- ==========================================
        LISTAS
        ========================================== -->
        <?php if (podeAcessarModulo('lists')): ?>
        <?php 
        $is_lists = strpos(rotaAtual(), 'lists') === 0;
        $is_lista_todos = (rotaAtual() === 'lists' || rotaAtual() === 'lists/index');
        $is_lista_mamas = strpos(rotaAtual(), 'lists/mamas') === 0;
        $is_lista_papas = strpos(rotaAtual(), 'lists/papas') === 0;
        $is_lista_jovens = strpos(rotaAtual(), 'lists/jovens') === 0;
        ?>
        <div class="sb-group <?= $is_lists ? 'open' : '' ?>">
            <div class="sb-item <?= $is_lists ? 'active' : '' ?>" style="display:flex;align-items:center;justify-content:space-between;">
                <span style="display:flex;align-items:center;gap:12px;flex:1;" onclick="window.location.href='<?= url('modules/lists/index.php') ?>'">
                    <span class="ic"><i class="fas fa-file-alt"></i></span> Listas
                </span>
                <span class="chev" onclick="event.stopPropagation(); this.closest('.sb-group').classList.toggle('open');" style="cursor:pointer;padding:0 8px;">
                    <i class="fas fa-chevron-right"></i>
                </span>
            </div>
            <div class="sb-sub">
                <div onclick="window.location.href='<?= url('modules/lists/index.php') ?>'" class="<?= $is_lista_todos ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> Todos os Membros
                </div>
                <div onclick="window.location.href='<?= url('modules/lists/mamas.php') ?>'" class="<?= $is_lista_mamas ? 'active' : '' ?>">
                    <i class="fas fa-female"></i> Lista de Mamãs
                </div>
                <div onclick="window.location.href='<?= url('modules/lists/papas.php') ?>'" class="<?= $is_lista_papas ? 'active' : '' ?>">
                    <i class="fas fa-male"></i> Lista de Papás
                </div>
                <div onclick="window.location.href='<?= url('modules/lists/jovens.php') ?>'" class="<?= $is_lista_jovens ? 'active' : '' ?>">
                    <i class="fas fa-user-graduate"></i> Lista de Jovens
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Contribuição Fim do Ano -->
        <?php if (podeAcessarModulo('year_end')): ?>
        <a href="<?= url('modules/year-end/index.php') ?>" class="sb-item <?= strpos(rotaAtual(), 'year-end') === 0 ? 'active' : '' ?>">
            <span class="ic"><i class="fas fa-gift"></i></span> Contribuição Fim do Ano
        </a>
        <?php endif; ?>

        <!-- ==========================================
        RELATÓRIOS
        ========================================== -->
        <?php if (podeAcessarModulo('reports')): ?>
        <?php 
        $is_reports = strpos(rotaAtual(), 'reports') === 0;
        $is_financeiro = (rotaAtual() === 'reports' || rotaAtual() === 'reports/index');
        $is_anual = strpos(rotaAtual(), 'reports/annual') === 0;
        $is_rel_saidas = strpos(rotaAtual(), 'reports/expenses') === 0;
        ?>
        <div class="sb-group <?= $is_reports ? 'open' : '' ?>" onclick="this.classList.toggle('open')">
            <div class="sb-item <?= $is_reports ? 'active' : '' ?>">
                <span class="ic"><i class="fas fa-chart-bar"></i></span> Relatórios <span class="chev"><i class="fas fa-chevron-right"></i></span>
            </div>
            <div class="sb-sub">
                <div onclick="window.location.href='<?= url('modules/reports/index.php') ?>'" class="<?= $is_financeiro ? 'active' : '' ?>">
                    <i class="fas fa-file-pdf"></i> Relatório Financeiro
                </div>
                <div onclick="window.location.href='<?= url('modules/reports/annual.php') ?>'" class="<?= $is_anual ? 'active' : '' ?>">
                    <i class="fas fa-file-pdf"></i> Relatório Anual
                </div>
                <div onclick="window.location.href='<?= url('modules/reports/expenses.php') ?>'" class="<?= $is_rel_saidas ? 'active' : '' ?>">
                    <i class="fas fa-file-pdf"></i> Relatório de Saídas
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ==========================================
        SAÍDAS/DESPESAS
        ========================================== -->
        <?php if (podeAcessarModulo('expenses')): ?>
        <?php 
        // Verifica se está no módulo de saídas (NÃO no relatório de saídas)
        $is_expenses = strpos(rotaAtual(), 'expenses') === 0;
        ?>
        <a href="<?= url('modules/expenses/index.php') ?>" class="sb-item <?= $is_expenses ? 'active' : '' ?>">
            <span class="ic"><i class="fas fa-money-bill-wave"></i></span> Saídas/Despesas
        </a>
        <?php endif; ?>
        <div class="sb-label">Conta</div>
        <!-- Meu Perfil -->
        <a href="<?= url('modules/profile/index.php') ?>" class="sb-item <?= strpos(rotaAtual(), 'profile') === 0 ? 'active' : '' ?>">
            <span class="ic"><i class="fas fa-user-cog"></i></span> Meu Perfil
        </a>

        <?php if ($_SESSION['user_perfil'] == 'admin'): ?>
        <div class="sb-label">Administração</div>
        <!-- Utilizadores (APENAS ADMIN) -->
        <a href="<?= url('modules/users/index.php') ?>" class="sb-item <?= strpos(rotaAtual(), 'users') === 0 ? 'active' : '' ?>">
            <span class="ic"><i class="fas fa-user-cog"></i></span> Utilizadores
        </a>

        <!-- Auditoria (APENAS ADMIN) -->
        <a href="<?= url('modules/audit/index.php') ?>" class="sb-item <?= strpos(rotaAtual(), 'audit') === 0 ? 'active' : '' ?>">
            <span class="ic"><i class="fas fa-shield-alt"></i></span> Auditoria
        </a>

        <!-- Backups (APENAS ADMIN) -->
        <a href="<?= url('modules/backups/index.php') ?>" class="sb-item <?= strpos(rotaAtual(), 'backups') === 0 ? 'active' : '' ?>">
            <span class="ic"><i class="fas fa-database"></i></span> Backups
        </a>
        <?php endif; ?>

        <!-- Sair -->
        <a href="<?= url('modules/auth/logout.php') ?>" class="sb-item logout-btn logout-trigger">
            <span class="ic"><i class="fas fa-sign-out-alt"></i></span> Sair
        </a>
    </nav>

    <div class="sb-foot">
        <div class="sb-church">
            <div class="av"><?= strtoupper(substr($_SESSION['user_nome'], 0, 2)) ?></div>
            <div>
                <div class="nm"><?= htmlspecialchars($_SESSION['user_nome']) ?></div>
                <div class="sub"><?= htmlspecialchars($_SESSION['user_perfil']) ?> · Calemba 2</div>
            </div>
        </div>
    </div>
</aside>