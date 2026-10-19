<?php
/** @var array $user */
/** @var array $porStatus */
/** @var array $porGenero */
/** @var array $porEstadoCivil */
/** @var array $porDepartamento */
/** @var array $porFaixaEtaria */

use App\Core\Rbac;

$statusLabels = ['activo' => 'Activo', 'inactivo' => 'Inactivo', 'transferido' => 'Transferido', 'falecido' => 'Falecido'];
$generoLabels = ['M' => 'Masculino', 'F' => 'Feminino', 'outro' => 'Outro'];
$civilLabels  = ['solteiro' => 'Solteiro(a)', 'casado' => 'Casado(a)', 'divorciado' => 'Divorciado(a)', 'viuvo' => 'Viúvo(a)', 'uniao_facto' => 'União de facto'];

$totalMembros = array_sum(array_column($porStatus, 'total'));
$podeExportar = Rbac::can('relatorios.exportar');
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('relatorios')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title">Relatório de Membros</h1>
    </div>
    <?php if ($podeExportar): ?>
        <a href="<?= esc(url('relatorios/exportar/membros')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-download" aria-hidden="true"></i>
            Exportar CSV
        </a>
    <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px;margin-bottom:16px">
    <div class="card" style="text-align:center;padding:20px">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Total de Membros</div>
        <div style="font-size:32px;font-weight:600;color:var(--brown-900);margin-top:6px"><?= number_format($totalMembros, 0, ',', '.') ?></div>
    </div>
    <div class="card" style="text-align:center;padding:20px">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Departamentos</div>
        <div style="font-size:32px;font-weight:600;color:var(--brown-900);margin-top:6px"><?= count($porDepartamento) ?></div>
    </div>
    <div class="card" style="text-align:center;padding:20px">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Faixas Etárias</div>
        <div style="font-size:32px;font-weight:600;color:var(--brown-900);margin-top:6px"><?= count($porFaixaEtaria) ?></div>
    </div>
</div>

<div class="grid row-2" style="gap:16px;margin-bottom:16px">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Por Status</div>
        </div>
        <?php if (empty($porStatus)): ?>
            <p class="muted">Sem dados.</p>
        <?php else: ?>
            <?php foreach ($porStatus as $s): ?>
                <?php $pct = $totalMembros ? round(($s['total'] / $totalMembros) * 100) : 0; ?>
                <div style="margin-bottom:12px">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                        <span><?= esc($statusLabels[$s['status']] ?? $s['status']) ?></span>
                        <span style="color:var(--text-muted)"><?= (int) $s['total'] ?> (<?= $pct ?>%)</span>
                    </div>
                    <div style="background:var(--gold-100);height:8px;border-radius:999px;overflow:hidden">
                        <div style="width:<?= $pct ?>%;height:100%;background:var(--gold-500)"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fa-solid fa-venus-mars" aria-hidden="true"></i> Por Género</div>
        </div>
        <?php if (empty($porGenero)): ?>
            <p class="muted">Sem dados.</p>
        <?php else: ?>
            <div style="position:relative;height:220px">
                <canvas id="chart-genero"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid row-2" style="gap:16px;margin-bottom:16px">
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fa-solid fa-network-wired" aria-hidden="true"></i> Por Departamento</div>
        </div>
        <?php if (empty($porDepartamento)): ?>
            <p class="muted">Sem dados.</p>
        <?php else: ?>
            <?php $maxDep = max(array_column($porDepartamento, 'total')) ?: 1; ?>
            <?php foreach ($porDepartamento as $d): ?>
                <?php $pct = round(($d['total'] / $maxDep) * 100); ?>
                <div style="margin-bottom:10px">
                    <div style="display:flex;align-items:center;gap:10px;font-size:13px;margin-bottom:4px">
                        <span style="width:10px;height:10px;border-radius:50%;background:<?= esc($d['cor'] ?: '#b8862b') ?>"></span>
                        <span style="flex:1"><?= esc($d['nome']) ?></span>
                        <span style="color:var(--text-muted)"><?= (int) $d['total'] ?></span>
                    </div>
                    <div style="background:var(--gold-100);height:6px;border-radius:999px;overflow:hidden">
                        <div style="width:<?= $pct ?>%;height:100%;background:<?= esc($d['cor'] ?: '#b8862b') ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i> Faixa Etária</div>
        </div>
        <?php if (empty($porFaixaEtaria)): ?>
            <p class="muted">Sem dados.</p>
        <?php else: ?>
            <div style="position:relative;height:220px">
                <canvas id="chart-idade"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div class="card-title"><i class="fa-solid fa-heart" aria-hidden="true"></i> Por Estado Civil</div>
    </div>
    <?php if (empty($porEstadoCivil)): ?>
        <p class="muted">Sem dados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Estado civil</th><th style="text-align:right">Total</th></tr></thead>
                <tbody>
                    <?php foreach ($porEstadoCivil as $e): ?>
                        <tr>
                            <td><?= esc($civilLabels[$e['estado_civil']] ?? $e['estado_civil']) ?></td>
                            <td style="text-align:right;font-weight:600"><?= (int) $e['total'] ?></td>
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

    const generoLabels = <?= json_encode($generoLabels) ?>;
    const porGenero = <?= json_encode($porGenero) ?>;
    const porIdade = <?= json_encode($porFaixaEtaria) ?>;

    if (porGenero.length && document.getElementById('chart-genero')) {
        new Chart(document.getElementById('chart-genero'), {
            type: 'doughnut',
            data: {
                labels: porGenero.map(g => generoLabels[g.genero] || g.genero),
                datasets: [{
                    data: porGenero.map(g => Number(g.total)),
                    backgroundColor: ['#b8862b', '#d4a33b', '#e2bc6f'],
                    borderColor: '#fffdf8', borderWidth: 2,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                    tooltip: { callbacks: { label: c => ` ${c.label}: ${c.raw}` } },
                },
            },
        });
    }

    if (porIdade.length && document.getElementById('chart-idade')) {
        new Chart(document.getElementById('chart-idade'), {
            type: 'bar',
            data: {
                labels: porIdade.map(f => f.faixa),
                datasets: [{
                    label: 'Membros', data: porIdade.map(f => Number(f.total)),
                    backgroundColor: '#d4a33b', borderRadius: 4, barPercentage: .7,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#f2ead8' } },
                },
            },
        });
    }
</script>