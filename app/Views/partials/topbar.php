<?php
/** @var array $user */
$iniciais = '';
if (!empty($user['nome'])) {
    $partes = preg_split('/\s+/', trim($user['nome']));
    $iniciais = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
}
?>
<header class="topbar">
    <button class="icon-btn menu-toggle" id="menu-toggle" aria-label="Abrir menu">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>

    <div class="brand">
        <?= emblem(42) ?>
        <div style="line-height:1">
            <div class="display" style="font-size:17px;letter-spacing:.04em;color:var(--brown-900)">
                MONANA <span style="color:var(--gold-600)">ECLESIA</span>
            </div>
            <div style="font-size:6.5px;letter-spacing:.22em;color:var(--text-muted);margin-top:5px">
                PLATAFORMA INTEGRADA DE GESTÃO DE IGREJAS
            </div>
        </div>
    </div>

    <div class="search field">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"
           style="position:absolute;left:12px;color:var(--text-soft);font-size:12px"></i>
        <input class="input" id="global-search" placeholder="Pesquisar no sistema..."
               style="padding-left:34px">
    </div>

    <div class="actions">
        <button class="icon-btn" aria-label="Notificações">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <span class="dot"></span>
        </button>
        <div class="user-chip" style="position:relative">
            <span class="avatar" style="width:34px;height:34px;display:inline-grid;place-items:center;color:var(--gold-700);font-weight:600;font-size:12px;background:var(--gold-100);border-radius:50%">
                <?= esc($iniciais) ?>
            </span>
            <div class="txt">
                <div class="name"><?= esc($user['nome'] ?? 'Utilizador') ?></div>
                <div class="role">Administrador</div>
            </div>
            <button class="icon-btn" id="user-menu-btn" aria-label="Menu">
                <i class="fa-solid fa-chevron-down" aria-hidden="true" style="font-size:12px"></i>
            </button>
            <div class="card" id="user-menu" style="position:absolute;right:0;top:48px;padding:6px;width:200px;z-index:50;display:none">
                <a href="<?= esc(url('perfil')) ?>" class="nav-item" style="text-decoration:none">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <span>O meu perfil</span>
                </a>
                <form method="post" action="<?= esc(url('logout')) ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <button type="submit" class="nav-item" style="width:100%;text-align:left;background:none;border:0;cursor:pointer">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        <span>Terminar sessão</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<script>
    document.getElementById('user-menu-btn')?.addEventListener('click', () => {
        const m = document.getElementById('user-menu');
        m.style.display = m.style.display === 'none' ? 'block' : 'none';
    });
    document.addEventListener('click', (e) => {
        const m = document.getElementById('user-menu');
        const b = document.getElementById('user-menu-btn');
        if (m && b && !m.contains(e.target) && !b.contains(e.target)) {
            m.style.display = 'none';
        }
    });
</script>