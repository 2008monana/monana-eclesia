<?php
// modules/auth/reset-password.php
require_once '../../config/session.php';
require_once '../../config/url.php';

if (isLoggedIn()) {
    redirect('dashboard');
}

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$usuario = null;

if (empty($token)) {
    $error = 'Token inválido ou expirado.';
} else {
    try {
        require_once '../../config/database.php';
        $conn = getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE token_recuperacao = :token AND token_expiracao > NOW()");
        $stmt->execute([':token' => $token]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            $error = 'Token inválido ou expirado. Solicite um novo link de recuperação.';
        }
    } catch (PDOException $e) {
        $error = 'Erro ao conectar ao banco de dados.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    if (strlen($nova_senha) < 6) {
        $error = 'A palavra-passe deve ter pelo menos 6 caracteres.';
    } elseif ($nova_senha !== $confirmar_senha) {
        $error = 'As palavras-passe não coincidem.';
    } else {
        try {
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE usuarios SET senha_hash = :hash, token_recuperacao = NULL, token_expiracao = NULL WHERE id = :id");
            $stmt->execute([':hash' => $hash, ':id' => $usuario['id']]);

            require_once '../../modules/audit/functions.php';
            registrarLog($usuario['id'], $usuario['nome_completo'], 'resetar_senha', 'auth', 'Redefiniu a própria senha via link de recuperação');
            
            $success = 'Palavra-passe alterada com sucesso! <a href="login.php">Clique aqui para fazer login</a>.';
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar a senha.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c99a2e">
    <title>Redefinir senha — MonanaEclésia</title>
    <link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= url('assets/img/favicon-32.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= url('assets/img/favicon-180.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style><?php readfile(dirname(__DIR__, 2) . '/assets/css/monana-theme.css'); ?></style>
</head>
<body>
    <div class="auth">
        <!-- Marca -->
        <aside class="auth-brand">
            <img class="logo" src="<?= url('assets/img/logo.png') ?>" alt="MonanaEclésia — Plataforma Integrada de Gestão de Igrejas">
            <p class="tagline">Organizar para servir melhor.</p>
            <div class="legal">
                &copy; 2026 Igreja Pentecostal Fonte da Vida Angola<br>
                Calemba 2 &middot; Todos os direitos reservados.
            </div>
        </aside>

        <main class="auth-panel">
            <div class="auth-card">
                <img class="mark-sm" src="<?= url('assets/img/logo.png') ?>" alt="MonanaEclésia">
                <h1>Redefinir senha</h1>
                <p class="lead">Escolha uma nova senha para aceder à sua conta.</p>

                <?php if ($error): ?>
                <div class="alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error) ?></span></div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="success-box"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
                <?php elseif ($usuario): ?>
                <form method="POST" action="">
                    <div class="field-group">
                        <label class="field-label" for="nova_senha">Nova senha</label>
                        <div class="field">
                            <span class="ico"><i class="fas fa-lock"></i></span>
                            <input type="password" name="nova_senha" id="nova_senha" placeholder="Mínimo de 6 caracteres" required minlength="6">
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="confirmar_senha">Confirmar senha</label>
                        <div class="field">
                            <span class="ico"><i class="fas fa-lock"></i></span>
                            <input type="password" name="confirmar_senha" id="confirmar_senha" placeholder="Repita a nova senha" required>
                        </div>
                    </div>
                    <button class="btn-primary" type="submit">Alterar senha</button>
                </form>
                <?php endif; ?>

                <div class="auth-aux">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Voltar ao login</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
