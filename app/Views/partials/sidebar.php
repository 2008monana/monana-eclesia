<?php
use App\Core\Rbac;
use App\Core\Settings;

$itens = [
    ['id' => 'painel',        'label' => 'Painel Principal', 'icon' => 'fa-house',                 'perm' => null,                'url' => 'dashboard'],
    ['id' => 'membros',       'label' => 'Membros',          'icon' => 'fa-users',                 'perm' => 'membros.ver',       'url' => 'membros'],
    ['id' => 'departamentos', 'label' => 'Departamentos',    'icon' => 'fa-network-wired',         'perm' => 'departamentos.ver', 'url' => 'departamentos'],
    ['id' => 'celulas',       'label' => 'Células',          'icon' => 'fa-people-roof',           'perm' => 'celulas.ver',       'url' => 'celulas'],
    ['id' => 'ebd',           'label' => 'EBD',              'icon' => 'fa-book-open',             'perm' => 'ebd.ver',           'url' => 'ebd'],
    ['id' => 'eventos',       'label' => 'Eventos',          'icon' => 'fa-calendar-days',         'perm' => 'eventos.ver',       'url' => 'eventos'],
    ['id' => 'financeiro',    'label' => 'Financeiro',       'icon' => 'fa-circle-dollar-to-slot', 'perm' => 'financeiro.ver',    'url' => 'financeiro'],
    ['id' => 'relatorios',    'label' => 'Relatórios',       'icon' => 'fa-chart-line',            'perm' => 'relatorios.ver',    'url' => 'relatorios'],
    ['id' => 'usuarios',      'label' => 'Usuários',         'icon' => 'fa-user-shield',           'perm' => 'utilizadores.ver',  'url' => 'usuarios'],
    ['id' => 'notificacoes',  'label' => 'Notificações',     'icon' => 'fa-bell',                  'perm' => 'assinaturas.ver',   'url' => 'notificacoes'],
    ['id' => 'assinatura',    'label' => 'Assinatura',       'icon' => 'fa-credit-card',           'perm' => 'assinaturas.ver',   'url' => 'assinatura'],
    ['id' => 'auditoria',     'label' => 'Auditoria',        'icon' => 'fa-clipboard-list',        'perm' => 'auditoria.ver',     'url' => 'auditoria'],
    ['id' => 'backups',       'label' => 'Backups',          'icon' => 'fa-database',              'perm' => 'backup.ver',        'url' => 'backups'],
    ['id' => 'configuracoes', 'label' => 'Configurações',    'icon' => 'fa-gear',                  'perm' => 'configuracoes.ver', 'url' => 'configuracoes'],
];

$currentPath    = trim((string) ($_SERVER['APP_ROUTE'] ?? ''), '/');
$currentSegment = explode('/', $currentPath)[0] ?? 'dashboard';

$versiculo = Settings::get('versiculo_sidebar', '“Servir a Deus é um privilégio.”');
?>
<aside class="sidebar" id="sidebar">
    <nav class="nav">
        <?php foreach ($itens as $item): ?>
            <?php
            if ($item['perm'] !== null && !Rbac::can($item['perm'])) {
                continue;
            }
            $isActive = ($currentSegment === $item['url'])
                || ($item['url'] === 'dashboard' && ($currentSegment === '' || $currentSegment === 'dashboard'));
            ?>
            <a href="<?= esc(url($item['url'])) ?>"
               class="nav-item <?= $isActive ? 'active' : '' ?>"
               style="text-decoration:none">
                <i class="fa-solid <?= esc($item['icon']) ?>" aria-hidden="true"></i>
                <span><?= esc($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-verse">
        <div class="cross-line">
            <i class="fa-solid fa-cross" aria-hidden="true"></i>
        </div>
        <p><?= $versiculo ?></p>
    </div>
</aside>