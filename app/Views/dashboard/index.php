<?php
/** @var array $user */
/** @var array|null $igreja */
/** @var array $stats */

// Saudação dinâmica
$hora = (int) date('H');
$saudacao = match (true) {
    $hora < 12 => 'Bom dia',
    $hora < 19 => 'Boa tarde',
    default    => 'Boa noite',
};

// Total de membros nos departamentos (para o centro da rosca)
$totalDist = array_sum(array_column($stats['distribuicao'], 'total'));

// Primeiro nome do utilizador
$primeiroNome = explode(' ', trim((string) ($user['nome'] ?? 'Irmão')))[0];
?>
<div class="page-head" style="align-items:flex-start">
    <div>
        <h1 class="serif" style="font-size:28px;font-weight:600;color:var(--brown-900)">
            <?= esc($saudacao) ?>, <?= esc($primeiroNome) ?>!
        </h1>
        <p class="muted" style="margin:4px 0 0;font-size:14.5px">
            Que a paz de Deus esteja sempre convosco.
        </p>
    </div>
    <div style="display:flex;align-items:center;gap:10px;font-size:12.5px;color:var(--text-muted)">
        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
        <?= date('D, d M Y') ?>
    </div>
</div>

<!-- ======================================================
     KPIs
     ====================================================== -->
<section class="grid kpis">
    <div class="card kpi">
        <span class="ic"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
        <div>
            <div class="val"><?= number_format($stats['membros'], 0, ',', '.') ?></div>
            <div class="lbl">Membros ativos</div>
            <div class="delta up">
                <i class="fa-solid fa-arrow-trend-up" aria-hidden="true" style="font-size:11px"></i>
                Registados
            </div>
        </div>
    </div>

    <div class="card kpi">
        <span class="ic"><i class="fa-solid fa-coins" aria-hidden="true"></i></span>
        <div>
            <div class="val" style="font-size:18px"><?= fmtKz($stats['ofertas_mes']) ?></div>
            <div class="lbl">Ofertas do mês</div>
            <div class="delta up">
                <i class="fa-solid fa-arrow-up" aria-hidden="true" style="font-size:11px"></i>
                Entradas
            </div>
        </div>
    </div>

    <div class="card kpi">
        <span class="ic"><i class="fa-solid fa-arrow-trend-down" aria-hidden="true"></i></span>
        <div>
            <div class="val" style="font-size:18px"><?= fmtKz($stats['despesas_mes']) ?></div>
            <div class="lbl">Despesas do mês</div>
            <div class="delta down">
                <i class="fa-solid fa-arrow-down" aria-hidden="true" style="font-size:11px"></i>
                Saídas
            </div>
        </div>
    </div>

    <div class="card kpi">
        <span class="ic"><i class="fa-solid fa-network-wired" aria-hidden="true"></i></span>
        <div>
            <div class="val"><?= $stats['departamentos'] ?></div>
            <div class="lbl">Departamentos</div>
            <div class="delta">
                <i class="fa-solid fa-people-roof" aria-hidden="true" style="font-size:11px"></i>
                <?= $stats['celulas'] ?> células ativas
            </div>
        </div>
    </div>
</section>

<!-- ======================================================
     Gráficos: Barras + Rosca
     ====================================================== -->
<section class="grid row-2" style="margin-top:16px">
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                Ofertas e Despesas <small>(últimos 6 meses)</small>
            </div>
        </div>
        <div class="legend" style="margin-bottom:8px">
            <span><i style="background:#d4a33b"></i>Ofertas</span>
            <span><i style="background:#8a5a1c"></i>Despesas</span>
        </div>
        <div style="position:relative;height:220px">
            <canvas id="chart-bar"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                Distribuição por Departamento
            </div>
        </div>
        <div class="donut-wrap">
            <div style="position:relative;width:170px;height:170px;flex-shrink:0">
                <canvas id="chart-donut"></canvas>
                <div class="donut-center">
                    <small>Total</small>
                    <b><?= number_format($totalDist, 0, ',', '.') ?></b>
                </div>
            </div>
            <ul class="dist-list">
                <?php if (empty($stats['distribuicao'])): ?>
                    <li class="muted" style="display:flex;align-items:center;gap:8px">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        Sem dados
                    </li>
                <?php else: ?>
                    <?php foreach ($stats['distribuicao'] as $d): ?>
                        <li>
                            <i style="background:<?= esc($d['cor'] ?: '#b8862b') ?>"></i>
                            <span><?= esc($d['nome']) ?></span>
                            <span><?= (int) $d['total'] ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</section>

<!-- ======================================================
     Próximos Eventos + Atividade Recente
     ====================================================== -->
<section class="grid row-2" style="margin-top:16px">
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                Próximos Eventos
            </div>
            <a href="<?= esc(url('eventos')) ?>" class="link-more">
                Ver todos
                <i class="fa-solid fa-arrow-right" aria-hidden="true" style="font-size:10px"></i>
            </a>
        </div>
        <?php if (empty($stats['proximos_eventos'])): ?>
            <div class="empty-state">
                <i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i>
                <p class="muted">Sem eventos agendados.</p>
            </div>
        <?php else: ?>
            <?php foreach ($stats['proximos_eventos'] as $e): ?>
                <?php
                $ts  = strtotime($e['data']);
                $dia = date('d', $ts);
                $mes = date('M', $ts);
                ?>
                <div class="ev-item">
                    <div class="date-box">
                        <b><?= esc($dia) ?></b>
                        <small><?= esc($mes) ?></small>
                    </div>
                    <div>
                        <div class="t"><?= esc($e['titulo']) ?></div>
                        <div class="s">
                            <i class="fa-regular fa-clock" aria-hidden="true" style="font-size:11px"></i>
                            <?= esc($e['hora'] ?? '—') ?>
                            · <i class="fa-solid fa-location-dot" aria-hidden="true" style="font-size:11px"></i>
                            <?= esc($e['local'] ?? '—') ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                Actividade Recente
            </div>
        </div>
        <div class="empty-state">
            <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
            <p class="muted">
                Em breve aparecerão aqui as últimas atividades da igreja —<br>
                novos membros, ofertas, eventos e mais.
            </p>
        </div>
    </div>
</section>

<!-- ======================================================
     Chart.js + inicialização
     ====================================================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    // Dados vindos do PHP
    const evolucao     = <?= json_encode($stats['evolucao'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const distribuicao = <?= json_encode($stats['distribuicao'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // Estilo global dos gráficos (Inter + dourado)
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.font.size   = 11;
    Chart.defaults.color       = '#7c6d58';

    // ---------------------------------------------------
    // Gráfico de barras: ofertas vs despesas (6 meses)
    // ---------------------------------------------------
    new Chart(document.getElementById('chart-bar'), {
        type: 'bar',
        data: {
            labels: evolucao.map(e => e.label),
            datasets: [
                {
                    label: 'Ofertas',
                    data: evolucao.map(e => e.entradas),
                    backgroundColor: '#d4a33b',
                    borderRadius: 3,
                    barPercentage: 0.6,
                    categoryPercentage: 0.6,
                },
                {
                    label: 'Despesas',
                    data: evolucao.map(e => e.saidas),
                    backgroundColor: '#8a5a1c',
                    borderRadius: 3,
                    barPercentage: 0.6,
                    categoryPercentage: 0.6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (c) =>
                            `${c.dataset.label}: ${Number(c.raw).toLocaleString('pt-PT')} Kz`,
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, border: { color: '#ece2cc' } },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f2ead8' },
                    border: { display: false },
                    ticks: {
                        callback: (v) => Number(v).toLocaleString('pt-PT'),
                    },
                },
            },
        },
    });

    // ---------------------------------------------------
    // Gráfico de rosca: distribuição por departamento
    // ---------------------------------------------------
    if (distribuicao.length > 0) {
        new Chart(document.getElementById('chart-donut'), {
            type: 'doughnut',
            data: {
                labels: distribuicao.map(d => d.nome),
                datasets: [{
                    data: distribuicao.map(d => Number(d.total)),
                    backgroundColor: distribuicao.map(d => d.cor || '#b8862b'),
                    borderColor: '#fffdf8',
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (c) => ` ${c.label}: ${c.raw} membro(s)`,
                        },
                    },
                },
            },
        });
    }
</script>