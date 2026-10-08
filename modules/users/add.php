<?php
// modules/users/add.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /ipfva-gestao/modules/auth/login.php');
    exit;
}

if ($_SESSION['user_perfil'] != 'admin') {
    header('Location: /ipfva-gestao/modules/dashboard/index.php');
    exit;
}

$page_title = 'Novo Utilizador - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../config/session.php';
require_once '../../modules/audit/functions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$conn = getConnection();
$erro = '';
$sucesso = '';
$modulos_disponiveis = getModulosAtribuiveis();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $perfil = ($_POST['perfil'] ?? 'gestor') === 'admin' ? 'admin' : 'gestor';
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    // Só interessa gravar módulos para gestores - admin já tem tudo.
    $modulos_selecionados = array_intersect($_POST['modulos'] ?? [], array_keys($modulos_disponiveis));
    $modulos_permitidos = $perfil === 'admin' ? null : implode(',', $modulos_selecionados);
    
    if (empty($nome)) {
        $erro = 'O nome é obrigatório.';
    } elseif (empty($email)) {
        $erro = 'O email é obrigatório.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Email inválido.';
    } elseif (empty($senha)) {
        $erro = 'A senha é obrigatória.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmar_senha) {
        $erro = 'As senhas não coincidem.';
    } else {
        try {
            // Verificar se email já existe
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $erro = 'Este email já está em uso.';
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $sql = "INSERT INTO usuarios (nome_completo, email, senha_hash, perfil, modulos_permitidos) 
                        VALUES (:nome, :email, :hash, :perfil, :modulos_permitidos)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':nome' => $nome,
                    ':email' => $email,
                    ':hash' => $hash,
                    ':perfil' => $perfil,
                    ':modulos_permitidos' => $modulos_permitidos
                ]);
                
                $sucesso = 'Utilizador criado com sucesso!';
                registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'criar', 'users', "Criou o utilizador \"$nome\" ($email, perfil: $perfil)");
                header('refresh:2;url=' . url('modules/users/index.php'));
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao criar utilizador: ' . $e->getMessage();
        }
    }
}
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-user-plus"></i> Novo Utilizador</h2>
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
                        <input type="text" name="nome" required
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Email <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="email" name="email" required
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Senha <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="password" name="senha" required minlength="6"
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Confirmar Senha <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="password" name="confirmar_senha" required
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Perfil <span style="color:var(--danger);">*</span>
                        </label>
                        <select name="perfil" id="perfil" required onchange="toggleModulos()" style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;">
                            <option value="gestor">Gestor</option>
                            <option value="admin">Administrador</option>
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
                                <input type="checkbox" name="modulos[]" value="<?= $chave ?>">
                                <?= htmlspecialchars($label) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary" style="padding:12px 30px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-save"></i> Criar
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