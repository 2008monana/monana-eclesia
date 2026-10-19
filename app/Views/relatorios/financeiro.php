<?php
/** @var array $user */
/** @var int $ano */
/** @var array $porMes */
/** @var array $porCategoria */
/** @var array $porMetodo */
/** @var array $topDizimistas */

use App\Core\Rbac;

$meses = ['', 'Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$podeExportar = Rbac::can('relatorios.exportar');

$totalEntradas = array_sum(array_column($porMes, 'entradas'));
$totalDespesas = array_sum(array_column($porMes, 'despesas'));
$saldoAnual = $totalEntradas - $totalDespesas;
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('relatorios')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title">Relatório Financeiro — <?= $ano ?></h1>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
        <form method="get" style="display:flex;gap:8px">
            <select class="select" name="ano" onchange="this.form.submit()" style="width:100px">
                <?php for ($i = (int) date('Y') - 3; $i <= (int) date('Y') + 1; $i++): ?>
                    <option value="<?= $i ?>" <?= $i === $ano ? 'selected' : '' ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <?php if ($podeExportar): ?>
            <a href="<?= esc(url('relatorios/exportar/financeiro')) ?>" class="btn btn-gold">
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                Exportar CSV
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid kpis-3" style="margin-bottom:16px">
    <div class="card kpi-simple">
        <div class="lbl muted">Entradas no ano</div>
        <div class="val" style="color:var(--success)"><?= fmtKz($totalEntradas) ?></div>
    </div>
    <div class="card kpi-simple">
        <div class="lbl muted">Despesas no ano</div>
        <div class="val" style="color:var(--danger)"><?= fmtKz($totalDespesas) ?></div>
    </div>
    <div class="card kpi-simple">
        <div class="lbl muted">Saldo anual</div>
        <div class="val" style="color:<?= $saldoAnual >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= fmtKz($saldoAnual) ?></div>
    </div>
</div>

<section class="card" style="margin-bottom:16px">
    <div class="card-head">
        <div class="card-title"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Evolução Mensal</div>
    </div>
    <div style="position:relative;height:260px">
        <canvas id="chart-mensal"></canvas>
    </div>
</section>

<div class="grid row-2" style="gap:16px;margin-bottom:16px">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fa-solid fa-tags" aria-hidden="true"></i> Por Categoria</div>
        </div>
        <?php if (empty($porCategoria)): ?>
            <p class="muted">Sem dados.</p>
        <?php else: ?>
            <div style="position:relative;height:240px">
                <canvas id="chart-categoria"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fa-solid fa-credit-card" aria-hidden="true"></i> Por Método de Pagamento</div>
        </div>
        <?php if (empty($porMetodo)): ?>
            <p class="muted">Sem dados.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Método</th><th style="text-align:right">Nº</th><th style="text-align:right">Valor</th></tr></thead>
                    <tbody>
                        <?php foreach ($porMetodo as $m): ?>
                            <tr>
                                <td><?= esc(ucfirst($m['metodo'])) ?></td>
                                <td style="text-align:right" class="muted"><?= (int) $m['total'] ?></td>
                                <td style="text-align:right;font-weight:600"><?= fmtKz($m['valor']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div class="card-title"><i class="fa-solid fa-crown" aria-hidden="true"></i> Top 10 Contribuintes do Ano</div>
    </div>
    <?php if (empty($topDizimistas)): ?>
        <p class="muted">Sem dados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>Membro</th><th style="text-align:right">Total contribuído</th></tr></thead>
                <tbody>
                    <?php foreach ($topDizimistas as $i => $d): ?>
                        <tr>
                            <td><strong><?= $i + 1 ?>º</strong></td>
                            <td>
                                <a href="<?= esc(url('membros/' . $d['nome'])) ?>" style="color:var(--brown-900);font-weight:500;text-decoration:none">
                                    <?= esc($d['nome']) ?>
                                </a>
                                <span class="muted" style="font-size:11.5px">(<?= esc($d['codigo']) ?>)</span>
                            </td>
                            <td style="text-align:right;font-weight:600;color:var(--success)"><?= fmtKz($d['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#7c6d58';

    const porMes = <?= json_encode($porMes) ?>;
    const porCategoria = <?= json_encode($porCategoria) ?>;
    const mesesLabel = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

    new Chart(document.getElementById('chart-mensal'), {
        type: 'bar',
        data: {
            labels: porMes.map(m => mesesLabel[m.mes - 1]),
            datasets: [
                { label: 'Entradas', data: porMes.map(m => m.entradas), backgroundColor: '#d4a33b', borderRadius: 3, barPercentage: .6, categoryPercentage: .6 },
                { label: 'Despesas', data: porMes.map(m => m.despesas), backgroundColor: '#8a5a1c', borderRadius: 3, barPercentage: .6, categoryPercentage: .6 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                tooltip: { callbacks: { label: c => `${c.dataset.label}: ${Number(c.raw).toLocaleString('pt-PT')} Kz` } },
            },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: '#f2ead8' }, ticks: { callback: v => Number(v).toLocaleString('pt-PT') } },
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
                responsive: true, maintainAspectRatio: false, cutout: '60%',
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 10 } } },
                    tooltip: { callbacks: { label: c => ` ${c.label}: ${Number(c.raw).toLocaleString('pt-PT')} Kz` } },
                },
            },
        });
    }
</script>