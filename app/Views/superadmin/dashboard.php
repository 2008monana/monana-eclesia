<?php
/** @var array $supervisor */
/** @var array $stats */

$hora = (int) date('H');
$saudacao = match (true) {
    $hora < 12 => 'Bom dia',
    $hora < 19 => 'Boa tarde',
    default    => 'Boa noite',
};

$primeiroNome = explode(' ', trim((string) ($supervisor['nome'] ?? 'Superadmin')))[0];
$totalPlanos = array_sum(array_column($stats['por_plano'], 'total'));
?>
<div class="page-head" style="align-items:flex-start">
    <div>
        <h1 class="serif" style="font-size:28px;font-weight:600;color:var(--brown-900)">
            <?= esc($saudacao) ?>, <?= esc($primeiroNome) ?>!
        </h1>
        <p class="muted" style="margin:4px 0 0;font-size:14.5px">
            Bem-vindo ao painel da plataforma.
        </p>
    </div>
    <div style="display:flex;align-items:center;gap:10px;font-size:12.5px;color:var(--text-muted)">
        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
        <?= date('D, d M Y') ?>
    </div>
</div>

<!-- KPIs principais -->
<section class="grid kpis" style="margin-bottom:16px">
    <div class="card kpi">
        <span class="ic"><i class="fa-solid fa-church" aria-hidden="true"></i></span>
        <div>
            <div class="val"><?= number_format($stats['total_igrejas'], 0, ',', '.') ?></div>
            <div class="lbl">Igrejas registadas</div>
            <div class="delta">
                <i class="fa-solid fa-circle-check" aria-hidden="true" style="font-size:11px;color:var(--success)"></i>
                <?= $stats['ativas'] ?> ativas · <?= $stats['em_teste'] ?> em teste
            </div>
        </div>
    </div>

    <div class="card kpi">
        <span class="ic"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
        <div>
            <div class="val"><?= number_format($stats['total_membros'], 0, ',', '.') ?></div>
            <div class="lbl">Membros na plataforma</div>
            <div class="delta">
                <i class="fa-solid fa-user-shield" aria-hidden="true" style="font-size:11px"></i>
                <?= number_format($stats['total_users'], 0, ',', '.') ?> utilizadores
            </div>
        </div>
    </div>

    <div class="card kpi">
        <span class="ic"><i class="fa-solid fa-file-contract" aria-hidden="true"></i></span>
        <div>
            <div class="val"><?= $stats['assinaturas_ativas'] ?></div>
            <div class="lbl">Assinaturas ativas</div>
            <div class="delta">
                <?php if ($stats['assinaturas_problema'] > 0): ?>
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" style="font-size:11px;color:var(--danger)"></i>
                    <?= $stats['assinaturas_problema'] ?> a precisar de atenção
                <?php else: ?>
                    <i class="fa-solid fa-circle-check" aria-hidden="true" style="font-size:11px;color:var(--success)"></i>
                    Tudo em ordem
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card kpi">
        <span class="ic"><i class="fa-solid fa-coins" aria-hidden="true"></i></span>
        <div>
            <div class="val" style="font-size:18px"><?= fmtKz($stats['receita_mes']) ?></div>
            <div class="lbl">Receita do mês</div>
            <div class="delta">
                <i class="fa-solid fa-arrow-up" aria-hidden="true" style="font-size:11px;color:var(--success)"></i>
                Pagamentos confirmados
            </div>
        </div>
    </div>
</section>

<!-- Gráficos -->
<section class="grid row-2" style="margin-bottom:16px">
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                Novas igrejas (últimos 6 meses)
            </div>
        </div>
        <div style="position:relative;height:220px">
            <canvas id="chart-evolucao"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                Distribuição por plano
            </div>
        </div>
        <div class="donut-wrap">
            <div style="position:relative;width:170px;height:170px;flex-shrink:0">
                <canvas id="chart-planos"></canvas>
                <div class="donut-center">
                    <small>Total</small>
                    <b><?= $totalPlanos ?></b>
                </div>
            </div>
            <ul class="dist-list">
                <?php if (empty($stats['por_plano'])): ?>
                    <li class="muted">Sem dados</li>
                <?php else: ?>
                    <?php foreach ($stats['por_plano'] as $p): ?>
                        <li>
                            <i style="background:var(--gold-500)"></i>
                            <span><?= esc($p['nome']) ?></span>
                            <span><?= (int) $p['total'] ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</section>

<!-- Últimas igrejas -->
<section class="card">
    <div class="card-head">
        <div class="card-title">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            Últimas igrejas registadas
        </div>
        <a href="<?= esc(url('superadmin/igrejas')) ?>" class="link-more" style="font-size:12.5px;color:var(--gold-700);text-decoration:none">
            Ver todas <i class="fa-solid fa-arrow-right" aria-hidden="true" style="font-size:10px"></i>
        </a>
    </div>

    <?php if (empty($stats['ultimas_igrejas'])): ?>
        <p class="muted" style="padding:16px 0">Nenhuma igreja registada.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Igreja</th>
                        <th>Plano</th>
                        <th>Status</th>
                        <th>Criada</th>
                        <th style="text-align:right"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['ultimas_igrejas'] as $i):
                        $statusClass = match ($i['status']) {
                            'activa'    => 'ok',
                            'em_teste'  => 'off',
                            default     => 'off',
                        };
                        $statusLabel = match ($i['status']) {
                            'activa'    => 'Ativa',
                            'em_teste'  => 'Em teste',
                            'suspensa'  => 'Suspensa',
                            'cancelada' => 'Cancelada',
                            default     => ucfirst($i['status']),
                        };
                    ?>
                        <tr>
                            <td>
                                <a href="<?= esc(url('superadmin/igrejas/' . $i['id'])) ?>"
                                   style="color:var(--brown-900);text-decoration:none;font-weight:500">
                                    <i class="fa-solid fa-church" aria-hidden="true" style="color:var(--gold-600);margin-right:6px"></i>
                                    <?= esc($i['nome']) ?>
                                </a>
                            </td>
                            <td class="muted"><?= esc($i['plano_nome'] ?? '—') ?></td>
                            <td>
                                <span class="status <?= $statusClass ?>">
                                    <?= esc($statusLabel) ?>
                                </span>
                            </td>
                            <td class="muted" style="font-size:12.5px">
                                <?= esc(date('d/m/Y', strtotime($i['criado_em']))) ?>
                            </td>
                            <td style="text-align:right">
                                <a href="<?= esc(url('superadmin/igrejas/' . $i['id'])) ?>"
                                   class="icon-btn" title="Ver" style="display:inline-grid">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#7c6d58';

    const evolucao = <?= json_encode($stats['evolucao']) ?>;
    const porPlano = <?= json_encode($stats['por_plano']) ?>;

    new Chart(document.getElementById('chart-evolucao'), {
        type: 'bar',
        data: {
            labels: evolucao.map(e => e.label),
            datasets: [{
                label: 'Novas igrejas',
                data: evolucao.map(e => e.total),
                backgroundColor: '#d4a33b',
                borderRadius: 3,
                barPercentage: 0.55,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: '#f2ead8' }, ticks: { stepSize: 1 } },
            },
        },
    });

    if (porPlano.length) {
        new Chart(document.getElementById('chart-planos'), {
            type: 'doughnut',
            data: {
                labels: porPlano.map(p => p.nome),
                datasets: [{
                    data: porPlano.map(p => Number(p.total)),
                    backgroundColor: ['#b8862b', '#d4a33b', '#e2bc6f', '#9c6d1c', '#eacf92'],
                    borderColor: '#fffdf8', borderWidth: 2,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: { legend: { display: false } },
            },
        });
    }
</script>