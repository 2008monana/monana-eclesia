<?php
// modules/lists/papas.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('lists');
$page_title = 'Lista de Papás - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once 'functions.php';

$conn = getConnection();

// ============================================
// PARÂMETROS
// ============================================
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
$ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));
$categoria = 'papa';

if ($mes < 1 || $mes > 12) $mes = date('m');

// ============================================
// BUSCAR DOMINGOS DO MÊS
// ============================================
$domingos = getDomingosDoMes($mes, $ano);

// ============================================
// BUSCAR MEMBROS DA CATEGORIA
// ============================================
$stmt = $conn->prepare("
    SELECT id, nome_completo, telefone, categoria
    FROM membros
    WHERE categoria = :categoria AND ativo = 1
    ORDER BY nome_completo ASC
");
$stmt->execute([':categoria' => $categoria]);
$membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// CALCULAR DADOS DE CADA MEMBRO
// ============================================
$categoria_cores = [
    'mama'  => ['label' => 'Mamã', 'color' => '#b5527a'],
    'papa'  => ['label' => 'Papá', 'color' => '#3f7d4e'],
    'jovem' => ['label' => 'Jovem', 'color' => '#7a5ca8']
];

$total_membros = count($membros);
$total_saldo_geral = 0;
$total_quitados = 0;
$total_devendo = 0;

foreach ($membros as &$membro) {
    // Pagamentos do mês (para mostrar nos domingos)
    $pagamentos = getPagamentosMembro($membro['id'], $mes, $ano);
    $membro['pagamentos'] = $pagamentos;
    
    // Total pago no mês
    $total_mes = 0;
    foreach ($pagamentos as $p) {
        $total_mes += $p['valor'];
    }
    $membro['total_mes'] = $total_mes;

    // Se este mês não tem valor em nenhum domingo mas já foi quitado por
    // um pagamento feito noutro mês (ex: dívida de Janeiro paga em Agosto),
    // guarda essa informação para mostrar o selo "Quitado (pago em ...)"
    $membro['quitacao_posterior'] = null;
    if ($total_mes == 0) {
        $pagamentos_quitacao = getPagamentoQuitacaoMes($membro['id'], $mes, $ano);
        if (!empty($pagamentos_quitacao)) {
            $membro['quitacao_posterior'] = $pagamentos_quitacao[0];
        }
    }
    
    // Saldo acumulado
    $saldo_info = calcularSaldoMembro($membro['id'], $mes, $ano);
    $membro['saldo'] = $saldo_info['saldo'];
    $membro['total_pago'] = $saldo_info['total_pago'];
    $membro['deveria_pagar'] = $saldo_info['deveria_pagar'];
    $membro['meses_quitados'] = $saldo_info['meses_quitados'];
    $membro['meses_devendo'] = $saldo_info['meses_devendo'];
    
    $total_saldo_geral += $saldo_info['saldo'];
    if ($saldo_info['saldo'] >= 0) {
        $total_quitados++;
    } else {
        $total_devendo++;
    }
}
unset($membro);

// ============================================
// NOME DO MÊS
// ============================================
$mes_exibicao = nomeMesPT($mes);
$cat_info = getCategoriaLabel($categoria);
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas <?= $cat_info['icon'] ?>" style="color:<?= $cat_info['color'] ?>;"></i> Lista de <?= $cat_info['label'] ?></h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button onclick="gerarPDF()" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-file-pdf"></i> Gerar PDF
                </button>
                <a href="<?= url('modules/lists/index.php') ?>" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <!-- ==========================================
        FILTRO
        ========================================== -->
        <div class="panel" style="margin-bottom:20px;">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Mês</label>
                    <select name="mes" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $mes == $i ? 'selected' : '' ?>>
                            <?= nomeMesPT($i) ?>
                        </option>
                        <?php endfor; ?>
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
                    <div style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-size:13px;color:var(--gray-600);background:var(--gray-100);"><?= $ano ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="submit" class="btn-primary" style="padding:8px 20px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
                <div>
                    <a href="<?= url('modules/lists/papas.php') ?>" style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;display:inline-block;">
                        <i class="fas fa-undo"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- ==========================================
        RESUMO
        ========================================== -->
        <div class="grid-stats" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background:<?= $cat_info['color'] ?>20;color:<?= $cat_info['color'] ?>;"><i class="fas fa-users"></i></div>
                <div class="stat-num"><?= $total_membros ?></div>
                <div class="stat-label">Total de <?= $cat_info['label'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-check-circle"></i></div>
                <div class="stat-num"><?= $total_quitados ?></div>
                <div class="stat-label">Em dia (saldo ≥ 0)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f9e9e5;color:#b5412f;"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-num"><?= $total_devendo ?></div>
                <div class="stat-label">Em dívida (saldo < 0)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-coins"></i></div>
                <div class="stat-num"><?= number_format($total_saldo_geral, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Saldo Geral (crédito)</div>
            </div>
        </div>

        <!-- ==========================================
        TABELA
        ========================================== -->
        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 style="font-size:14px;font-weight:700;color:var(--blue-800);">
                    <i class="fas fa-list"></i> Lista de <?= $cat_info['label'] ?> - <?= $mes_exibicao . '/' . $ano ?>
                    <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(<?= $total_membros ?> membros)</span>
                </h3>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="text" id="busca_nome" placeholder="Pesquisar nome..." oninput="filtrarTabela()"
                           style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                    <select id="filtro_status" onchange="filtrarTabela()"
                            style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="">Todos (pagou e não pagou)</option>
                        <option value="pago">Pagou (em dia)</option>
                        <option value="pendente">Não pagou (em dívida)</option>
                    </select>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-100);">
                            <th style="padding:10px 12px;text-align:left;">#</th>
                            <th style="padding:10px 12px;text-align:left;">Nome</th>
                            <?php foreach ($domingos as $domingo): ?>
                            <th style="padding:10px 12px;text-align:center;font-size:11px;">
                                <?= date('d/m', strtotime($domingo)) ?>
                            </th>
                            <?php endforeach; ?>
                            <th style="padding:10px 12px;text-align:right;">Total Mês (Kz)</th>
                            <th style="padding:10px 12px;text-align:right;">Total Pago</th>
                            <th style="padding:10px 12px;text-align:right;">Saldo (Kz)</th>
                            <th style="padding:10px 12px;text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="tabela_membros">
                        <?php if (empty($membros)): ?>
                        <tr>
                            <td colspan="<?= count($domingos) + 6 ?>" style="padding:40px;text-align:center;color:var(--gray-400);">
                                <i class="fas fa-users" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.3;"></i>
                                Nenhum membro encontrado.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($membros as $index => $membro):
                            $saldo = $membro['saldo'];
                            $status_class = $saldo >= 0 ? '#eaf3ec' : '#f9e9e5';
                            $status_color = $saldo >= 0 ? '#3f7d4e' : '#b5412f';
                            $status_text = $saldo >= 0 ? '✅ Em dia' : '❌ Em dívida';
                        ?>
                        <tr class="linha-membro" data-nome="<?= htmlspecialchars(mb_strtolower($membro['nome_completo'])) ?>" data-status="<?= $saldo >= 0 ? 'pago' : 'pendente' ?>" style="border-bottom:1px solid var(--gray-100);">
                            <td style="padding:10px 12px;color:var(--gray-500);"><?= $index + 1 ?></td>
                            <td style="padding:10px 12px;font-weight:600;color:var(--gray-800);">
                                <a href="javascript:void(0)" onclick="abrirHistoricoPagamentos(<?= $membro['id'] ?>, '<?= htmlspecialchars($membro['nome_completo'], ENT_QUOTES) ?>')"
                                   style="color:var(--gray-800);text-decoration:none;border-bottom:1px dashed var(--gray-300);cursor:pointer;" title="Ver histórico de pagamentos">
                                    <?= htmlspecialchars($membro['nome_completo']) ?>
                                </a>
                            </td>
                            <?php foreach ($domingos as $domingo): ?>
                            <td style="padding:10px 12px;text-align:center;">
                                <?php
                                $valor = getStatusPagamento($membro['pagamentos'], $domingo);
                                if ($valor !== null):
                                ?>
                                <span style="display:inline-block;padding:2px 8px;border-radius:4px;background:#eaf3ec;color:#3f7d4e;font-weight:600;font-size:12px;">
                                    <?= number_format($valor, 2, ',', '.') ?>
                                </span>
                                <?php else: ?>
                                <span style="color:var(--gray-300);font-size:16px;">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                            <td style="padding:10px 12px;text-align:right;font-weight:700;color:var(--blue-800);">
                                <?= number_format($membro['total_mes'], 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-weight:700;color:var(--blue-800);">
                                <?= number_format($membro['total_pago'], 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-weight:700;color:<?= $saldo >= 0 ? '#3f7d4e' : '#b5412f' ?>;">
                                <?= number_format($saldo, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= $status_class ?>;color:<?= $status_color ?>;font-size:11px;font-weight:600;">
                                    <?= $status_text ?>
                                </span>
                                <?php if ($membro['quitacao_posterior']): ?>
                                <br>
                                <span style="display:inline-block;margin-top:4px;padding:2px 10px;border-radius:20px;background:#f7edcf;color:#c99a2e;font-size:10px;font-weight:600;white-space:nowrap;">
                                    <i class="fas fa-check-double"></i> Quitado (pago em <?= date('d/m/Y', strtotime($membro['quitacao_posterior']['data_pagamento'])) ?>)
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- LEGENDA -->
            <div style="margin-top:16px;padding:12px;background:var(--gray-50);border-radius:var(--radius);font-size:12px;color:var(--gray-500);display:flex;flex-wrap:wrap;gap:16px;">
                <span><span style="display:inline-block;width:12px;height:12px;background:#eaf3ec;border-radius:2px;margin-right:4px;"></span> Valor pago no domingo</span>
                <span><span style="display:inline-block;width:12px;height:12px;background:transparent;border:1px solid var(--gray-300);border-radius:2px;margin-right:4px;text-align:center;color:var(--gray-300);">—</span> Não pagou neste domingo</span>
                <span><span style="display:inline-block;width:12px;height:12px;background:#eaf3ec;border-radius:2px;margin-right:4px;"></span> Saldo positivo (crédito)</span>
                <span><span style="display:inline-block;width:12px;height:12px;background:#f9e9e5;border-radius:2px;margin-right:4px;"></span> Saldo negativo (dívida)</span>
                <span><span style="display:inline-block;width:12px;height:12px;background:#f7edcf;border-radius:2px;margin-right:4px;"></span> Quitado por pagamento feito noutro mês</span>
            </div>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>
<?php require_once '../../includes/modal-historico-pagamentos.php'; ?>

<script>
function gerarPDF() {
    const params = new URLSearchParams(window.location.search);
    window.open('<?= url('modules/lists/generate-pdf.php') ?>?categoria=papa&' + params.toString(), '_blank');
}

function filtrarTabela() {
    const busca = document.getElementById('busca_nome').value.trim().toLowerCase();
    const status = document.getElementById('filtro_status').value;
    document.querySelectorAll('#tabela_membros tr.linha-membro').forEach(function(tr) {
        const nomeOk = !busca || tr.dataset.nome.includes(busca);
        const statusOk = !status || tr.dataset.status === status;
        tr.style.display = (nomeOk && statusOk) ? '' : 'none';
    });
}
</script>