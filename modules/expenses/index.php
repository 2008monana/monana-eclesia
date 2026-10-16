<?php
// modules/expenses/index.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('expenses');
$page_title = 'Saídas/Despesas - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../reports/functions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$conn = getConnection();

$saldo_cotas = getSaldoFundo('cotas');
$saldo_contribuicoes = getSaldoFundo('contribuicoes');

// ============================================
// FILTROS
// ============================================
$ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : 0;
$categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
// Nunca somar os dois fundos às cegas num único total - por isso não
// existe opção "Todos"; só os dois fundos reais (mesmo critério dos
// relatórios financeiros).
$fundo = isset($_GET['fundo']) ? trim($_GET['fundo']) : 'cotas';
if (!in_array($fundo, ['cotas', 'contribuicoes'])) {
    $fundo = 'cotas';
}

// ============================================
// BUSCAR CATEGORIAS PARA FILTRO
// ============================================
$stmt_cat = $conn->prepare("SELECT DISTINCT categoria FROM saidas WHERE origem_fundo = :fundo ORDER BY categoria");
$stmt_cat->execute([':fundo' => $fundo]);
$categorias_list = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);

// ============================================
// BUSCAR SAÍDAS
// ============================================
$where = "YEAR(data_saida) = :ano";
$params = [':ano' => $ano];

if ($mes > 0) {
    $where .= " AND MONTH(data_saida) = :mes";
    $params[':mes'] = $mes;
}

if (!empty($categoria)) {
    $where .= " AND categoria = :categoria";
    $params[':categoria'] = $categoria;
}

$where .= " AND origem_fundo = :fundo";
$params[':fundo'] = $fundo;

$sql = "SELECT 
            s.*,
            u.nome_completo as registrado_nome
        FROM saidas s
        LEFT JOIN usuarios u ON s.registrado_por = u.id
        WHERE $where
        ORDER BY s.data_saida DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$saidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_saidas = 0;
foreach ($saidas as $s) {
    $total_saidas += $s['valor'];
}
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-money-bill-wave"></i> Saídas/Despesas</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="<?= url('modules/expenses/add.php') ?>" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-plus"></i> Nova Saída
                </a>
            </div>
        </div>

        <!-- FILTRO -->
        <div class="panel" style="margin-bottom:20px;">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
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
                    <div style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-size:13px;color:var(--gray-600);background:var(--gray-100);"><?= $ano ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Mês</label>
                    <select name="mes" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="0">Todos</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $mes == $i ? 'selected' : '' ?>>
                            <?= nomeMesPT($i) ?>
                        </option>
                        <?php endfor; ?>
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
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Fundo</label>
                    <select name="fundo" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="cotas" <?= $fundo == 'cotas' ? 'selected' : '' ?>>Cotas</option>
                        <option value="contribuicoes" <?= $fundo == 'contribuicoes' ? 'selected' : '' ?>>Contribuições</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-primary" style="padding:8px 20px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
                <div>
                    <a href="<?= url('modules/expenses/index.php') ?>" style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;display:inline-block;">
                        <i class="fas fa-undo"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- SALDO POR FUNDO -->
        <div class="grid-stats" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-wallet"></i></div>
                <div class="stat-num"><?= number_format($saldo_cotas, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Saldo disponível - Cotas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#ede7f6;color:#7a5ca8;"><i class="fas fa-piggy-bank"></i></div>
                <div class="stat-num"><?= number_format($saldo_contribuicoes, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Saldo disponível - Contribuições</div>
            </div>
        </div>

        <!-- RESUMO -->
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
                <div class="stat-num"><?= count($categorias_list) ?></div>
                <div class="stat-label">Categorias</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-calculator"></i></div>
                <div class="stat-num"><?= count($saidas) > 0 ? number_format($total_saidas / count($saidas), 2, ',', '.') : '0,00' ?> Kz</div>
                <div class="stat-label">Média por Saída</div>
            </div>
        </div>

        <!-- TABELA -->
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
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Fundo</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Beneficiário</th>
                            <th style="padding:10px 12px;text-align:right;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Valor (Kz)</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($saidas)): ?>
                        <tr>
                            <td colspan="8" style="padding:40px;text-align:center;color:var(--gray-400);">
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
                            <td style="padding:10px 12px;">
                                <?php $fundo = getFundoLabel($s['origem_fundo'] ?? 'cotas'); ?>
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= $fundo['color'] ?>;color:#fff;font-size:11px;font-weight:600;">
                                    <?= $fundo['label'] ?>
                                </span>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-600);">
                                <?= htmlspecialchars($s['beneficiario'] ?? '-') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-weight:700;color:var(--danger);">
                                <?= number_format($s['valor'], 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                                    <a href="<?= url('modules/expenses/edit.php?id=' . $s['id']) ?>" 
                                       style="padding:6px 10px;border-radius:var(--radius);background:#fdeee3;color:#b9770e;text-decoration:none;font-size:12px;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="deleteExpense(<?= $s['id'] ?>)" 
                                            style="padding:6px 10px;border-radius:var(--radius);border:none;background:#f9e9e5;color:#b5412f;cursor:pointer;font-size:12px;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:var(--gray-50);font-weight:700;border-top:2px solid var(--gray-300);">
                            <td colspan="6" style="padding:10px 12px;text-align:right;font-size:15px;color:var(--blue-800);">
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
function deleteExpense(id) {
    if (confirm('Tem certeza que deseja excluir esta saída permanentemente?')) {
        fetch('<?= url('modules/expenses/delete.php') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao excluir: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro na requisição: ' + error);
        });
    }
}
</script>