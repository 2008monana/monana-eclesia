<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-money-bill-wave"></i> Relatório de Saídas
                <span style="font-weight:400;font-size:13px;color:var(--gray-500);">- Fundo: <?= $fundos_disponiveis[$fundo] ?></span>
            </h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button onclick="gerarPDF()" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-file-pdf"></i> Gerar PDF
                </button>
            </div>
        </div>

        <!-- ==========================================
        FILTRO
        ========================================== -->
        <div class="panel" style="margin-bottom:20px;">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Ano</label>
                    <select name="ano" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <?php for ($i = 2026; $i <= date('Y') + 1; $i++): ?>
                        <option value="<?= $i ?>" <?= $ano == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Mês</label>
                    <select name="mes" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="0">Todos</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $mes == $i ? 'selected' : '' ?>><?= getNomeMes($i) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Fundo</label>
                    <select name="fundo" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <?php foreach ($fundos_disponiveis as $valor => $label): ?>
                        <option value="<?= $valor ?>" <?= $fundo == $valor ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($categorias_list)): ?>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Categoria</label>
                    <select name="categoria" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="">Todas</option>
                        <?php foreach ($categorias_list as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $categoria == $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <button type="submit" class="btn-primary" style="padding:8px 20px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
                <div>
                    <a href="<?= url('modules/reports/expenses.php') ?>" style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;display:inline-block;">
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
                <div class="stat-icon" style="background:#f9e9e5;color:#b5412f;"><i class="fas fa-coins"></i></div>
                <div class="stat-num"><?= number_format($total_saidas, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Total de Saídas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-list"></i></div>
                <div class="stat-num"><?= count($saidas) ?></div>
                <div class="stat-label">Total de Registros</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fdeee3;color:#b9770e;"><i class="fas fa-tags"></i></div>
                <div class="stat-num"><?= count($categorias_saidas) ?></div>
                <div class="stat-label">Categorias</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-calculator"></i></div>
                <div class="stat-num"><?= count($saidas) > 0 ? number_format($total_saidas / count($saidas), 2, ',', '.') : '0,00' ?> Kz</div>
                <div class="stat-label">Média por Saída</div>
            </div>
        </div>

        <!-- ==========================================
        RESUMO POR CATEGORIA
        ========================================== -->
        <?php if (!empty($categorias_saidas)): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(200px,1fr));gap:12px;margin-bottom:20px;">
            <?php foreach ($categorias_saidas as $cat => $total): ?>
            <div style="background:var(--white);border-radius:var(--radius);padding:14px;box-shadow:var(--shadow);border:1px solid var(--gray-100);">
                <div style="font-weight:700;color:var(--gray-700);font-size:12px;"><?= htmlspecialchars($cat) ?></div>
                <div style="font-size:18px;font-weight:800;color:var(--danger);"><?= number_format($total, 2, ',', '.') ?> Kz</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ==========================================
        TABELA DE SAÍDAS
        ========================================== -->
        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 style="font-size:14.5px;font-weight:700;color:var(--blue-800);">
                    <i class="fas fa-list"></i> Lista de Saídas
                    <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(<?= count($saidas) ?> registros)</span>
                </h3>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-100);">
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">#</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Data</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Descrição</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Categoria</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Beneficiário</th>
                            <th style="padding:10px 12px;text-align:right;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Valor (Kz)</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($saidas)): ?>
                        <tr>
                            <td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);">
                                <i class="fas fa-money-bill-wave" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.3;"></i>
                                Nenhuma saída encontrada.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($saidas as $index => $s): ?>
                        <tr style="border-bottom:1px solid var(--gray-100);">
                            <td style="padding:10px 12px;color:var(--gray-500);"><?= $index + 1 ?></td>
                            <td style="padding:10px 12px;color:var(--gray-700);font-size:12px;">
                                <?= date('d/m/Y', strtotime($s['data_saida'])) ?>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-800);">
                                <?= htmlspecialchars($s['descricao']) ?>
                            </td>
                            <td style="padding:10px 12px;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:var(--blue-100);color:var(--blue-700);font-size:11px;font-weight:600;">
                                    <?= htmlspecialchars($s['categoria'] ?? 'Outros') ?>
                                </span>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-600);">
                                <?= htmlspecialchars($s['beneficiario'] ?? '-') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-weight:700;color:var(--danger);">
                                <?= number_format($s['valor'], 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-500);font-size:12px;">
                                <?= htmlspecialchars($s['registrado_nome'] ?? '-') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- TOTAL GERAL -->
                        <tr style="background:var(--gray-50);font-weight:700;border-top:2px solid var(--gray-300);">
                            <td colspan="5" style="padding:10px 12px;text-align:right;font-size:15px;color:var(--blue-800);">
                                TOTAL GERAL:
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-size:16px;color:var(--danger);">
                                <?= number_format($total_saidas, 2, ',', '.') ?> Kz
                            </td>
                            <td></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>

<script>
function gerarPDF() {
    const params = new URLSearchParams(window.location.search);
    window.open('<?= url('modules/reports/generate-expenses-pdf.php') ?>?' + params.toString(), '_blank');
}
</script>
