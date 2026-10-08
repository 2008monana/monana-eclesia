<?php
// modules/users/edit.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /ipfva-gestao/modules/auth/login.php');
    exit;
}

if ($_SESSION['user_perfil'] != 'admin') {
    header('Location: /ipfva-gestao/modules/dashboard/index.php');
    exit;
}

$page_title = 'Editar Utilizador - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../config/session.php';
require_once '../../modules/audit/functions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$conn = getConnection();

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: ' . url('modules/users/index.php'));
    exit;
}

// Buscar utilizador
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: ' . url('modules/users/index.php'));
    exit;
}

$erro = '';
$sucesso = '';
$modulos_disponiveis = getModulosAtribuiveis();
$modulos_atuais = array_filter(array_map('trim', explode(',', (string) ($usuario['modulos_permitidos'] ?? ''))));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $perfil = ($_POST['perfil'] ?? 'gestor') === 'admin' ? 'admin' : 'gestor';
    $modulos_selecionados = array_intersect($_POST['modulos'] ?? [], array_keys($modulos_disponiveis));
    $modulos_permitidos = $perfil === 'admin' ? null : implode(',', $modulos_selecionados);
    
    if (empty($nome)) {
        $erro = 'O nome é obrigatório.';
    } elseif (empty($email)) {
        $erro = 'O email é obrigatório.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Email inválido.';
    } else {
        try {
            // Verificar se email já existe (para outro usuário)
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
            $stmt->execute([':email' => $email, ':id' => $id]);
            if ($stmt->fetch()) {
                $erro = 'Este email já está em uso por outro utilizador.';
            } else {
                $sql = "UPDATE usuarios SET nome_completo = :nome, email = :email, perfil = :perfil, modulos_permitidos = :modulos_permitidos WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':nome' => $nome,
                    ':email' => $email,
                    ':perfil' => $perfil,
                    ':modulos_permitidos' => $modulos_permitidos,
                    ':id' => $id
                ]);
                
                $sucesso = 'Utilizador atualizado com sucesso!';
                $usuario_log = $usuario;
                unset($usuario_log['senha_hash']);
                registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'editar', 'users', "Editou o utilizador \"$nome\" (ID $id)", $usuario_log);
                header('refresh:2;url=' . url('modules/users/index.php'));

                // Refletir de imediato no formulário caso a página não recarregue ainda
                $usuario['perfil'] = $perfil;
                $modulos_atuais = $modulos_selecionados;
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao atualizar utilizador: ' . $e->getMessage();
        }
    }
}
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-user-edit"></i> Editar Utilizador</h2>
            <a href="<?= url('modules/users/index.php') ?>" style="display:inline-flex;align-items:center;gap:6px;color:var(--gray-500);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;">
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

        <div class="panel" style="max-width:600px;margin:0 auto;">
            <form method="POST" action="">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Nome Completo <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="text" name="nome" required value="<?= htmlspecialchars($usuario['nome_completo']) ?>"
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Email <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($usuario['email']) ?>"
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Perfil <span style="color:var(--danger);">*</span>
                        </label>
                        <select name="perfil" id="perfil" required onchange="toggleModulos()"
                                style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;">
                            <option value="gestor" <?= $usuario['perfil'] == 'gestor' ? 'selected' : '' ?>>Gestor</option>
                            <option value="admin" <?= $usuario['perfil'] == 'admin' ? 'selected' : '' ?>>Administrador</option>
                        </select>
                        <p style="font-size:11.5px;color:var(--gray-400);margin-top:4px;">O Administrador tem acesso total ao sistema. O Gestor só acede aos módulos que escolher abaixo - o resto fica automaticamente reservado à sua página de Perfil.</p>
                    </div>
                    <div id="bloco_modulos" style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;">
                            Módulos com acesso
                        </label>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(180px,1fr));gap:10px;padding:14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);">
                            <?php foreach ($modulos_disponiveis as $chave => $label): ?>
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-700);cursor:pointer;">
                                <input type="checkbox" name="modulos[]" value="<?= $chave ?>" <?= in_array($chave, $modulos_atuais) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div style="grid-column:1/3;">
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:var(--radius);">
                            <div style="font-size:12px;color:var(--gray-500);">Status</div>
                            <div style="font-weight:600;color:var(--gray-800);">
                                <?= $usuario['ativo'] ? '✅ Ativo' : '❌ Inativo' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary" style="padding:12px 30px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-save"></i> Atualizar
                    </button>
                    <a href="<?= url('modules/users/index.php') ?>" style="padding:12px 24px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:14px;transition:.2s;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>

<script>
function toggleModulos() {
    const perfil = document.getElementById('perfil').value;
    const bloco = document.getElementById('bloco_modulos');
    bloco.style.display = perfil === 'admin' ? 'none' : 'block';
}
toggleModulos();
</script>