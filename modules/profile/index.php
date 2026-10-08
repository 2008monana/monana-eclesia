<?php
// modules/profile/index.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /ipfva-gestao/modules/auth/login.php');
    exit;
}

$page_title = 'Meu Perfil - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../modules/audit/functions.php';

$conn = getConnection();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Buscar dados do usuário
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header('Location: /ipfva-gestao/modules/dashboard/index.php');
    exit;
}

// Atualizar perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    if (empty($nome) || empty($email)) {
        $error = 'Nome e email são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    } else {
        try {
            // Verificar se email já existe (para outro usuário)
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
            $stmt->execute([':email' => $email, ':id' => $user_id]);
            if ($stmt->fetch()) {
                $error = 'Este email já está em uso por outro usuário.';
            } else {
                // Atualizar dados básicos
                $sql = "UPDATE usuarios SET nome_completo = :nome, email = :email WHERE id = :id";
                $params = [':nome' => $nome, ':email' => $email, ':id' => $user_id];
                
                // Se quer mudar a senha
                if (!empty($nova_senha) || !empty($senha_atual)) {
                    if (empty($senha_atual)) {
                        $error = 'Digite a senha atual para alterar a senha.';
                    } elseif (!password_verify($senha_atual, $usuario['senha_hash'])) {
                        $error = 'Senha atual incorreta.';
                    } elseif (strlen($nova_senha) < 6) {
                        $error = 'A nova senha deve ter pelo menos 6 caracteres.';
                    } elseif ($nova_senha !== $confirmar_senha) {
                        $error = 'As senhas não coincidem.';
                    } else {
                        $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                        $sql = "UPDATE usuarios SET nome_completo = :nome, email = :email, senha_hash = :hash WHERE id = :id";
                        $params[':hash'] = $hash;
                    }
                }
                
                if (empty($error)) {
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                    
                    // Registrar log de alteração de perfil
                    if (!empty($nova_senha)) {
                        registrarLog(
                            $_SESSION['user_id'],
                            $_SESSION['user_nome'],
                            'editar',
                            'profile',
                            "Perfil atualizado e senha alterada"
                        );
                    } else {
                        registrarLog(
                            $_SESSION['user_id'],
                            $_SESSION['user_nome'],
                            'editar',
                            'profile',
                            "Perfil atualizado (nome e/ou email)"
                        );
                    }
                    
                    // Atualizar sessão
                    $_SESSION['user_nome'] = $nome;
                    $_SESSION['user_email'] = $email;
                    
                    $success = 'Perfil atualizado com sucesso!';
                    
                    // Recarregar dados do usuário
                    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = :id");
                    $stmt->execute([':id' => $user_id]);
                    $usuario = $stmt->fetch();
                }
            }
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar perfil: ' . $e->getMessage();
            registrarLog(
                $_SESSION['user_id'],
                $_SESSION['user_nome'],
                'editar',
                'profile',
                "Erro ao atualizar perfil: " . $e->getMessage()
            );
        }
    }
}
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-user-cog"></i> Meu Perfil</h2>
            <?php if (podeAcessarModulo('dashboard')): ?>
            <a href="<?= url('modules/dashboard/index.php') ?>" style="display:inline-flex;align-items:center;gap:6px;color:var(--gray-500);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;">
                <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
            </a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['sem_acesso'])): ?>
        <div style="background:#fff3cd;border:1px solid #ffe69c;color:#7a5b00;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:13.5px;">
            <i class="fas fa-info-circle"></i> Não tem acesso a essa página. Esta é a sua página de Perfil - fale com o administrador se precisar de acesso a outro módulo.
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div style="background:#f9e9e5;border:1px solid #f5c6c6;color:#721c24;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px;">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div style="background:#eaf3ec;border:1px solid #a5d6a7;color:#2e7d32;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px;">
            <i class="fas fa-check-circle"></i> <?= $success ?>
        </div>
        <?php endif; ?>

        <div class="panel" style="max-width:600px;margin:0 auto;">
            <!-- Cabeçalho do Perfil -->
            <div style="text-align:center;margin-bottom:24px;">
                <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--blue-500),var(--blue-700));display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:32px;color:#fff;font-weight:700;font-family:'Inter',sans-serif;">
                    <?= strtoupper(substr($usuario['nome_completo'], 0, 2)) ?>
                </div>
                <h2 style="font-size:20px;font-weight:700;color:var(--blue-800);"><?= htmlspecialchars($usuario['nome_completo']) ?></h2>
                <span style="display:inline-block;padding:2px 16px;border-radius:20px;background:<?= $usuario['perfil'] == 'admin' ? '#f9e9e5' : ($usuario['perfil'] == 'editor' ? '#fdf1d9' : '#eaf3ec') ?>;color:<?= $usuario['perfil'] == 'admin' ? '#b5412f' : ($usuario['perfil'] == 'editor' ? '#b9770e' : '#3f7d4e') ?>;font-size:12px;font-weight:600;">
                    <?= ucfirst($usuario['perfil']) ?>
                </span>
            </div>

            <form method="POST" action="">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Nome Completo <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome_completo']) ?>" required
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Email <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                </div>

                <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);">
                    <div style="font-weight:700;color:var(--gray-700);margin-bottom:12px;font-size:14px;">
                        <i class="fas fa-lock"></i> Alterar Palavra-passe
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div style="grid-column:1/3;">
                            <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                                Senha Atual
                            </label>
                            <input type="password" name="senha_atual" placeholder="Digite a senha atual para alterar"
                                   style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                                Nova Senha
                            </label>
                            <input type="password" name="nova_senha" placeholder="Mínimo 6 caracteres" minlength="6"
                                   style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                                Confirmar Nova Senha
                            </label>
                            <input type="password" name="confirmar_senha" placeholder="Confirme a nova senha"
                                   style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:24px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary" style="padding:12px 30px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-save"></i> SALVAR ALTERAÇÕES
                    </button>
                    <a href="<?= url('modules/dashboard/index.php') ?>" style="padding:12px 24px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:14px;transition:.2s;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>

            <!-- Informações Adicionais -->
            <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div style="background:var(--gray-50);padding:12px 16px;border-radius:var(--radius);">
                    <div style="font-size:11px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">ID do Utilizador</div>
                    <div style="font-size:14px;font-weight:600;color:var(--gray-800);">#<?= str_pad($usuario['id'], 4, '0', STR_PAD_LEFT) ?></div>
                </div>
                <div style="background:var(--gray-50);padding:12px 16px;border-radius:var(--radius);">
                    <div style="font-size:11px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Data de Cadastro</div>
                    <div style="font-size:14px;font-weight:600;color:var(--gray-800);">
                        <?= date('d/m/Y H:i', strtotime($usuario['criado_em'])) ?>
                    </div>
                </div>
                <div style="grid-column:1/3;background:var(--gray-50);padding:12px 16px;border-radius:var(--radius);">
                    <div style="font-size:11px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Último Login</div>
                    <div style="font-size:14px;font-weight:600;color:var(--gray-800);">
                        <?= $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca' ?>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>