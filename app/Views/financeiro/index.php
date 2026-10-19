<?php
/** @var array $user */
/** @var int $ano */
/** @var int $mes */
/** @var array $totais */
/** @var array $evolucao */
/** @var array $porCategoria */
/** @var array $fechos */

use App\Core\Rbac;

$meses = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$podeCriar = Rbac::can('financeiro.criar');
?>
<div class="page-head">
    <div>
        <h1 class="page-title">Financeiro</h1>
        <p class="muted" style="margin:4px 0 0;font-size:13px">
            <?= esc($meses[$mes]) ?> de <?= $ano ?>
        </p>
    </div>
    <div style="display:flex;gap:10px">
        <a href="<?= esc(url('financeiro/categorias')) ?>" class="btn btn-outline">
            <i class="fa-solid fa-tags" aria-hidden="true"></i>
            Categorias
        </a>
        <a href="<?= esc(url('financeiro/transacoes')) ?>" class="btn btn-outline">
            <i class="fa-solid fa-list" aria-hidden="true"></i>
            Transações
        </a>
        <?php if ($podeCriar): ?>
            <a href="<?= esc(url('financeiro/transacoes/criar')) ?>" class="btn btn-gold">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Nova Transação
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtro de mês -->
<div class="card" style="margin-bottom:16px;padding:14px 18px">
    <form method="get" action="<?= esc(url('financeiro')) ?>"
          style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <span style="font-size:13px;color:var(--text-muted)">Período:</span>
        <select class="select" name="mes" style="max-width:180px" onchange="this.form.submit()">
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?= $i ?>" <?= $i === $mes ? 'selected' : '' ?>><?= $meses[$i] ?></option>
            <?php endfor; ?>
        </select>
        <select class="select" name="ano" style="max-width:120px" onchange="this.form.submit()">
            <?php for ($i = (int) date('Y') - 3; $i <= (int) date('Y') + 1; $i++): ?>
                <option value="<?= $i ?>" <?= $i === $ano ? 'selected' : '' ?>><?= $i ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<!-- KPIs -->
<section class="grid kpis-3" style="margin-bottom:16px">
    <div class="card kpi-simple">
        <div class="lbl muted">Total de Entradas</div>
        <div class="val" style="color:var(--success)"><?= fmtKz($totais['entradas']) ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:6px">
            <i class="fa-solid fa-arrow-up" aria-hidden="true" style="font-size:11px"></i>
            <?= $totais['total'] ?> transações no mês
        </div>
    </div>
    <div class="card kpi-simple">
        <div class="lbl muted">Total de Despesas</div>
        <div class="val" style="color:var(--danger)"><?= fmtKz($totais['despesas']) ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:6px">
            <i class="fa-solid fa-arrow-down" aria-hidden="true" style="font-size:11px"></i>
            <?= $totais['despesas'] > 0 ? round(($totais['despesas'] / max(1, $totais['entradas'])) * 100) . '% das entradas' : '—' ?>
        </div>
    </div>
    <div class="card kpi-simple">
        <div class="lbl muted">Saldo do Mês</div>
        <div class="val" style="color:<?= $totais['saldo'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
            <?= fmtKz($totais['saldo']) ?>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:6px">
            <?= $totais['saldo'] >= 0 ? 'Positivo' : 'Negativo' ?>
        </div>
    </div>
</section>

<section class="grid row-2" style="margin-bottom:16px">
    <!-- Gráfico de barras (evolução) -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                Evolução (últimos 6 meses)
            </div>
        </div>
        <div style="position:relative;height:220px">
            <canvas id="chart-evolucao"></canvas>
        </div>
    </div>

    <!-- Gráfico de rosca (categorias de despesa) -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                Despesas por Categoria
            </div>
        </div>
        <?php if (empty($porCategoria)): ?>
            <div style="text-align:center;padding:32px;color:var(--text-muted)">
                <i class="fa-solid fa-circle-info" aria-hidden="true" style="font-size:24px;color:var(--gold-300)"></i>
                <p style="margin:8px 0 0">Sem despesas registadas este mês.</p>
            </div>
        <?php else: ?>
            <div style="position:relative;height:220px">
                <canvas id="chart-categoria"></canvas>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Fecho mensal -->
<section class="card">
    <div class="card-head">
        <div class="card-title">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            Fechos Mensais Recentes
        </div>
        <a href="<?= esc(url('financeiro/fecho')) ?>" class="link-more" style="font-size:12.5px;color:var(--gold-700);text-decoration:none">
            Ver todos <i class="fa-solid fa-arrow-right" aria-hidden="true" style="font-size:10px"></i>
        </a>
    </div>
    <?php if (empty($fechos)): ?>
        <p class="muted" style="margin:0;padding:16px 0">Nenhum fecho mensal realizado.</p>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
            <?php foreach ($fechos as $f): ?>
                <div style="padding:12px 14px;background:var(--gold-50);border-radius:8px;border-left:3px solid var(--gold-500)">
                    <div style="font-size:11.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">
                        <?= esc($meses[(int) $f['mes']]) ?> <?= (int) $f['ano'] ?>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:12.5px">
                        <span class="up">+<?= fmtKz($f['total_entradas']) ?></span>
                        <span class="down">−<?= fmtKz($f['total_despesas']) ?></span>
                    </div>
                    <div style="font-weight:600;margin-top:4px;color:<?= (float) $f['saldo'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                        <?= fmtKz($f['saldo']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.font.size   = 11;
    Chart.defaults.color       = '#7c6d58';

    const evolucao = <?= json_encode($evolucao) ?>;
    const porCategoria = <?= json_encode($porCategoria) ?>;

    new Chart(document.getElementById('chart-evolucao'), {
        type: 'bar',
        data: {
            labels: evolucao.map(e => e.label),
            datasets: [
                { label: 'Entradas', data: evolucao.map(e => e.entradas), backgroundColor: '#d4a33b', borderRadius: 3, barPercentage: .6, categoryPercentage: .6 },
                { label: 'Despesas', data: evolucao.map(e => e.despesas), backgroundColor: '#8a5a1c', borderRadius: 3, barPercentage: .6, categoryPercentage: .6 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                tooltip: { callbacks: { label: c => `${c.dataset.label}: ${Number(c.raw).toLocaleString('pt-PT')} Kz` } },
            },
            scales: {
                x: { grid: { display: false }, border: { color: '#ece2cc' } },
                y: { beginAtZero: true, grid: { color: '#f2ead8' }, border: { display: false }, ticks: { callback: v => Number(v).toLocaleString('pt-PT') } },
            },
        },
    });

    if (porCategoria.length && document.getElementById('chart-categoria')) {
        new Chart(document.getElementById('chart-categoria'), {
            type: 'doughnut',
            data: {
                labels: porCategoria.map(c => c.nome),
                datasets: [{
                    data: porCategoria.map(c => Number(c.total)),
                    backgroundColor: porCategoria.map(c => c.cor || '#b8862b'),
                    borderColor: '#fffdf8', borderWidth: 2,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 10 } } },
                    tooltip: { callbacks: { label: c => ` ${c.label}: ${Number(c.raw).toLocaleString('pt-PT')} Kz` } },
                },
            },
        });
    }
</script>