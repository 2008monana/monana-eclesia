<?php
// modules/auth/login.php
require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../config/session.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /ipfva-gestao/' . getUrlPosLogin());
    exit;
}

$error = '';
$login_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../config/database.php';
    require_once '../../modules/audit/functions.php';
    
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $lembrar = isset($_POST['lembrar']);
    
    if (empty($email) || empty($senha)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();
            
            if (!$usuario) {
                $error = 'Credenciais inválidas. Utilizador não encontrado.';
            } elseif (!$usuario['ativo']) {
                $error = 'Utilizador desativado. Contacte o administrador.';
            } elseif (!password_verify($senha, $usuario['senha_hash'])) {
                $error = 'Credenciais inválidas. Senha incorreta.';
            } else {
                // Login bem-sucedido
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['user_nome'] = $usuario['nome_completo'];
                $_SESSION['user_email'] = $usuario['email'];
                $_SESSION['user_perfil'] = $usuario['perfil'];
                $_SESSION['user_modulos'] = $usuario['modulos_permitidos'] ?? '';
                
                // Atualizar último login
                $stmt = $conn->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id");
                $stmt->execute([':id' => $usuario['id']]);
                
                // Registrar log de login
                registrarLog(
                    $usuario['id'],
                    $usuario['nome_completo'],
                    'login',
                    'auth',
                    "Login realizado com sucesso"
                );
                
                // Cookie "Lembrar-me"
                if ($lembrar) {
                    setcookie('user_email', $email, time() + (86400 * 30), '/');
                }
                
                $login_success = true;
                
                // Retornar resposta JSON para o AJAX
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Login bem-sucedido! Redirecionando...',
                    'redirect' => url(getUrlPosLogin())
                ]);
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Erro ao conectar ao banco de dados: ' . $e->getMessage();
        }
    }
    
    // Se chegou aqui, houve erro
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $error
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c99a2e">
    <title>Entrar — MonanaEclésia</title>
    <link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.png') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= url('assets/img/favicon.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= url('assets/img/favicon.png') ?>">
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

        <!-- Formulário -->
        <main class="auth-panel">
            <div class="auth-card">
                <img class="mark-sm" src="<?= url('assets/img/logo.png') ?>" alt="MonanaEclésia">
                <h1>Bem-vindo de volta!</h1>
                <p class="lead">Entre na sua conta para continuar.</p>

                <form id="loginForm" method="POST" action="">
                    <div class="field-group">
                        <label class="field-label" for="loginEmail">Email</label>
                        <div class="field">
                            <span class="ico"><i class="far fa-envelope"></i></span>
                            <input type="email" name="email" id="loginEmail" placeholder="Digite o seu email" autocomplete="username"
                                   value="<?= htmlspecialchars($_COOKIE['user_email'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="senha">Senha</label>
                        <div class="field">
                            <span class="ico"><i class="fas fa-lock"></i></span>
                            <input id="senha" type="password" name="senha" placeholder="Digite a sua senha" autocomplete="current-password" required>
                            <button type="button" class="toggle-eye" onclick="toggleSenha()" aria-label="Mostrar senha">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="auth-row">
                        <label class="check">
                            <input type="checkbox" name="lembrar" <?= isset($_COOKIE['user_email']) ? 'checked' : '' ?>>
                            <span>Lembrar-me</span>
                        </label>
                        <a href="<?= url('modules/auth/forgot-password.php') ?>">Esqueceu a senha?</a>
                    </div>
                    <button class="btn-primary" type="submit" id="loginBtn">Entrar</button>
                </form>
            </div>
        </main>
    </div>

    <!-- ===== MODAL DE PROCESSAMENTO ===== -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-box">
            <span class="modal-icon" id="modalIcon"><i class="fas fa-spinner"></i></span>
            <div class="modal-title" id="modalTitle">A processar...</div>
            <div class="modal-message" id="modalMessage">Aguarde enquanto validamos as suas credenciais.</div>
            <div class="modal-progress"><div class="bar" id="modalProgressBar"></div></div>
        </div>
    </div>

    <script>
        function toggleSenha() {
            const input = document.getElementById('senha');
            const icon = document.querySelector('.toggle-eye i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // ============================================
        // LOGIN COM MODAL DE PROCESSAMENTO
        // ============================================
        const loginForm = document.getElementById('loginForm');
        const modalOverlay = document.getElementById('modalOverlay');
        const modalIcon = document.getElementById('modalIcon');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalProgressBar = document.getElementById('modalProgressBar');
        const loginBtn = document.getElementById('loginBtn');

        function showModal(icon, title, message, progressWidth, progressColor = '') {
            modalIcon.innerHTML = icon;
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            modalProgressBar.style.width = progressWidth;
            modalProgressBar.className = 'bar' + (progressColor ? ' ' + progressColor : '');
            modalOverlay.classList.add('active');
        }

        function hideModal() {
            modalOverlay.classList.remove('active');
            // Resetar progresso
            setTimeout(() => {
                modalProgressBar.style.width = '0%';
                modalProgressBar.className = 'bar';
            }, 300);
        }

        // Impedir envio tradicional do formulário
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('loginEmail').value;
            const senha = document.getElementById('senha').value;
            
            if (!email || !senha) {
                alert('Por favor, preencha todos os campos.');
                return;
            }

            // Desabilitar botão
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A processar...';

            // ==========================================
            // INICIAR ANIMAÇÃO DO MODAL
            // ==========================================
            let progresso = 0;
            const totalTempo = 5000; // 5 segundos
            const intervalo = 50; // atualizar a cada 50ms
            const incremento = (intervalo / totalTempo) * 100;

            // Mostrar modal com progresso inicial
            showModal(
                '<i class="fas fa-spinner"></i>',
                'A processar...',
                'Aguarde enquanto validamos as suas credenciais.',
                '0%'
            );

            // Atualizar progresso
            const progressInterval = setInterval(() => {
                progresso += incremento;
                if (progresso >= 100) {
                    progresso = 100;
                    clearInterval(progressInterval);
                }
                modalProgressBar.style.width = progresso + '%';
            }, intervalo);

            // Enviar requisição AJAX
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    email: email,
                    senha: senha,
                    lembrar: document.querySelector('input[name="lembrar"]').checked ? 'on' : ''
                })
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(progressInterval);
                
                if (data.success) {
                    // LOGIN BEM-SUCEDIDO
                    modalProgressBar.style.width = '100%';
                    showModal(
                        '<i class="fas fa-check-circle"></i>',
                        'Bem-vindo!',
                        data.message || 'Login realizado com sucesso!',
                        '100%',
                        'success'
                    );
                    
                    // Redirecionar após 1.5 segundos
                    setTimeout(() => {
                        window.location.href = data.redirect || '/ipfva-gestao/modules/dashboard/index.php';
                    }, 1500);
                    
                } else {
                    // LOGIN FALHOU
                    let icon = '<i class="fas fa-times-circle"></i>';
                    let title = 'Erro de Login';
                    let progressColor = 'error';
                    
                    // Verificar tipo de erro
                    if (data.message.includes('desativado')) {
                        icon = '<i class="fas fa-exclamation-triangle"></i>';
                        title = 'Utilizador Desativado';
                        progressColor = 'warning';
                    }
                    
                    modalProgressBar.style.width = '100%';
                    showModal(
                        icon,
                        title,
                        data.message || 'Credenciais inválidas. Tente novamente.',
                        '100%',
                        progressColor
                    );
                    
                    // Reativar botão após 2 segundos
                    setTimeout(() => {
                        hideModal();
                        loginBtn.disabled = false;
                        loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> INICIAR SESSÃO';
                    }, 2000);
                }
            })
            .catch(error => {
                clearInterval(progressInterval);
                console.error('Erro:', error);
                
                showModal(
                    '<i class="fas fa-times-circle"></i>',
                    ' Erro de Conexão',
                    'Ocorreu um erro ao processar o login. Tente novamente.',
                    '100%',
                    'error'
                );
                
                setTimeout(() => {
                    hideModal();
                    loginBtn.disabled = false;
                    loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> INICIAR SESSÃO';
                }, 2000);
            });
        });
    </script>
</body>
</html>