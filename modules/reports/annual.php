<?php
// modules/reports/annual.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('reports');
$page_title = 'Relatório Anual - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once 'functions.php';

$conn = getConnection();

// ============================================
// PARÂMETROS
// ============================================
$ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));

$fundos_disponiveis = getFundosRelatorio();
$fundo = isset($_GET['fundo']) ? $_GET['fundo'] : 'cotas';
if (!array_key_exists($fundo, $fundos_disponiveis)) $fundo = 'cotas';

// ============================================
// DADOS DO RELATÓRIO ANUAL
// ============================================
$resumo_anual = getResumoAnual($ano, $fundo);
$entradas_mensais = getEntradasMensais($ano, $fundo);
$saidas_mensais = getSaidasMensais($ano, $fundo);

$total_entradas = 0;
$total_saidas = 0;
$meses_com_movimento = 0;

for ($mes = 1; $mes <= 12; $mes++) {
    if ($entradas_mensais[$mes] > 0 || $saidas_mensais[$mes] > 0) {
        $meses_com_movimento++;
    }
    $total_entradas += $entradas_mensais[$mes];
    $total_saidas += $saidas_mensais[$mes];
}

// Dados para gráficos
$labels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$entradas_dados = array_values($entradas_mensais);
$saidas_dados = array_values($saidas_mensais);
$saldos = [];
for ($i = 0; $i < 12; $i++) {
    $saldos[] = $entradas_dados[$i] - $saidas_dados[$i];
}
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-calendar-alt"></i> Relatório Anual - <?= $ano ?>
                <span style="font-weight:400;font-size:13px;color:var(--gray-500);">- Fundo: <?= $fundos_disponiveis[$fundo] ?></span>
            </h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button onclick="gerarPDF()" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-file-pdf"></i> Gerar PDF
                </button>
                <a href="<?= url('modules/reports/export-excel.php?ano=' . $ano . '&fundo=' . $fundo) ?>" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--green),var(--success));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </a>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Fundo</label>
                    <select name="fundo" onchange="mudarFundo(this.value)" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <?php foreach ($fundos_disponiveis as $valor => $label): ?>
                        <option value="<?= $valor ?>" <?= $fundo == $valor ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php $anos_disponiveis = getAnosDisponiveis(); ?>
                <?php if (count($anos_disponiveis) > 1): ?>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Ano</label>
                    <select name="ano" onchange="window.location.href='<?= url('modules/reports/annual.php') ?>?ano='+this.value+'&fundo=<?= $fundo ?>'" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <?php foreach ($anos_disponiveis as $i): ?>
                        <option value="<?= $i ?>" <?= $ano == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==========================================
        RESUMO (CARDS)
        ========================================== -->
        <div class="grid-stats" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-arrow-down"></i></div>
                <div class="stat-num"><?= number_format($total_entradas, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Total de Entradas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f9e9e5;color:#b5412f;"><i class="fas fa-arrow-up"></i></div>
                <div class="stat-num"><?= number_format($total_saidas, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Total de Saídas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fdeee3;color:#b9770e;"><i class="fas fa-calculator"></i></div>
                <div class="stat-num"><?= number_format($total_entradas - $total_saidas, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Saldo do Ano</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-num"><?= $meses_com_movimento ?></div>
                <div class="stat-label">Meses com Movimento</div>
            </div>
        </div>

        <!-- ==========================================
        GRÁFICOS
        ========================================== -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
            <!-- Gráfico de Barras -->
            <div class="panel">
                <h3 style="font-size:14.5px;font-weight:700;color:var(--blue-800);margin-bottom:14px;">
                    <i class="fas fa-chart-bar" style="color:var(--blue-400);margin-right:6px;"></i> 
                    Entradas vs Saídas
                </h3>
                <canvas id="chartBarras" height="200"></canvas>
            </div>
            
            <!-- Gráfico de Linha -->
            <div class="panel">
                <h3 style="font-size:14.5px;font-weight:700;color:var(--blue-800);margin-bottom:14px;">
                    <i class="fas fa-chart-line" style="color:var(--blue-400);margin-right:6px;"></i> 
                    Evolução do Saldo
                </h3>
                <canvas id="chartLinha" height="200"></canvas>
            </div>
        </div>

        <!-- ==========================================
        TABELA MENSAL
        ========================================== -->
        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 style="font-size:14.5px;font-weight:700;color:var(--blue-800);">
                    <i class="fas fa-list"></i> Resumo Mensal - <?= $ano ?>
                </h3>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-100);">
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Mês</th>
                            <th style="padding:10px 12px;text-align:right;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Entradas (Kz)</th>
                            <th style="padding:10px 12px;text-align:right;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Saídas (Kz)</th>
                            <th style="padding:10px 12px;text-align:right;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Saldo (Kz)</th>
                            <th style="padding:10px 12px;text-align:right;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Saldo Acumulado</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $saldo_acumulado = 0;
                        $total_entradas_tabela = 0;
                        $total_saidas_tabela = 0;
                        
                        for ($mes = 1; $mes <= 12; $mes++):
                            $entradas = $entradas_mensais[$mes];
                            $saidas = $saidas_mensais[$mes];
                            $saldo = $entradas - $saidas;
                            $saldo_acumulado += $saldo;
                            $total_entradas_tabela += $entradas;
                            $total_saidas_tabela += $saidas;
                            
                            $cor_saldo = $saldo >= 0 ? '#3f7d4e' : '#b5412f';
                            $cor_acumulado = $saldo_acumulado >= 0 ? '#3f7d4e' : '#b5412f';
                            $status = $saldo >= 0 ? '✅ Positivo' : '❌ Negativo';
                        ?>
                        <tr style="border-bottom:1px solid var(--gray-100);">
                            <td style="padding:10px 12px;font-weight:600;color:var(--gray-800);">
                                <?= getNomeMes($mes) ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;color:var(--blue-600);font-weight:600;">
                                <?= number_format($entradas, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;color:var(--danger);font-weight:600;">
                                <?= number_format($saidas, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-weight:700;color:<?= $cor_saldo ?>;">
                                <?= number_format($saldo, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-weight:700;color:<?= $cor_acumulado ?>;">
                                <?= number_format($saldo_acumulado, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= $saldo >= 0 ? '#eaf3ec' : '#f9e9e5' ?>;color:<?= $saldo >= 0 ? '#3f7d4e' : '#b5412f' ?>;font-size:11px;font-weight:600;">
                                    <?= $status ?>
                                </span>
                            </td>
                        </tr>
                        <?php endfor; ?>
                        
                        <!-- TOTAL GERAL -->
                        <tr style="background:var(--gray-50);font-weight:700;border-top:2px solid var(--gray-300);">
                            <td style="padding:10px 12px;font-size:15px;color:var(--blue-800);">TOTAL GERAL</td>
                            <td style="padding:10px 12px;text-align:right;font-size:15px;color:var(--blue-600);">
                                <?= number_format($total_entradas_tabela, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-size:15px;color:var(--danger);">
                                <?= number_format($total_saidas_tabela, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-size:16px;color:<?= ($total_entradas_tabela - $total_saidas_tabela) >= 0 ? '#3f7d4e' : '#b5412f' ?>;">
                                <?= number_format($total_entradas_tabela - $total_saidas_tabela, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-size:16px;color:<?= $saldo_acumulado >= 0 ? '#3f7d4e' : '#b5412f' ?>;">
                                <?= number_format($saldo_acumulado, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= ($total_entradas_tabela - $total_saidas_tabela) >= 0 ? '#eaf3ec' : '#f9e9e5' ?>;color:<?= ($total_entradas_tabela - $total_saidas_tabela) >= 0 ? '#3f7d4e' : '#b5412f' ?>;font-size:12px;font-weight:700;">
                                    <?= ($total_entradas_tabela - $total_saidas_tabela) >= 0 ? '✅ SUPERÁVIT' : '❌ DÉFICIT' ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- LEGENDA -->
            <div style="margin-top:16px;padding:12px;background:var(--gray-50);border-radius:var(--radius);font-size:12px;color:var(--gray-500);display:flex;flex-wrap:wrap;gap:16px;">
                <span><span style="display:inline-block;width:12px;height:12px;background:#eaf3ec;border-radius:2px;margin-right:4px;"></span> Saldo positivo</span>
                <span><span style="display:inline-block;width:12px;height:12px;background:#f9e9e5;border-radius:2px;margin-right:4px;"></span> Saldo negativo</span>
                <span><i class="fas fa-arrow-down" style="color:#3f7d4e;"></i> Entradas</span>
                <span><i class="fas fa-arrow-up" style="color:#b5412f;"></i> Saídas</span>
            </div>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ============================================
// GRÁFICO DE BARRAS
// ============================================
const ctx1 = document.getElementById('chartBarras').getContext('2d');
const labels = <?= json_encode($labels) ?>;
const entradas = <?= json_encode($entradas_dados) ?>;
const saidas = <?= json_encode($saidas_dados) ?>;

new Chart(ctx1, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Entradas',
                data: entradas,
                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                borderColor: '#3f7d4e',
                borderWidth: 1,
                borderRadius: 4,
            },
            {
                label: 'Saídas',
                data: saidas,
                backgroundColor: 'rgba(239, 68, 68, 0.7)',
                borderColor: '#b5412f',
                borderWidth: 1,
                borderRadius: 4,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    font: { family: 'Inter', size: 11 },
                    boxWidth: 15,
                    padding: 15
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString() + ' Kz';
                    },
                    font: { size: 10 }
                }
            },
            x: {
                ticks: { font: { size: 10 } }
            }
        }
    }
});

// ============================================
// GRÁFICO DE LINHA (SALDO)
// ============================================
const ctx2 = document.getElementById('chartLinha').getContext('2d');
const saldos = <?= json_encode($saldos) ?>;

new Chart(ctx2, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Saldo Acumulado',
            data: saldos,
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
            legend: {
                position: 'top',
                labels: {
                    font: { family: 'Inter', size: 11 },
                    boxWidth: 15,
                    padding: 15
                }
            }
        },
        scales: {
            y: {
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString() + ' Kz';
                    },
                    font: { size: 10 }
                }
            },
            x: {
                ticks: { font: { size: 10 } }
            }
        }
    }
});

// ============================================
// FUNÇÕES
// ============================================
function gerarPDF() {
    const params = new URLSearchParams(window.location.search);
    window.open('<?= url('modules/reports/generate-pdf.php') ?>?' + params.toString(), '_blank');
}

function mudarFundo(valor) {
    window.location.href = '<?= url('modules/reports/annual.php') ?>?ano=<?= $ano ?>&fundo=' + valor;
}
</script>