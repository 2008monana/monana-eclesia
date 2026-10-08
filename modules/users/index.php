<?php
// modules/users/index.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}

// Apenas admin pode acessar
if ($_SESSION['user_perfil'] != 'admin') {
    redirect('dashboard');
    exit;
}

$page_title = 'Utilizadores - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once 'functions.php'; // IMPORTANTE: incluir as funções

$conn = getConnection();

// ============================================
// FILTROS
// ============================================
$perfil_filtro = isset($_GET['perfil']) ? $_GET['perfil'] : '';
$status_filtro = isset($_GET['status']) ? $_GET['status'] : '';

// ============================================
// BUSCAR UTILIZADORES
// ============================================
$where = "1=1";
$params = [];

if (!empty($perfil_filtro)) {
    $where .= " AND perfil = :perfil";
    $params[':perfil'] = $perfil_filtro;
}

if ($status_filtro === 'ativo') {
    $where .= " AND ativo = 1";
} elseif ($status_filtro === 'inativo') {
    $where .= " AND ativo = 0";
}

$sql = "SELECT * FROM usuarios WHERE $where ORDER BY criado_em DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// ESTATÍSTICAS
// ============================================
$total_usuarios = count($usuarios);
$total_admin = 0;
$total_gestor = 0;
$total_ativos = 0;

foreach ($usuarios as $u) {
    if ($u['ativo']) $total_ativos++;
    if ($u['perfil'] == 'admin') $total_admin++;
    elseif ($u['perfil'] == 'gestor') $total_gestor++;
}
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-users-cog"></i> Utilizadores</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="<?= url('modules/users/add.php') ?>" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-user-plus"></i> Novo Utilizador
                </a>
            </div>
        </div>

        <!-- ==========================================
        RESUMO (CARDS)
        ========================================== -->
        <div class="grid-stats" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-users"></i></div>
                <div class="stat-num"><?= $total_usuarios ?></div>
                <div class="stat-label">Total de Utilizadores</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-user-check"></i></div>
                <div class="stat-num"><?= $total_ativos ?></div>
                <div class="stat-label">Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f9e9e5;color:#b5412f;"><i class="fas fa-user-slash"></i></div>
                <div class="stat-num"><?= $total_usuarios - $total_ativos ?></div>
                <div class="stat-label">Inativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fdeee3;color:#b9770e;"><i class="fas fa-user-shield"></i></div>
                <div class="stat-num"><?= $total_admin ?></div>
                <div class="stat-label">Administradores</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fcf8ec;color:#b08020;"><i class="fas fa-user-cog"></i></div>
                <div class="stat-num"><?= $total_gestor ?></div>
                <div class="stat-label">Gestores</div>
            </div>
        </div>

        <!-- ==========================================
        FILTRO
        ========================================== -->
        <div class="panel" style="margin-bottom:20px;">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Perfil</label>
                    <select name="perfil" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="">Todos</option>
                        <option value="admin" <?= $perfil_filtro == 'admin' ? 'selected' : '' ?>>Administrador</option>
                        <option value="gestor" <?= $perfil_filtro == 'gestor' ? 'selected' : '' ?>>Gestor</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Status</label>
                    <select name="status" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="">Todos</option>
                        <option value="ativo" <?= $status_filtro == 'ativo' ? 'selected' : '' ?>>Ativos</option>
                        <option value="inativo" <?= $status_filtro == 'inativo' ? 'selected' : '' ?>>Inativos</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-primary" style="padding:8px 20px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
                <div>
                    <a href="<?= url('modules/users/index.php') ?>" style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;display:inline-block;">
                        <i class="fas fa-undo"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- ==========================================
        TABELA
        ========================================== -->
        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 style="font-size:14.5px;font-weight:700;color:var(--blue-800);">
                    <i class="fas fa-list"></i> Lista de Utilizadores
                    <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(<?= $total_usuarios ?> registros)</span>
                </h3>
            </div>

            <?php if (empty($usuarios)): ?>
            <div style="padding:40px;text-align:center;color:var(--gray-400);">
                <i class="fas fa-users" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.3;"></i>
                Nenhum utilizador encontrado.
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-100);">
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">#</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Nome</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Email</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Perfil</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Status</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Último Login</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $index => $u): 
                            $perfil_info = getPerfilLabel($u['perfil']);
                            $status_badge = getStatusBadge($u['ativo']);
                        ?>
                        <tr style="border-bottom:1px solid var(--gray-100);">
                            <td style="padding:10px 12px;color:var(--gray-500);"><?= $index + 1 ?></td>
                            <td style="padding:10px 12px;font-weight:600;color:var(--gray-800);">
                                <?= htmlspecialchars($u['nome_completo']) ?>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-600);">
                                <?= htmlspecialchars($u['email']) ?>
                            </td>
                            <td style="padding:10px 12px;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= $perfil_info['color'] ?>20;color:<?= $perfil_info['color'] ?>;font-size:11px;font-weight:600;">
                                    <i class="fas <?= $perfil_info['icon'] ?>"></i> <?= $perfil_info['label'] ?>
                                </span>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <?= $status_badge ?>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-500);font-size:12px;">
                                <?= $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : 'Nunca' ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                                    <a href="<?= url('modules/users/edit.php?id=' . $u['id']) ?>" 
                                       style="padding:6px 10px;border-radius:var(--radius);background:#fdeee3;color:#b9770e;text-decoration:none;font-size:12px;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="toggleStatus(<?= $u['id'] ?>, <?= $u['ativo'] ? 0 : 1 ?>)" 
                                            style="padding:6px 10px;border-radius:var(--radius);border:none;background:<?= $u['ativo'] ? '#f9e9e5' : '#eaf3ec' ?>;color:<?= $u['ativo'] ? '#b5412f' : '#3f7d4e' ?>;cursor:pointer;font-size:12px;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas <?= $u['ativo'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                        <?= $u['ativo'] ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                    <button onclick="resetPassword(<?= $u['id'] ?>)" 
                                            style="padding:6px 10px;border-radius:var(--radius);border:none;background:var(--blue-100);color:var(--blue-600);cursor:pointer;font-size:12px;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <button onclick="deleteUser(<?= $u['id'] ?>)" 
                                            style="padding:6px 10px;border-radius:var(--radius);border:none;background:#f9e9e5;color:#b5412f;cursor:pointer;font-size:12px;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>

<script>
function toggleStatus(id, novoStatus) {
    const acao = novoStatus ? 'ativar' : 'desativar';
    if (confirm(`Tem certeza que deseja ${acao} este utilizador?`)) {
        fetch('<?= url('modules/users/toggle-status.php') ?>', {
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
                alert('Erro: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro na requisição: ' + error);
        });
    }
}

function resetPassword(id) {
    const novaSenha = prompt('Digite a nova senha para este utilizador (mínimo 6 caracteres):');
    if (novaSenha && novaSenha.length >= 6) {
        fetch('<?= url('modules/users/reset-password.php') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id + '&senha=' + encodeURIComponent(novaSenha)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Senha alterada com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro na requisição: ' + error);
        });
    } else if (novaSenha !== null) {
        alert('A senha deve ter pelo menos 6 caracteres.');
    }
}

function deleteUser(id) {
    if (confirm('Tem certeza que deseja ELIMINAR este utilizador permanentemente? Esta ação não pode ser desfeita.')) {
        fetch('<?= url('modules/users/delete.php') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Utilizador eliminado com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro na requisição: ' + error);
        });
    }
}
</script>