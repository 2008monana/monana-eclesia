<?php
// modules/registry/index.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /ipfva-gestao/modules/auth/login.php');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('registry');
$page_title = 'Registo Diário - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$conn = getConnection();

// ============================================
// PARÂMETROS
// ============================================
$data_filtro = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

// Validar data
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_filtro)) {
    $data_filtro = date('Y-m-d');
}

// Verificar se é o dia atual
$hoje = date('Y-m-d');
$is_hoje = ($data_filtro == $hoje);

// ============================================
// BUSCAR REGISTROS DO DIA
// ============================================
$stmt = $conn->prepare("
    SELECT 
        c.*,
        m.nome_completo,
        m.categoria,
        u.nome_completo as registrado_nome
    FROM cotas_diarias c
    JOIN membros m ON c.membro_id = m.id
    LEFT JOIN usuarios u ON c.registrado_por = u.id
    WHERE DATE(c.data_pagamento) = :data
    ORDER BY c.criado_em DESC
");
$stmt->execute([':data' => $data_filtro]);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// CALCULAR TOTAIS
// ============================================
$total_geral = 0;
$total_mamas = 0;
$total_papas = 0;
$total_jovens = 0;
$total_membros_pagaram = [];

foreach ($registros as $r) {
    $total_geral += $r['valor'];
    
    if ($r['categoria'] == 'mama') {
        $total_mamas += $r['valor'];
    } elseif ($r['categoria'] == 'papa') {
        $total_papas += $r['valor'];
    } elseif ($r['categoria'] == 'jovem') {
        $total_jovens += $r['valor'];
    }
    
    if (!in_array($r['membro_id'], $total_membros_pagaram)) {
        $total_membros_pagaram[] = $r['membro_id'];
    }
}

$total_membros_pagaram = count($total_membros_pagaram);

// ============================================
// BUSCAR DATAS COM REGISTROS PARA NAVEGAÇÃO
// ============================================
$stmt = $conn->query("
    SELECT DISTINCT DATE(data_pagamento) as data 
    FROM cotas_diarias 
    ORDER BY data_pagamento DESC 
    LIMIT 30
");
$datas_com_registros = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Mensagem de erro (para edição bloqueada)
if (isset($_SESSION['erro'])) {
    $erro_msg = $_SESSION['erro'];
    unset($_SESSION['erro']);
} else {
    $erro_msg = '';
}
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-calendar-day"></i> Registo Diário</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php if ($is_hoje): ?>
                <a href="<?= url('modules/registry/add.php') ?>" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-plus"></i> Novo Registo
                </a>
                <?php endif; ?>
                <button onclick="gerarPDF()" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-file-pdf"></i> Gerar PDF
                </button>
                <?php if ($_SESSION['user_perfil'] == 'admin'): ?>
                <a href="<?= url('modules/registry/recalcular-referencias.php') ?>" title="Corrige o mês de referência de registos antigos, aplicando a mesma regra de \"mês mais antigo em dívida primeiro\" usada nos registos novos" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);font-weight:700;font-size:13px;">
                    <i class="fas fa-calculator"></i> Recalcular Referências
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($erro_msg): ?>
        <div style="background:#fdf1d9;border:1px solid #f5c6a0;color:#856404;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px;">
            <i class="fas fa-exclamation-triangle"></i> <?= $erro_msg ?>
        </div>
        <?php endif; ?>

        <!-- ==========================================
        NAVEGAÇÃO POR DATA
        ========================================== -->
        <div class="panel" style="margin-bottom:20px;">
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
                <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                    <a href="?data=<?= date('Y-m-d', strtotime($data_filtro . ' -1 day')) ?>" 
                       style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                    
                    <div style="display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-calendar-alt" style="color:var(--blue-400);"></i>
                        <strong style="font-size:16px;color:var(--blue-800);">
                            <?= dataPorExtensoPT($data_filtro) ?>
                            <?php if ($is_hoje): ?>
                            <span style="font-size:12px;color:var(--success);font-weight:400;">(Hoje)</span>
                            <?php endif; ?>
                        </strong>
                    </div>
                    
                    <a href="?data=<?= date('Y-m-d', strtotime($data_filtro . ' +1 day')) ?>" 
                       style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;">
                        Próximo <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                
                <form method="GET" action="" style="display:flex;gap:8px;align-items:center;">
                    <input type="date" name="data" value="<?= $data_filtro ?>"
                           style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                    <button type="submit" class="btn-primary" style="padding:8px 16px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                        <i class="fas fa-search"></i> Ir
                    </button>
                    <a href="?data=<?= date('Y-m-d') ?>" style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;">
                        <i class="fas fa-undo"></i> Hoje
                    </a>
                </form>
            </div>
            
            <?php if (!empty($datas_com_registros)): ?>
            <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                <span style="font-size:12px;color:var(--gray-500);font-weight:600;">Datas com registos:</span>
                <?php foreach ($datas_com_registros as $data): ?>
                <a href="?data=<?= $data ?>" 
                   style="padding:4px 12px;border-radius:20px;background:<?= $data == $data_filtro ? 'var(--blue-500)' : 'var(--gray-100)' ?>;color:<?= $data == $data_filtro ? '#fff' : 'var(--gray-600)' ?>;text-decoration:none;font-size:12px;font-weight:600;transition:.2s;">
                    <?= date('d/m', strtotime($data)) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ==========================================
        RESUMO DO DIA (CARDS)
        ========================================== -->
        <div class="grid-stats" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-coins"></i></div>
                <div class="stat-num"><?= number_format($total_geral, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Total Arrecadado</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-users"></i></div>
                <div class="stat-num"><?= $total_membros_pagaram ?></div>
                <div class="stat-label">Membros Pagaram</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fce4ec;color:#b5527a;"><i class="fas fa-female"></i></div>
                <div class="stat-num"><?= number_format($total_mamas, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Mamãs</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-male"></i></div>
                <div class="stat-num"><?= number_format($total_papas, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Papás</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#ede7f6;color:#7a5ca8;"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-num"><?= number_format($total_jovens, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Jovens</div>
            </div>
        </div>

        <!-- ==========================================
        TABELA DE REGISTROS DO DIA
        ========================================== -->
        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 style="font-size:14px;font-weight:700;color:var(--blue-800);">
                    <i class="fas fa-list"></i> Registros do Dia 
                    <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(<?= count($registros) ?> registros)</span>
                </h3>
            </div>
            
            <?php if (empty($registros)): ?>
            <div style="padding:40px;text-align:center;color:var(--gray-400);">
                <i class="fas fa-calendar-day" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.3;"></i>
                Nenhum registro encontrado para esta data.
                <?php if ($is_hoje): ?>
                <br>
                <a href="<?= url('modules/registry/add.php') ?>" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;margin-top:12px;">
                    <i class="fas fa-plus"></i> Adicionar Registo
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-100);">
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">#</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Membro</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Categoria</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Mês Referência</th>
                            <th style="padding:10px 12px;text-align:right;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Valor (Kz)</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Registrado por</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $index => $r): 
                            $categoria_labels = [
                                'mama' => ['label' => 'Mamã', 'color' => '#b5527a'],
                                'papa' => ['label' => 'Papá', 'color' => '#3f7d4e'],
                                'jovem' => ['label' => 'Jovem', 'color' => '#7a5ca8']
                            ];
                            $cat = $categoria_labels[$r['categoria']] ?? ['label' => $r['categoria'], 'color' => '#gray-500'];
                        ?>
                        <tr style="border-bottom:1px solid var(--gray-100);">
                            <td style="padding:10px 12px;color:var(--gray-500);"><?= $index + 1 ?></td>
                            <td style="padding:10px 12px;font-weight:600;color:var(--gray-800);">
                                <?= htmlspecialchars($r['nome_completo']) ?>
                            </td>
                            <td style="padding:10px 12px;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= $cat['color'] ?>;color:#fff;font-size:11px;font-weight:600;">
                                    <?= $cat['label'] ?>
                                </span>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-600);font-size:12px;">
                                <?= date('m/Y', strtotime($r['mes_referencia'])) ?>
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-weight:700;color:var(--success);">
                                <?= number_format($r['valor'], 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-500);font-size:12px;">
                                <?= htmlspecialchars($r['registrado_nome'] ?? '-') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                                    <?php if ($is_hoje): ?>
                                    <a href="<?= url('modules/registry/edit.php?id=' . $r['id']) ?>" 
                                       style="padding:6px 10px;border-radius:var(--radius);background:#fdeee3;color:#b9770e;text-decoration:none;font-size:12px;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="deleteRegistry(<?= $r['id'] ?>)" 
                                            style="padding:6px 10px;border-radius:var(--radius);border:none;background:#f9e9e5;color:#b5412f;cursor:pointer;font-size:12px;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <span style="font-size:11px;color:var(--gray-400);font-weight:600;">
                                        <i class="fas fa-lock"></i> Bloqueado
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- TOTAL GERAL DO DIA -->
                        <tr style="background:var(--gray-50);font-weight:700;border-top:2px solid var(--gray-300);">
                            <td colspan="4" style="padding:10px 12px;text-align:right;font-size:15px;color:var(--blue-800);">
                                TOTAL DO DIA:
                            </td>
                            <td style="padding:10px 12px;text-align:right;font-size:16px;color:var(--success);">
                                <?= number_format($total_geral, 2, ',', '.') ?> Kz
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Resumo por Categoria do Dia -->
            <div style="margin-top:16px;display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
                <div style="background:#fce4ec;padding:12px 16px;border-radius:var(--radius);">
                    <div style="font-size:12px;color:#b5527a;font-weight:600;">
                        <i class="fas fa-female"></i> Mamãs
                    </div>
                    <div style="font-size:18px;font-weight:800;color:var(--gray-800);">
                        <?= number_format($total_mamas, 2, ',', '.') ?> Kz
                    </div>
                </div>
                <div style="background:#eaf3ec;padding:12px 16px;border-radius:var(--radius);">
                    <div style="font-size:12px;color:#3f7d4e;font-weight:600;">
                        <i class="fas fa-male"></i> Papás
                    </div>
                    <div style="font-size:18px;font-weight:800;color:var(--gray-800);">
                        <?= number_format($total_papas, 2, ',', '.') ?> Kz
                    </div>
                </div>
                <div style="background:#ede7f6;padding:12px 16px;border-radius:var(--radius);">
                    <div style="font-size:12px;color:#7a5ca8;font-weight:600;">
                        <i class="fas fa-user-graduate"></i> Jovens
                    </div>
                    <div style="font-size:18px;font-weight:800;color:var(--gray-800);">
                        <?= number_format($total_jovens, 2, ',', '.') ?> Kz
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>

<script>
function deleteRegistry(id) {
    if (confirm('Tem certeza que deseja excluir este registro permanentemente?')) {
        fetch('<?= url('modules/registry/delete.php') ?>', {
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

function gerarPDF() {
    const params = new URLSearchParams(window.location.search);
    window.open('<?= url('modules/registry/generate-pdf.php') ?>?' + params.toString(), '_blank');
}
</script>