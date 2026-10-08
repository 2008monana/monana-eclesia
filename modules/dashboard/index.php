<?php
// modules/dashboard/index.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('modules/auth/login.php');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('dashboard');
$page_title = 'Dashboard - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$conn = getConnection();

// Estatísticas
$stmt = $conn->query("SELECT COUNT(*) as total FROM membros WHERE ativo = 1");
$totalMembros = $stmt->fetch()['total'] ?? 0;

$stmt = $conn->query("SELECT 
    SUM(CASE WHEN categoria = 'mama' THEN 1 ELSE 0 END) as mamas,
    SUM(CASE WHEN categoria = 'papa' THEN 1 ELSE 0 END) as papas,
    SUM(CASE WHEN categoria = 'jovem' THEN 1 ELSE 0 END) as jovens,
    SUM(CASE WHEN categoria = 'crianca' THEN 1 ELSE 0 END) as criancas
FROM membros WHERE ativo = 1");
$categorias = $stmt->fetch();

// "Valor Arrecadado" reflete o dinheiro que entrou de facto (data_pagamento),
// não o mês de referência usado só para controlo de dívida.
$mes_atual = date('Y-m-01'); // usado abaixo para as métricas de dívida (mes_referencia)
$mes_atual_inicio = date('Y-m-01');
$mes_atual_fim = date('Y-m-t');
$stmt = $conn->prepare("SELECT SUM(valor) as total FROM cotas_diarias WHERE data_pagamento BETWEEN :inicio AND :fim");
$stmt->execute([':inicio' => $mes_atual_inicio, ':fim' => $mes_atual_fim]);
$mesAtualTotal = $stmt->fetch()['total'] ?? 0;

$ano_atual_inicio = date('Y-01-01');
$ano_atual_fim = date('Y-12-31');
$stmt = $conn->prepare("SELECT SUM(valor) as total FROM cotas_diarias WHERE data_pagamento BETWEEN :inicio AND :fim");
$stmt->execute([':inicio' => $ano_atual_inicio, ':fim' => $ano_atual_fim]);
$anoAtualTotal = $stmt->fetch()['total'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM membros WHERE ativo = 1 
    AND id NOT IN (SELECT DISTINCT membro_id FROM cotas_diarias WHERE mes_referencia = :mes)");
$stmt->execute([':mes' => $mes_atual]);
$emDivida = $stmt->fetch()['total'] ?? 0;

$stmt = $conn->query("SELECT 
    c.*, m.nome_completo, m.categoria 
    FROM cotas_diarias c 
    JOIN membros m ON c.membro_id = m.id 
    ORDER BY c.criado_em DESC LIMIT 5");
$ultimosPagamentos = $stmt->fetchAll();

// Contagem real dos pagamentos de HOJE (para o card "Últimos Pagamentos Hoje").
// Antes usava count($ultimosPagamentos), que é sempre <= 5 e não reflete
// os pagamentos de hoje - agora conta mesmo os registos de data_pagamento = hoje.
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM cotas_diarias WHERE data_pagamento = CURDATE()");
$stmt->execute();
$pagamentosHojeTotal = $stmt->fetch()['total'] ?? 0;

// Dados para gráfico (últimos 6 meses) - arrecadação real por data_pagamento
// Nota: DATE_FORMAT(...,'%b/%Y') devolve o mês em inglês (locale do MySQL),
// por isso o nome do mês é montado no PHP com nomeMesAbrevPT().
$stmt = $conn->query("SELECT 
    YEAR(data_pagamento) as ano_ord,
    MONTH(data_pagamento) as mes_ord,
    SUM(valor) as total
    FROM cotas_diarias 
    WHERE data_pagamento >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(data_pagamento), MONTH(data_pagamento)
    ORDER BY ano_ord ASC, mes_ord ASC");
$dadosGrafico = $stmt->fetchAll();
$labels = array_map(function ($d) {
    return nomeMesAbrevPT($d['mes_ord']) . '/' . $d['ano_ord'];
}, $dadosGrafico);
$values = array_column($dadosGrafico, 'total');

// Dados para gráfico por categoria (este mês) - arrecadação real por data_pagamento
$stmt = $conn->prepare("SELECT 
    m.categoria,
    SUM(c.valor) as total
    FROM cotas_diarias c
    JOIN membros m ON c.membro_id = m.id
    WHERE c.data_pagamento BETWEEN :inicio AND :fim
    GROUP BY m.categoria");
$stmt->execute([':inicio' => $mes_atual_inicio, ':fim' => $mes_atual_fim]);
$dadosCategoria = $stmt->fetchAll();

// IMPORTANTE: as chaves 0,1,2 têm de existir sempre (mama, papa, jovem),
// mesmo quando uma categoria não tem nenhum pagamento no mês. Antes, quando
// só havia dados de "jovem" (índice 2), o array ficava com uma única chave
// (2 => valor) e array_pad() acrescentava as posições em falta a seguir
// (chaves 3 e 4, não 0 e 1). Depois, array_values() reindexava tudo a partir
// de 0, empurrando o valor de "jovem" para a posição 0 ("Mamãs") no gráfico -
// por isso um valor lançado em Jovens aparecia 100% em Mamãs.
$categoriasValores = [0 => 0, 1 => 0, 2 => 0];
foreach ($dadosCategoria as $dc) {
    $idx = array_search($dc['categoria'], ['mama', 'papa', 'jovem']);
    if ($idx !== false) {
        $categoriasValores[$idx] = (float) $dc['total'];
    }
}
?>

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