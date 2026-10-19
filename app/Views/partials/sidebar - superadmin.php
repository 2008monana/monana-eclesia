<?php
$itens = [
    ['label' => 'Visão Geral',      'icon' => 'fa-chart-pie',           'url' => 'superadmin'],
    ['label' => 'Igrejas',          'icon' => 'fa-church',              'url' => 'superadmin/igrejas'],
    ['label' => 'Supervisores',     'icon' => 'fa-user-shield',         'url' => 'superadmin/supervisores'],
    ['label' => 'Planos',           'icon' => 'fa-layer-group',         'url' => 'superadmin/planos'],
    ['label' => 'Assinaturas',      'icon' => 'fa-file-contract',       'url' => 'superadmin/assinaturas'],
    ['label' => 'Pagamentos',       'icon' => 'fa-money-bill-transfer', 'url' => 'superadmin/pagamentos'],
    ['label' => 'Auditoria Global', 'icon' => 'fa-clipboard-list',      'url' => 'superadmin/auditoria'],
    ['label' => 'Configurações',    'icon' => 'fa-gear',                'url' => 'superadmin/configuracoes'],
];

$currentPath = trim((string) ($_SERVER['APP_ROUTE'] ?? ''), '/');
?>
<aside class="sa-sidebar" id="sa-sidebar">
    <div class="sa-sidebar-header">
        <div class="label">Plataforma</div>
        <div class="title">Superadmin</div>
    </div>

    <nav class="sa-nav">
        <?php foreach ($itens as $item):
            $isActive = $currentPath === $item['url']
                || ($item['url'] === 'superadmin' && $currentPath === 'superadmin');
        ?>
            <a href="<?= esc(url($item['url'])) ?>"
               class="sa-nav-item <?= $isActive ? 'active' : '' ?>">
                <i class="fa-solid <?= esc($item['icon']) ?>" aria-hidden="true"></i>
                <span><?= esc($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sa-verse">
        <div class="cross"><i class="fa-solid fa-cross" aria-hidden="true"></i></div>
        <p>“A colheita é grande,<br>mas os trabalhadores são poucos.”</p>
    </div>
</aside>