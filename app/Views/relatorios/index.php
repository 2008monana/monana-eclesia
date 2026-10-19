<?php
/** @var array $user */
/** @var int $totalMembros */
/** @var int $totalEventos */
/** @var int $totalTransacoes */
/** @var int $totalAuditoria */

$cards = [
    ['url' => 'relatorios/membros',         'icon' => 'fa-users',               'titulo' => 'Relatório de Membros',    'desc' => 'Distribuição por status, género, departamento e faixa etária.',      'total' => $totalMembros],
    ['url' => 'relatorios/financeiro',      'icon' => 'fa-circle-dollar-to-slot','titulo' => 'Relatório Financeiro',    'desc' => 'Entradas, despesas, categorias e top contribuintes por ano.',        'total' => $totalTransacoes],
    ['url' => 'relatorios/frequencia',      'icon' => 'fa-calendar-check',      'titulo' => 'Relatório de Frequência', 'desc' => 'Presenças em eventos, membros e células nos últimos 3 meses.',      'total' => $totalEventos],
    ['url' => 'relatorios/aniversariantes', 'icon' => 'fa-cake-candles',        'titulo' => 'Aniversariantes',         'desc' => 'Aniversariantes do mês corrente, ordenados por dia.',                'total' => null],
    ['url' => 'auditoria',                  'icon' => 'fa-clipboard-list',      'titulo' => 'Auditoria',               'desc' => 'Histórico de ações realizadas na igreja (imutável).',                'total' => $totalAuditoria],
];
?>
<div class="page-head">
    <h1 class="page-title">Relatórios</h1>
</div>

<div class="grid" style="grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:16px">
    <?php foreach ($cards as $c): ?>
        <a href="<?= esc(url($c['url'])) ?>"
           class="card"
           style="text-decoration:none;color:inherit;display:block;transition:transform .15s, box-shadow .15s"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(120,85,30,.12)'"
           onmouseout="this.style.transform='';this.style.boxShadow=''">
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px">
                <span style="width:46px;height:46px;border-radius:12px;background:var(--gold-100);display:grid;place-items:center;color:var(--gold-700);flex-shrink:0">
                    <i class="fa-solid <?= esc($c['icon']) ?>" aria-hidden="true" style="font-size:20px"></i>
                </span>
                <div style="min-width:0">
                    <div style="font-size:15px;font-weight:600;color:var(--brown-900)"><?= esc($c['titulo']) ?></div>
                    <?php if ($c['total'] !== null): ?>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
                            <?= number_format($c['total'], 0, ',', '.') ?> registos
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <p style="margin:0;font-size:13px;color:var(--text-muted);line-height:1.5">
                <?= esc($c['desc']) ?>
            </p>
        </a>
    <?php endforeach; ?>
</div>