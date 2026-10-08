<?php
// controllers/UsersController.php
// Controlador do modulo "users" - arquitectura MVC.
// Logica original dos antigos modules/users/*.php preservada,
// agora separada em Model (dados) / Controller (fluxo) / View (HTML).

require_once BASE_PATH . '/models/UsersModel.php';
require_once BASE_PATH . '/models/AuditModel.php';
class UsersController extends Controller
{
    protected string $modulo = 'users';

    /** Antes: modules/users/add.php */
    public function add(): void
    {
    // modules/users/add.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }

    if ($_SESSION['user_perfil'] != 'admin') {
        redirect('dashboard');
        exit;
    }

    $page_title = 'Novo Utilizador - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();
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
    }

    /** Antes: modules/users/delete.php */
    public function delete(): void
    {
    // modules/users/delete.php

    if (!isset($_SESSION['user_id']) || $_SESSION['user_perfil'] != 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permissão negada']);
        exit;
    }


    $conn = db();

    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    // Não permitir eliminar o próprio admin
    if ($id == $_SESSION['user_id']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Não pode eliminar o próprio utilizador']);
        exit;
    }

    try {
        // Buscar dados do utilizador para o log
        $stmt = $conn->prepare("SELECT nome_completo, email, perfil FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Utilizador não encontrado']);
            exit;
        }

        // Registrar log antes de eliminar
        registrarLog(
            $_SESSION['user_id'],
            $_SESSION['user_nome'],
            'excluir',
            'users',
            "Utilizador '{$usuario['nome_completo']}' (Email: {$usuario['email']}, Perfil: {$usuario['perfil']}) foi eliminado permanentemente",
            $usuario,
            null
        );

        // Eliminar o utilizador
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    ?>
    }

    /** Antes: modules/users/edit.php */
    public function edit(): void
    {
    // modules/users/edit.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }

    if ($_SESSION['user_perfil'] != 'admin') {
        redirect('dashboard');
        exit;
    }

    $page_title = 'Editar Utilizador - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

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
    }

    /** Antes: modules/users/functions.php */
    public function functions(): void
    {
    // modules/users/functions.php

    function getPerfilLabel($perfil) {
        $labels = [
            'admin' => ['label' => 'Administrador', 'color' => '#b5412f', 'icon' => 'fa-crown'],
            'gestor' => ['label' => 'Gestor', 'color' => '#b9770e', 'icon' => 'fa-user-cog']
        ];
        return $labels[$perfil] ?? ['label' => ucfirst($perfil), 'color' => '#gray-500', 'icon' => 'fa-user'];
    }

    function getStatusBadge($ativo) {
        if ($ativo) {
            return '<span style="display:inline-block;padding:2px 12px;border-radius:20px;background:#eaf3ec;color:#3f7d4e;font-size:11px;font-weight:600;">
                        <i class="fas fa-circle" style="font-size:6px;margin-right:4px;"></i> Ativo
                    </span>';
        } else {
            return '<span style="display:inline-block;padding:2px 12px;border-radius:20px;background:#f9e9e5;color:#b5412f;font-size:11px;font-weight:600;">
                        <i class="fas fa-circle" style="font-size:6px;margin-right:4px;"></i> Inativo
                    </span>';
        }
    }
    ?>
    }

    /** Antes: modules/users/index.php */
    public function index(): void
    {
    // modules/users/index.php

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

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

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
    }

    /** Antes: modules/users/reset-password.php */
    public function resetPassword(): void
    {
    // modules/users/reset-password.php

    if (!isset($_SESSION['user_id']) || $_SESSION['user_perfil'] != 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permissão negada']);
        exit;
    }

    $conn = db();

    $id = intval($_POST['id'] ?? 0);
    $senha = $_POST['senha'] ?? '';

    if ($id <= 0 || empty($senha) || strlen($senha) < 6) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Senha inválida (mínimo 6 caracteres)']);
        exit;
    }

    try {
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET senha_hash = :hash WHERE id = :id");
        $stmt->execute([':hash' => $hash, ':id' => $id]);

        $stmt = $conn->prepare("SELECT nome_completo FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $nome_usuario = $stmt->fetchColumn();
        registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'resetar_senha', 'users', "Redefiniu a senha do utilizador \"$nome_usuario\" (ID $id)");

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    ?>
    }

    /** Antes: modules/users/toggle-status.php */
    public function toggleStatus(): void
    {
    // modules/users/toggle-status.php

    if (!isset($_SESSION['user_id']) || $_SESSION['user_perfil'] != 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permissão negada']);
        exit;
    }

    $conn = db();

    $id = intval($_POST['id'] ?? 0);
    $ativo = intval($_POST['ativo'] ?? 0);

    if ($id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    // Não permitir desativar o próprio admin
    if ($id == $_SESSION['user_id']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Não pode alterar o próprio status']);
        exit;
    }

    try {
        $stmt = $conn->prepare("UPDATE usuarios SET ativo = :ativo WHERE id = :id");
        $stmt->execute([':ativo' => $ativo, ':id' => $id]);

        $stmt = $conn->prepare("SELECT nome_completo FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $nome_usuario = $stmt->fetchColumn();
        registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], $ativo ? 'ativar' : 'desativar', 'users', ($ativo ? "Ativou" : "Desativou") . " o utilizador \"$nome_usuario\" (ID $id)");

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    ?>
    }

}
