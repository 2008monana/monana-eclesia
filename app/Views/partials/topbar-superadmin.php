<?php
/** @var array $supervisor */

$iniciais = iniciais($supervisor['nome'] ?? '');
?>
<header class="sa-topbar">
    <button class="sa-icon-btn sa-menu-toggle" id="sa-menu-toggle" aria-label="Abrir menu">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>

    <div class="sa-brand">
        <?= emblem(38) ?>
        <div class="sa-brand-text">
            <div class="sa-brand-title">
                MONANA <span>ECLESIA</span>
                <span class="sa-badge">ADMIN</span>
            </div>
            <div class="sa-brand-sub">PAINEL DA PLATAFORMA</div>
        </div>
    </div>

    <div class="sa-search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="text" id="sa-global-search" placeholder="Pesquisar igreja, supervisor...">
    </div>

    <div class="sa-actions">
        <a href="<?= esc(url('superadmin/auditoria')) ?>" class="sa-icon-btn" title="Auditoria Global">
            <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
        </a>
        <button class="sa-icon-btn" aria-label="Notificações">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <span class="dot"></span>
        </button>

        <div class="sa-user" id="sa-user-btn">
            <span class="avatar"><?= esc($iniciais) ?></span>
            <div class="txt">
                <div class="name"><?= esc($supervisor['nome'] ?? 'Superadmin') ?></div>
                <div class="role"><?= esc($supervisor['nivel'] ?? 'superadmin') ?></div>
            </div>
            <i class="fa-solid fa-chevron-down chev" aria-hidden="true"></i>

            <div class="sa-user-menu" id="sa-user-menu">
                <div class="sa-menu-head">
                    <div class="label">Sessão de plataforma</div>
                    <div class="email"><?= esc($supervisor['email'] ?? '') ?></div>
                </div>
                <a href="<?= esc(url('dashboard')) ?>" class="sa-menu-item">
                    <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                    <span>Ir para modo igreja</span>
                </a>
                <form method="post" action="<?= esc(url('superadmin/logout')) ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <button type="submit" class="sa-menu-item">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        <span>Terminar sessão</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<script>
    document.getElementById('sa-user-btn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        document.getElementById('sa-user-menu').classList.toggle('show');
    });
    document.addEventListener('click', (e) => {
        const m = document.getElementById('sa-user-menu');
        const b = document.getElementById('sa-user-btn');
        if (m && b && !m.contains(e.target) && !b.contains(e.target)) {
            m.classList.remove('show');
        }
    });
    document.getElementById('sa-global-search')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            window.location.href = '<?= esc(url('superadmin/igrejas')) ?>?q=' + encodeURIComponent(e.target.value);
        }
    });
</script>