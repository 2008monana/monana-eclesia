<?php
// modules/lists/index.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('lists');
$page_title = 'Lista de Todos os Membros - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once 'functions.php';

$conn = getConnection();

// ============================================
// PARÂMETROS DE FILTRO
// ============================================
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
$ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));

// Validar
if ($mes < 1 || $mes > 12) $mes = date('m');

// ============================================
// BUSCAR DOMINGOS DO MÊS
// ============================================
$domingos = getDomingosDoMes($mes, $ano);

// ============================================
// BUSCAR TODOS OS MEMBROS ATIVOS (TODAS CATEGORIAS)
// ============================================
$stmt = $conn->prepare("
    SELECT id, nome_completo, telefone, categoria
    FROM membros
    WHERE ativo = 1
    ORDER BY
        CASE categoria
            WHEN 'mama'    THEN 1
            WHEN 'papa'    THEN 2
            WHEN 'jovem'   THEN 3
            WHEN 'crianca' THEN 4
        END,
        nome_completo ASC
");
$stmt->execute();
$membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// CALCULAR PAGAMENTOS E TOTAIS
// ============================================
$categoria_cores = [
    'mama'    => ['label' => 'Mamã',    'color' => '#b5527a'],
    'papa'    => ['label' => 'Papá',    'color' => '#3f7d4e'],
    'jovem'   => ['label' => 'Jovem',   'color' => '#7a5ca8'],
    'crianca' => ['label' => 'Criança', 'color' => '#c99a2e']
];

$total_membros = count($membros);
$membros_pagantes = 0;
$total_arrecadado = 0;

foreach ($membros as &$membro) {
    $pagamentos = getPagamentosMembro($membro['id'], $mes, $ano);
    $membro['pagamentos'] = $pagamentos;

    $total_membro = 0;
    foreach ($pagamentos as $p) {
        $total_membro += $p['valor'];
    }
    $membro['total'] = $total_membro;

    if ($total_membro > 0) {
        $membros_pagantes++;
        $total_arrecadado += $total_membro;
    }

    // Status do mês baseado no mes_referencia (não no calendário): um
    // pagamento recebido este mês mas usado para saldar um mês anterior
    // em dívida NÃO quita este mês - continua pendente aqui.
    $status_mes = verificarStatusMes($membro['id'], $mes, $ano);
    $membro['quitado'] = $status_mes['quitado'];

    // Se este mês não tem valor pago mas já foi quitado por um pagamento
    // feito noutro mês (ex: dívida de Janeiro paga em Agosto), guarda essa
    // informação para mostrar o selo "Quitado (pago em ...)".
    $membro['quitacao_posterior'] = null;
    if ($membro['quitado'] && $total_membro == 0) {
        $pagamentos_quitacao = getPagamentoQuitacaoMes($membro['id'], $mes, $ano);
        if (!empty($pagamentos_quitacao)) {
            $membro['quitacao_posterior'] = $pagamentos_quitacao[0];
        }
    }
}
unset($membro);

// ============================================
// NOME DO MÊS PARA EXIBIÇÃO
// ============================================
$mes_exibicao = nomeMesPT($mes);
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-file-alt"></i> Lista de Todos os Membros</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button onclick="gerarPDF()" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-file-pdf"></i> Gerar PDF
                </button>
            </div>
        </div>

        <!-- ==========================================
        FILTRO POR MÊS E ANO
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
                    <a href="<?= url('modules/lists/index.php') ?>" style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;display:inline-block;">
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
                <div class="stat-icon" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-users"></i></div>
                <div class="stat-num"><?= $total_membros ?></div>
                <div class="stat-label">Total de Membros</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-user-check"></i></div>
                <div class="stat-num"><?= $membros_pagantes ?></div>
                <div class="stat-label">Pagaram este mês</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fdeee3;color:#b9770e;"><i class="fas fa-user-slash"></i></div>
                <div class="stat-num"><?= $total_membros - $membros_pagantes ?></div>
                <div class="stat-label">Não pagaram este mês</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-coins"></i></div>
                <div class="stat-num"><?= number_format($total_arrecadado, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Total Arrecadado</div>
            </div>
        </div>

        <!-- ==========================================
        TABELA PRINCIPAL
        ========================================== -->
        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 style="font-size:14px;font-weight:700;color:var(--blue-800);">
                    <i class="fas fa-list"></i> Lista Geral - <?= $mes_exibicao . '/' . $ano ?>
                    <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(<?= $total_membros ?> membros)</span>
                </h3>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="text" id="busca_nome" placeholder="Pesquisar nome..." oninput="filtrarTabela()"
                           style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                    <select id="filtro_status" onchange="filtrarTabela()"
                            style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="">Todos (pagou e não pagou)</option>
                        <option value="pago">Pagou</option>
                        <option value="pendente">Não pagou</option>
                    </select>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-100);">
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">#</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Nome</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Categoria</th>
                            <?php foreach ($domingos as $domingo): ?>
                            <th style="padding:10px 12px;text-align:center;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);font-size:11px;">
                                <?= date('d/m', strtotime($domingo)) ?>
                            </th>
                            <?php endforeach; ?>
                            <th style="padding:10px 12px;text-align:right;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Total (Kz)</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Status</th>
                        </tr>
                    </thead>
                    <tbody id="tabela_membros">
                        <?php if (empty($membros)): ?>
                        <tr>
                            <td colspan="<?= count($domingos) + 5 ?>" style="padding:40px;text-align:center;color:var(--gray-400);">
                                <i class="fas fa-users" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.3;"></i>
                                Nenhum membro encontrado.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($membros as $index => $membro):
                            $cat = $categoria_cores[$membro['categoria']] ?? ['label' => $membro['categoria'], 'color' => '#gray-500'];
                        ?>
                        <tr class="linha-membro" data-nome="<?= htmlspecialchars(mb_strtolower($membro['nome_completo'])) ?>" data-status="<?= $membro['quitado'] ? 'pago' : 'pendente' ?>" style="border-bottom:1px solid var(--gray-100);transition:background .2s;">
                            <td style="padding:10px 12px;color:var(--gray-500);"><?= $index + 1 ?></td>
                            <td style="padding:10px 12px;font-weight:600;color:var(--gray-800);">
                                <?= htmlspecialchars($membro['nome_completo']) ?>
                            </td>
                            <td style="padding:10px 12px;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= $cat['color'] ?>;color:#fff;font-size:11px;font-weight:600;">
                                    <?= $cat['label'] ?>
                                </span>
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
                                <?= $membro['total'] > 0 ? number_format($membro['total'], 2, ',', '.') : '0,00' ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <?php if ($membro['quitado']): ?>
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:#eaf3ec;color:#3f7d4e;font-size:11px;font-weight:600;">
                                    <i class="fas fa-check-circle"></i> Pago
                                </span>
                                <?php else: ?>
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:#f9e9e5;color:#b5412f;font-size:11px;font-weight:600;">
                                    <i class="fas fa-times-circle"></i> Pendente
                                </span>
                                <?php endif; ?>
                                <?php if ($membro['quitacao_posterior']): ?>
                                <br>
                                <span style="display:inline-block;margin-top:4px;padding:2px 10px;border-radius:20px;background:#f7edcf;color:#c99a2e;font-size:10px;font-weight:600;white-space:nowrap;">
                                    <i class="fas fa-check-double"></i> Pago em <?= date('d/m/Y', strtotime($membro['quitacao_posterior']['data_pagamento'])) ?>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- ==========================================
                        TOTAL GERAL (RODAPÉ DA TABELA)
                        ========================================== -->
                        <tr style="background:var(--gray-50);font-weight:700;border-top:2px solid var(--gray-300);">
                            <td colspan="<?= count($domingos) + 3 ?>" style="padding:10px 12px;text-align:right;font-size:15px;color:var(--blue-800);">
                                TOTAL GERAL:
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-size:16px;color:var(--success);">
                                <?= number_format($total_arrecadado, 2, ',', '.') ?> Kz
                            </td>
                            <td style="padding:10px 12px;text-align:center;font-size:13px;color:var(--gray-600);">
                                <?= $membros_pagantes ?>/<?= $total_membros ?> pagaram
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ==========================================
            LEGENDA
            ========================================== -->
            <div style="margin-top:16px;padding:12px;background:var(--gray-50);border-radius:var(--radius);font-size:12px;color:var(--gray-500);display:flex;flex-wrap:wrap;gap:16px;">
                <span><span style="display:inline-block;width:12px;height:12px;background:#eaf3ec;border-radius:2px;margin-right:4px;"></span> Valor pago no domingo</span>
                <span><span style="display:inline-block;width:12px;height:12px;background:transparent;border:1px solid var(--gray-300);border-radius:2px;margin-right:4px;text-align:center;color:var(--gray-300);">—</span> Não pagou neste domingo</span>
                <span><i class="fas fa-check-circle" style="color:#3f7d4e;"></i> Este mês está quitado</span>
                <span><i class="fas fa-times-circle" style="color:#b5412f;"></i> Este mês ainda está em dívida (mesmo que tenha entrado dinheiro, se foi usado para saldar mês anterior)</span>
                <span><i class="fas fa-check-double" style="color:#c99a2e;"></i> Quitado por pagamento feito noutro mês</span>
            </div>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>

<script>
function gerarPDF() {
    const params = new URLSearchParams(window.location.search);
    window.open('<?= url('modules/lists/generate-pdf.php') ?>?categoria=todos&' + params.toString(), '_blank');
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