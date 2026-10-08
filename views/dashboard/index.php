<!-- ===== MAIN CONTENT ===== -->
<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>
    
    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-chart-pie"></i> Dashboard</h2>
            <div class="date-pill">
                <i class="fas fa-calendar-alt"></i> <?= dataPorExtensoPT() ?>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="grid-stats">
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-users"></i></div>
                <div class="stat-num"><?= number_format($totalMembros) ?></div>
                <div class="stat-label">Total de Membros</div>
                <a href="<?= url('modules/members/index.php') ?>" class="stat-link"><i class="fas fa-arrow-right"></i> Ver todos</a>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fce4ec;color:#b5527a;"><i class="fas fa-female"></i></div>
                <div class="stat-num"><?= number_format($categorias['mamas'] ?? 0) ?></div>
                <div class="stat-label">Mamãs</div>
                <a href="<?= url('modules/lists/mamas.php') ?>" class="stat-link"><i class="fas fa-arrow-right"></i> Ver todos</a>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-male"></i></div>
                <div class="stat-num"><?= number_format($categorias['papas'] ?? 0) ?></div>
                <div class="stat-label">Papás</div>
                <a href="<?= url('modules/lists/papas.php') ?>" class="stat-link"><i class="fas fa-arrow-right"></i> Ver todos</a>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#ede7f6;color:#7a5ca8;"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-num"><?= number_format($categorias['jovens'] ?? 0) ?></div>
                <div class="stat-label">Jovens</div>
                <a href="<?= url('modules/lists/jovens.php') ?>" class="stat-link"><i class="fas fa-arrow-right"></i> Ver todos</a>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fdf1d9;color:#c99a2e;"><i class="fas fa-child"></i></div>
                <div class="stat-num"><?= number_format($categorias['criancas'] ?? 0) ?></div>
                <div class="stat-label">Crianças</div>
                <div class="stat-note"><i class="fas fa-info-circle"></i> Não incluído na cota</div>
            </div>
        </div>

        <!-- METRICS -->
        <div class="grid-metrics">
            <div class="metric-card">
                <div class="metric-top">
                    <span class="ic-box" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-coins"></i></span>
                    Valor Arrecadado Este Mês
                </div>
                <div class="metric-val"><?= number_format($mesAtualTotal, 2, ',', '.') ?> Kz</div>
                <div class="metric-sub">
                    <span class="up"><i class="fas fa-arrow-up"></i> +12.5%</span> em relação ao mês anterior
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-top">
                    <span class="ic-box" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-chart-line"></i></span>
                    Valor Arrecadado Este Ano
                </div>
                <div class="metric-val"><?= number_format($anoAtualTotal, 2, ',', '.') ?> Kz</div>
                <div class="metric-sub">
                    <span class="up"><i class="fas fa-arrow-up"></i> +8.3%</span> em relação ao ano anterior
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-top">
                    <span class="ic-box" style="background:#fdeee3;color:#b9770e;"><i class="fas fa-exclamation-triangle"></i></span>
                    Membros em Dívida
                </div>
                <div class="metric-val"><?= number_format($emDivida) ?></div>
                <div class="metric-sub">
                    <?= number_format(($emDivida / max($totalMembros, 1)) * 100, 1) ?>% do total · <a href="<?= url('modules/lists/index.php') ?>" style="color:var(--blue-500);font-weight:600;text-decoration:none;">Ver detalhes</a>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-top">
                    <span class="ic-box" style="background:#ede7f6;color:#7a5ca8;"><i class="fas fa-clock"></i></span>
                    Últimos Pagamentos Hoje
                </div>
                <div class="metric-val"><?= number_format($pagamentosHojeTotal) ?></div>
                <div class="metric-sub"><a href="<?= url('modules/registry/index.php') ?>" style="color:var(--blue-500);font-weight:600;text-decoration:none;">Ver todos</a></div>
            </div>
        </div>

        <!-- GRID LOWER -->
        <div class="grid-lower">
            <div class="panel">
                <h3><i class="fas fa-chart-area"></i> Arrecadação dos Últimos 6 Meses</h3>
                <canvas id="chartLine" height="180"></canvas>
            </div>
            <div class="panel">
                <h3><i class="fas fa-chart-pie"></i> Resumo por Categoria (Este Mês)</h3>
                <div class="donut-wrap">
                    <canvas id="chartDonut" width="150" height="150"></canvas>
                    <div class="legend">
                        <?php
                        $cores = ['#c99a2e', '#3f7d4e', '#7a5ca8'];
                        // Nome próprio ($labelsCategorias) para não sobrescrever $labels,
                        // que já guarda os meses usados no gráfico de linha mais abaixo.
                        $labelsCategorias = ['Mamãs', 'Papás', 'Jovens'];
                        $totalCategoria = array_sum($categoriasValores) ?: 1;
                        foreach ($labelsCategorias as $i => $label):
                            $valor = $categoriasValores[$i] ?? 0;
                            $percent = ($valor / $totalCategoria) * 100;
                        ?>
                        <div>
                            <span class="dot" style="background:<?= $cores[$i] ?>;"></span>
                            <span class="lname"><?= $label ?></span>
                            <br><span class="lval"><?= number_format($valor, 2, ',', '.') ?> Kz (<?= number_format($percent, 1) ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="panel">
                <h3><i class="fas fa-clock"></i> Últimos Pagamentos</h3>
                <div class="paylist">
                    <?php foreach ($ultimosPagamentos as $pg): ?>
                    <div class="payrow">
                        <div class="av3" style="background:<?= 
                            $pg['categoria'] == 'mama' ? '#b5527a' : 
                            ($pg['categoria'] == 'papa' ? '#3f7d4e' : '#7a5ca8') 
                        ?>;">
                            <?= strtoupper(substr($pg['nome_completo'], 0, 2)) ?>
                        </div>
                        <div class="info">
                            <div class="nm"><?= htmlspecialchars($pg['nome_completo']) ?></div>
                            <div class="role">
                                <?= ucfirst($pg['categoria']) ?> · <?= date('d/m/Y', strtotime($pg['data_pagamento'])) ?>
                            </div>
                        </div>
                        <div class="amt"><?= number_format($pg['valor'], 2, ',', '.') ?> Kz</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?= url('modules/registry/index.php') ?>" class="see-all"><i class="fas fa-arrow-right"></i> Ver todos os pagamentos</a>
            </div>
        </div>

        <!-- AÇÕES RÁPIDAS -->
        <div class="actions-title"><i class="fas fa-bolt"></i> Ações Rápidas</div>
        <div class="actions-grid">
            <a href="<?= url('modules/registry/index.php') ?>" class="action-card">
                <div class="ic" style="background:var(--blue-500);"><i class="fas fa-calendar-plus"></i></div>
                <div class="lbl">Novo Registo Diário</div>
            </a>
            <a href="<?= url('modules/members/add.php') ?>" class="action-card">
                <div class="ic" style="background:var(--blue-600);"><i class="fas fa-user-plus"></i></div>
                <div class="lbl">Adicionar Membro</div>
            </a>
            <a href="<?= url('modules/lists/mamas.php') ?>" class="action-card">
                <div class="ic" style="background:#b5527a;"><i class="fas fa-female"></i></div>
                <div class="lbl">Lista de Mamãs</div>
            </a>
            <a href="<?= url('modules/lists/papas.php') ?>" class="action-card">
                <div class="ic" style="background:#3f7d4e;"><i class="fas fa-male"></i></div>
                <div class="lbl">Lista de Papás</div>
            </a>
            <a href="<?= url('modules/lists/jovens.php') ?>" class="action-card">
                <div class="ic" style="background:#7a5ca8;"><i class="fas fa-user-graduate"></i></div>
                <div class="lbl">Lista de Jovens</div>
            </a>
            <a href="<?= url('modules/reports/index.php') ?>" class="action-card">
                <div class="ic" style="background:var(--gold-500);"><i class="fas fa-file-pdf"></i></div>
                <div class="lbl">Relatório Mensal</div>
            </a>
            <a href="<?= url('modules/year-end/index.php') ?>" class="action-card">
                <div class="ic" style="background:#b9770e;"><i class="fas fa-gift"></i></div>
                <div class="lbl">Contribuição Fim do Ano</div>
            </a>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Gráfico de Linha
    const ctxLine = document.getElementById('chartLine').getContext('2d');
    new Chart(ctxLine, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Arrecadação (Kz)',
                data: <?= json_encode($values) ?>,
                borderColor: '#c99a2e',
                backgroundColor: 'rgba(201,154,46,.12)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#c99a2e',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString() + ' Kz';
                        }
                    }
                }
            }
        }
    });

    // Gráfico de Donut
    const ctxDonut = document.getElementById('chartDonut').getContext('2d');
    new Chart(ctxDonut, {
        type: 'doughnut',
        data: {
            labels: ['Mamãs', 'Papás', 'Jovens'],
            datasets: [{
                data: <?= json_encode(array_values($categoriasValores)) ?>,
                backgroundColor: ['#c99a2e', '#3f7d4e', '#7a5ca8'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '70%',
            plugins: {
                legend: { display: false }
            }
        }
    });
</script>
