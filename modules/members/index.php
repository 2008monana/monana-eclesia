<?php
// modules/members/index.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('modules/auth/login.php');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('members');
$page_title = 'Membros - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$conn = getConnection();

// Filtros: a pesquisa por nome/telefone, categoria e status agora são
// aplicados inteiramente no navegador (JS), em tempo real, enquanto o
// utilizador digita/seleciona - sem precisar clicar em "Filtrar" nem
// recarregar a página. Por isso a query já traz TODOS os membros
// (ativos e inativos) e a filtragem visual acontece client-side.
$sql = "SELECT * FROM membros ORDER BY nome_completo ASC";
$stmt = $conn->query($sql);
$membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$stmt = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN categoria = 'mama' AND ativo = 1 THEN 1 ELSE 0 END) as mamas,
    SUM(CASE WHEN categoria = 'papa' AND ativo = 1 THEN 1 ELSE 0 END) as papas,
    SUM(CASE WHEN categoria = 'jovem' AND ativo = 1 THEN 1 ELSE 0 END) as jovens,
    SUM(CASE WHEN categoria = 'crianca' AND ativo = 1 THEN 1 ELSE 0 END) as criancas
FROM membros");
$stats = $stmt->fetch();
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>
    
    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-users"></i> Membros</h2>
            <div>
                <a href="<?= url('modules/members/add.php') ?>" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-plus"></i> Novo Membro
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid-stats" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-users"></i></div>
                <div class="stat-num"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-label">Total de Membros</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fce4ec;color:#b5527a;"><i class="fas fa-female"></i></div>
                <div class="stat-num"><?= $stats['mamas'] ?? 0 ?></div>
                <div class="stat-label">Mamãs</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-male"></i></div>
                <div class="stat-num"><?= $stats['papas'] ?? 0 ?></div>
                <div class="stat-label">Papás</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#ede7f6;color:#7a5ca8;"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-num"><?= $stats['jovens'] ?? 0 ?></div>
                <div class="stat-label">Jovens</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fdf1d9;color:#c99a2e;"><i class="fas fa-child"></i></div>
                <div class="stat-num"><?= $stats['criancas'] ?? 0 ?></div>
                <div class="stat-label">Crianças</div>
            </div>
        </div>

        <!-- Filtros: tudo em tempo real (client-side), sem precisar clicar em nenhum botão -->
        <div class="panel" style="margin-bottom:20px;">
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                <div style="flex:1;min-width:150px;">
                    <input type="text" id="busca_membro_input" placeholder="Buscar por nome ou telefone..."
                           oninput="filtrarMembrosTabela()"
                           style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                </div>
                <div style="min-width:130px;">
                    <select id="filtro_categoria_membro" onchange="filtrarMembrosTabela()"
                            style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="">Todas Categorias</option>
                        <option value="mama">Mamãs</option>
                        <option value="papa">Papás</option>
                        <option value="jovem">Jovens</option>
                        <option value="crianca">Crianças</option>
                    </select>
                </div>
                <div style="min-width:120px;">
                    <select id="filtro_status_membro" onchange="filtrarMembrosTabela()"
                            style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="ativo" selected>Ativos</option>
                        <option value="inativo">Inativos</option>
                        <option value="todos">Todos</option>
                    </select>
                </div>
                <button type="button" onclick="limparFiltrosMembros()" style="padding:10px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);background:#fff;text-decoration:none;font-weight:600;font-size:13px;transition:.2s;cursor:pointer;">
                    <i class="fas fa-undo"></i> Limpar
                </button>
            </div>
        </div>

        <!-- Tabela de Membros -->
        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 style="font-size:14px;font-weight:700;color:var(--blue-800);">
                    <i class="fas fa-list"></i> Lista de Membros 
                    <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(<?= count($membros) ?> registros)</span>
                </h3>
                <div style="display:flex;gap:8px;">
                    <button onclick="exportarExcel()" class="btn-secondary" style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);background:#fff;color:var(--gray-600);font-weight:600;font-size:12px;cursor:pointer;font-family:'Inter',sans-serif;transition:.2s;">
                        <i class="fas fa-file-excel"></i> Exportar
                    </button>
                </div>
            </div>
            
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-100);">
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">#</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Nome</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Categoria</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Telefone</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Data Cadastro</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Status</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabela_membros">
                        <tr id="linha_nenhum_membro" style="display:none;">
                            <td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);">
                                <i class="fas fa-users" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.3;"></i>
                                Nenhum membro encontrado.
                            </td>
                        </tr>
                        <?php if (!empty($membros)): ?>
                        <?php foreach ($membros as $index => $membro): ?>
                        <tr class="linha-membro"
                            data-nome="<?= htmlspecialchars(mb_strtolower($membro['nome_completo'])) ?>"
                            data-telefone="<?= htmlspecialchars(mb_strtolower($membro['telefone'] ?? '')) ?>"
                            data-categoria="<?= $membro['categoria'] ?>"
                            data-status="<?= $membro['ativo'] ? 'ativo' : 'inativo' ?>"
                            style="border-bottom:1px solid var(--gray-100);transition:background .2s;">
                            <td style="padding:10px 12px;color:var(--gray-500);"><?= $index + 1 ?></td>
                            <td style="padding:10px 12px;font-weight:600;color:var(--gray-800);">
                                <a href="javascript:void(0)" onclick="abrirHistoricoPagamentos(<?= $membro['id'] ?>, '<?= htmlspecialchars($membro['nome_completo'], ENT_QUOTES) ?>')"
                                   style="color:var(--gray-800);text-decoration:none;border-bottom:1px dashed var(--gray-300);cursor:pointer;" title="Ver histórico de pagamentos">
                                    <?= htmlspecialchars($membro['nome_completo']) ?>
                                </a>
                            </td>
                            <td style="padding:10px 12px;">
                                <?php
                                $categoria_labels = [
                                    'mama' => ['label' => 'Mamã', 'color' => '#b5527a'],
                                    'papa' => ['label' => 'Papá', 'color' => '#3f7d4e'],
                                    'jovem' => ['label' => 'Jovem', 'color' => '#7a5ca8'],
                                    'crianca' => ['label' => 'Criança', 'color' => '#c99a2e']
                                ];
                                $cat = $categoria_labels[$membro['categoria']] ?? ['label' => $membro['categoria'], 'color' => '#gray-500'];
                                ?>
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= $cat['color'] ?>;color:#fff;font-size:11px;font-weight:600;">
                                    <?= $cat['label'] ?>
                                </span>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-600);"><?= htmlspecialchars($membro['telefone'] ?? '-') ?></td>
                            <td style="padding:10px 12px;color:var(--gray-500);font-size:12px;">
                                <?= date('d/m/Y', strtotime($membro['criado_em'])) ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <?php if ($membro['ativo']): ?>
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:#eaf3ec;color:#3f7d4e;font-size:11px;font-weight:600;">
                                    <i class="fas fa-circle" style="font-size:6px;margin-right:4px;"></i> Ativo
                                </span>
                                <?php else: ?>
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:#f9e9e5;color:#b5412f;font-size:11px;font-weight:600;">
                                    <i class="fas fa-circle" style="font-size:6px;margin-right:4px;"></i> Inativo
                                </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                                    <a href="<?= url('modules/members/view.php?id=' . $membro['id']) ?>" 
                                       style="padding:6px 10px;border-radius:var(--radius);background:var(--blue-100);color:var(--blue-600);text-decoration:none;font-size:12px;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?= url('modules/members/edit.php?id=' . $membro['id']) ?>" 
                                       style="padding:6px 10px;border-radius:var(--radius);background:#fdeee3;color:#b9770e;text-decoration:none;font-size:12px;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="toggleStatus(<?= $membro['id'] ?>, <?= $membro['ativo'] ? 0 : 1 ?>)" 
                                            style="padding:6px 10px;border-radius:var(--radius);border:none;background:<?= $membro['ativo'] ? '#f9e9e5' : '#eaf3ec' ?>;color:<?= $membro['ativo'] ? '#b5412f' : '#3f7d4e' ?>;cursor:pointer;font-size:12px;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas <?= $membro['ativo'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                        <?= $membro['ativo'] ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                    <?php if ($_SESSION['user_perfil'] == 'admin'): ?>
                                    <button onclick="deleteMember(<?= $membro['id'] ?>)" 
                                            style="padding:6px 10px;border-radius:var(--radius);border:none;background:#f9e9e5;color:#b5412f;cursor:pointer;font-size:12px;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>
<?php require_once '../../includes/modal-historico-pagamentos.php'; ?>

<script>
function toggleStatus(id, novoStatus) {
    const acao = novoStatus ? 'ativar' : 'desativar';
    if (confirm(`Tem certeza que deseja ${acao} este membro?`)) {
        fetch('<?= url('modules/members/toggle-status.php') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id + '&ativo=' + novoStatus
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao alterar status: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro na requisição: ' + error);
        });
    }
}

function deleteMember(id) {
    if (confirm('Tem certeza que deseja excluir este membro permanentemente? Esta ação não pode ser desfeita.')) {
        fetch('<?= url('modules/members/delete.php') ?>', {
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

function exportarExcel() {
    alert('Funcionalidade de exportação em desenvolvimento.');
}

// Filtragem em tempo real: enquanto o utilizador digita ou muda a categoria/
// status, a tabela é filtrada instantaneamente no navegador - sem recarregar
// a página e sem precisar clicar em nenhum botão de "Filtrar".
function filtrarMembrosTabela() {
    const busca = document.getElementById('busca_membro_input').value.trim().toLowerCase();
    const categoria = document.getElementById('filtro_categoria_membro').value;
    const status = document.getElementById('filtro_status_membro').value;

    let algumaVisivel = false;

    document.querySelectorAll('#tabela_membros tr.linha-membro').forEach(function (tr) {
        const nomeOk = !busca || tr.dataset.nome.includes(busca) || tr.dataset.telefone.includes(busca);
        const categoriaOk = !categoria || tr.dataset.categoria === categoria;
        const statusOk = status === 'todos' || tr.dataset.status === status;

        const visivel = nomeOk && categoriaOk && statusOk;
        tr.style.display = visivel ? '' : 'none';
        if (visivel) algumaVisivel = true;
    });

    document.getElementById('linha_nenhum_membro').style.display = algumaVisivel ? 'none' : '';
}

function limparFiltrosMembros() {
    document.getElementById('busca_membro_input').value = '';
    document.getElementById('filtro_categoria_membro').value = '';
    document.getElementById('filtro_status_membro').value = 'ativo';
    filtrarMembrosTabela();
}

// Aplica o filtro padrão (Ativos) assim que a página carrega.
document.addEventListener('DOMContentLoaded', filtrarMembrosTabela);
</script>