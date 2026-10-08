<?php
// controllers/AuthController.php
// Controlador do modulo "auth" - arquitectura MVC.
// Logica original dos antigos modules/auth/*.php preservada,
// agora separada em Model (dados) / Controller (fluxo) / View (HTML).

require_once BASE_PATH . '/models/AuthModel.php';
require_once BASE_PATH . '/models/AuditModel.php';
class AuthController extends Controller
{
    protected string $modulo = 'auth';

    /** Antes: modules/auth/forgot-password.php */
    public function forgotPassword(): void
    {
    // modules/auth/forgot-password.php

    if (isLoggedIn()) {
        redirect('dashboard');
    }

    $success = '';
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Por favor, insira o seu email.';
        } else {
            try {
                $conn = db();
                $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = :email");
                $stmt->execute([':email' => $email]);
                $usuario = $stmt->fetch();

                if ($usuario) {
                    $token = bin2hex(random_bytes(32));
                    $expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $stmt = $conn->prepare("UPDATE usuarios SET token_recuperacao = :token, token_expiracao = :expiracao WHERE id = :id");
                    $stmt->execute([
                        ':token' => $token,
                        ':expiracao' => $expiracao,
                        ':id' => $usuario['id']
                    ]);

                    $link = url('resetar-senha') . '?token=' . $token;

                    $success = "Um link de recuperação foi gerado para <strong>" . htmlspecialchars($email) . "</strong>.<br>
                                <small>Clique no link para redefinir sua senha: <br>
                                <a href='" . $link . "' target='_blank'>" . $link . "</a></small>";
                } else {
                    $error = 'Email não encontrado no sistema.';
                }
            } catch (PDOException $e) {
                $error = 'Erro ao conectar ao banco de dados.';
            }
        }
    }
    }

    /** Antes: modules/auth/login.php */
    public function login(): void
    {
    // modules/auth/login.php

    if (isset($_SESSION['user_id'])) {
        redirect(getUrlPosLogin());
        exit;
    }

    $error = '';
    $login_success = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $lembrar = isset($_POST['lembrar']);

        if (empty($email) || empty($senha)) {
            $error = 'Por favor, preencha todos os campos.';
        } else {
            try {
                $conn = db();
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
    }

    /** Antes: modules/auth/logout.php */
    public function logout(): void
    {
    // modules/auth/logout.php


    // Verificar se há uma requisição AJAX
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if (isset($_SESSION['user_id'])) {
        $conn = db();
        registrarLog(
            $_SESSION['user_id'],
            $_SESSION['user_nome'],
            'logout',
            'auth',
            "Logout realizado"
        );
    }

    session_destroy();

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'redirect' => url('modules/auth/login.php')]);
        exit;
    }

    redirect('modules/auth/login.php');
    }

    /** Antes: modules/auth/reset-password.php */
    public function resetPassword(): void
    {
    // modules/auth/reset-password.php

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
            $conn = db();

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

                registrarLog($usuario['id'], $usuario['nome_completo'], 'resetar_senha', 'auth', 'Redefiniu a própria senha via link de recuperação');

                $success = 'Palavra-passe alterada com sucesso! <a href="login.php">Clique aqui para fazer login</a>.';
            } catch (PDOException $e) {
                $error = 'Erro ao atualizar a senha.';
            }
        }
    }
    }

}
