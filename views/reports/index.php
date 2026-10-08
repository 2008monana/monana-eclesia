<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-chart-bar"></i> Relatório Financeiro
                <span style="font-weight:400;font-size:13px;color:var(--gray-500);">- Fundo: <?= $fundos_disponiveis[$fundo] ?></span>
            </h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button onclick="gerarPDF()" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-file-pdf"></i> Gerar PDF
                </button>
                <a href="<?= url('modules/reports/export-excel.php') ?>?ano=<?= $ano ?>&mes_inicio=<?= $mes_inicio ?>&mes_fim=<?= $mes_fim ?>&fundo=<?= $fundo ?>" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--green),var(--success));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </a>
            </div>
        </div>

        <!-- ==========================================
        FILTRO
        ========================================== -->
        <div class="panel" style="margin-bottom:20px;">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Fundo</label>
                    <select name="fundo" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <?php foreach ($fundos_disponiveis as $valor => $label): ?>
                        <option value="<?= $valor ?>" <?= $fundo == $valor ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Ano</label>
                    <?php $anos_disponiveis = getAnosDisponiveis(); ?>
                    <?php if (count($anos_disponiveis) > 1): ?>
                    <select name="ano" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <?php foreach ($anos_disponiveis as $i): ?>
                        <option value="<?= $i ?>" <?= $ano == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="hidden" name="ano" value="<?= $ano ?>">
                    <div style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-size:13px;color:var(--gray-600);background:var(--gray-100);">
                        <?= $ano ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Mês Início</label>
                    <select name="mes_inicio" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $mes_inicio == $i ? 'selected' : '' ?>><?= getNomeMes($i) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Mês Fim</label>
                    <select name="mes_fim" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $mes_fim == $i ? 'selected' : '' ?>><?= getNomeMes($i) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-primary" style="padding:8px 20px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
                <div>
                    <a href="<?= url('modules/reports/index.php') ?>" style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;display:inline-block;">
                        <i class="fas fa-undo"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- ==========================================
        RESUMO (CARDS)
        ========================================== -->
        <div class="grid-stats" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-arrow-down"></i></div>
                <div class="stat-num"><?= number_format($total_entradas_periodo, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Total de Entradas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f9e9e5;color:#b5412f;"><i class="fas fa-arrow-up"></i></div>
                <div class="stat-num"><?= number_format($total_saidas_periodo, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Total de Saídas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fdeee3;color:#b9770e;"><i class="fas fa-calculator"></i></div>
                <div class="stat-num"><?= number_format($total_entradas_periodo - $total_saidas_periodo, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Saldo do Período</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-num"><?= count($meses_com_dados) ?></div>
                <div class="stat-label">Meses com Movimento</div>
            </div>
        </div>

        <!-- ==========================================
        GRÁFICO DE BARRAS (ENTRADAS VS SAÍDAS)
        ========================================== -->
        <div class="panel" style="margin-bottom:20px;">
            <h3 style="font-size:14.5px;font-weight:700;color:var(--blue-800);margin-bottom:14px;">
                <i class="fas fa-chart-bar" style="color:var(--blue-400);margin-right:6px;"></i> 
                Evolução Mensal - <?= $ano ?>
            </h3>
            <canvas id="chartBarras" height="200"></canvas>
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
                            <th style="padding:10px 12px;text-align:center;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_entradas_mostrar = 0;
                        $total_saidas_mostrar = 0;
                        foreach ($dados_mensais as $dado):
                            $total_entradas_mostrar += $dado['entradas'];
                            $total_saidas_mostrar += $dado['saidas'];
                            $saldo = $dado['saldo'];
                            $cor_saldo = $saldo >= 0 ? '#3f7d4e' : '#b5412f';
                            $status = $saldo >= 0 ? '✅ Positivo' : '❌ Negativo';
                        ?>
                        <tr style="border-bottom:1px solid var(--gray-100);">
                            <td style="padding:10px 12px;font-weight:600;color:var(--gray-800);">
                                <?= $dado['nome_mes'] ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;color:var(--blue-600);font-weight:600;">
                                <?= number_format($dado['entradas'], 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;color:var(--danger);font-weight:600;">
                                <?= number_format($dado['saidas'], 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-weight:700;color:<?= $cor_saldo ?>;">
                                <?= number_format($saldo, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= $saldo >= 0 ? '#eaf3ec' : '#f9e9e5' ?>;color:<?= $saldo >= 0 ? '#3f7d4e' : '#b5412f' ?>;font-size:11px;font-weight:600;">
                                    <?= $status ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- TOTAL GERAL -->
                        <tr style="background:var(--gray-50);font-weight:700;border-top:2px solid var(--gray-300);">
                            <td style="padding:10px 12px;font-size:15px;color:var(--blue-800);">TOTAL GERAL</td>
                            <td style="padding:10px 12px;text-align:right;font-size:15px;color:var(--blue-600);">
                                <?= number_format($total_entradas_mostrar, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-size:15px;color:var(--danger);">
                                <?= number_format($total_saidas_mostrar, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-size:16px;color:<?= ($total_entradas_mostrar - $total_saidas_mostrar) >= 0 ? '#3f7d4e' : '#b5412f' ?>;">
                                <?= number_format($total_entradas_mostrar - $total_saidas_mostrar, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= ($total_entradas_mostrar - $total_saidas_mostrar) >= 0 ? '#eaf3ec' : '#f9e9e5' ?>;color:<?= ($total_entradas_mostrar - $total_saidas_mostrar) >= 0 ? '#3f7d4e' : '#b5412f' ?>;font-size:12px;font-weight:700;">
                                    <?= ($total_entradas_mostrar - $total_saidas_mostrar) >= 0 ? '✅ SUPERÁVIT' : '❌ DÉFICIT' ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- LEGENDA -->
            <div style="margin-top:16px;padding:12px;background:var(--gray-50);border-radius:var(--radius);font-size:12px;color:var(--gray-500);display:flex;flex-wrap:wrap;gap:16px;">
                <span><span style="display:inline-block;width:12px;height:12px;background:#eaf3ec;border-radius:2px;margin-right:4px;"></span> Saldo positivo (entradas > saídas)</span>
                <span><span style="display:inline-block;width:12px;height:12px;background:#f9e9e5;border-radius:2px;margin-right:4px;"></span> Saldo negativo (saídas > entradas)</span>
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
const ctx = document.getElementById('chartBarras').getContext('2d');

// Preparar dados para o gráfico
const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
const entradas = <?= json_encode(array_values($entradas_mensais)) ?>;
const saidas = <?= json_encode(array_values($saidas_mensais)) ?>;

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: meses,
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
                    font: {
                        family: 'Inter',
                        size: 11
                    },
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
                    font: {
                        size: 10
                    }
                }
            },
            x: {
                ticks: {
                    font: {
                        size: 10
                    }
                }
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
</script>
