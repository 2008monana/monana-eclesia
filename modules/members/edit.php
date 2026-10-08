<?php
// modules/members/edit.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('modules/auth/login.php');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('members');
$page_title = 'Editar Membro - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../modules/audit/functions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$conn = getConnection();

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    redirect('modules/members/index.php');
    exit;
}

// Buscar dados do membro
$stmt = $conn->prepare("SELECT * FROM membros WHERE id = :id");
$stmt->execute([':id' => $id]);
$membro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$membro) {
    redirect('modules/members/index.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $categoria = $_POST['categoria'] ?? '';
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    
    if (empty($nome) || empty($categoria)) {
        $erro = 'Nome e categoria são obrigatórios.';
    } else {
        try {
            $sql = "UPDATE membros SET 
                        nome_completo = :nome, 
                        categoria = :categoria, 
                        telefone = :telefone, 
                        endereco = :endereco, 
                        data_nascimento = :data_nascimento 
                    WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':nome' => $nome,
                ':categoria' => $categoria,
                ':telefone' => $telefone,
                ':endereco' => $endereco,
                ':data_nascimento' => $data_nascimento ?: null,
                ':id' => $id
            ]);
            
            $sucesso = 'Membro atualizado com sucesso!';
            registrarLog(
                $_SESSION['user_id'],
                $_SESSION['user_nome'],
                'editar',
                'members',
                "Editou o membro \"$nome\" (ID $id)",
                $membro
            );
            header('refresh:2;url=' . url('modules/members/index.php'));
        } catch (PDOException $e) {
            $erro = 'Erro ao atualizar membro: ' . $e->getMessage();
        }
    }
}
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>
    
    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-user-edit"></i> Editar Membro</h2>
            <a href="<?= url('modules/members/index.php') ?>" style="display:inline-flex;align-items:center;gap:6px;color:var(--gray-500);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <?php if ($erro): ?>
        <div style="background:#f9e9e5;border:1px solid #f5c6c6;color:#721c24;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px;">
            <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
        </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
        <div style="background:#eaf3ec;border:1px solid #a5d6a7;color:#2e7d32;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px;">
            <i class="fas fa-check-circle"></i> <?= $sucesso ?>
        </div>
        <?php endif; ?>

        <div class="panel" style="max-width:700px;margin:0 auto;">
            <form method="POST" action="">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Nome Completo <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="text" name="nome" required value="<?= htmlspecialchars($membro['nome_completo']) ?>"
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Categoria <span style="color:var(--danger);">*</span>
                        </label>
                        <select name="categoria" required
                                style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;">
                            <option value="">Selecione...</option>
                            <option value="mama" <?= $membro['categoria'] == 'mama' ? 'selected' : '' ?>>Mamã</option>
                            <option value="papa" <?= $membro['categoria'] == 'papa' ? 'selected' : '' ?>>Papá</option>
                            <option value="jovem" <?= $membro['categoria'] == 'jovem' ? 'selected' : '' ?>>Jovem</option>
                            <option value="crianca" <?= $membro['categoria'] == 'crianca' ? 'selected' : '' ?>>Criança</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Telefone
                        </label>
                        <input type="tel" name="telefone" value="<?= htmlspecialchars($membro['telefone'] ?? '') ?>"
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Data de Nascimento
                        </label>
                        <input type="date" name="data_nascimento" value="<?= $membro['data_nascimento'] ?? '' ?>"
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Endereço
                        </label>
                        <textarea name="endereco" rows="2"
                                  style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;resize:vertical;"><?= htmlspecialchars($membro['endereco'] ?? '') ?></textarea>
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary" style="padding:12px 30px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-save"></i> Atualizar
                    </button>
                    <a href="<?= url('modules/members/index.php') ?>" style="padding:12px 24px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:14px;transition:.2s;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>