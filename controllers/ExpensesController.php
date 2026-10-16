<?php
// controllers/ExpensesController.php
// Controlador do modulo "expenses" - arquitectura MVC.
// Logica original dos antigos modules/expenses/*.php preservada,
// agora separada em Model (dados) / Controller (fluxo) / View (HTML).

require_once BASE_PATH . '/models/ExpensesModel.php';
require_once BASE_PATH . '/models/AuditModel.php';
class ExpensesController extends Controller
{
    protected string $modulo = 'expenses';

    /** Antes: modules/expenses/add.php */
    public function add(): void
    {
    // modules/expenses/add.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('expenses');
    $page_title = 'Nova Saída - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();
    $erro = '';
    $sucesso = '';

    // Buscar categorias existentes para sugestão
    $stmt = $conn->query("SELECT DISTINCT categoria FROM saidas ORDER BY categoria");
    $categorias_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $saldo_cotas = getSaldoFundo('cotas');
    $saldo_contribuicoes = getSaldoFundo('contribuicoes');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0')));
        $data_saida = $_POST['data_saida'] ?? date('Y-m-d');
        $categoria = trim($_POST['categoria'] ?? '');
        $beneficiario = trim($_POST['beneficiario'] ?? '');
        $origem_fundo = ($_POST['origem_fundo'] ?? 'cotas') === 'contribuicoes' ? 'contribuicoes' : 'cotas';

        if (empty($descricao)) {
            $erro = 'A descrição é obrigatória.';
        } elseif ($valor <= 0) {
            $erro = 'O valor deve ser maior que zero.';
        } elseif (empty($data_saida)) {
            $erro = 'A data é obrigatória.';
        } else {
            $saldo_disponivel = getSaldoFundo($origem_fundo);
            if ($valor > $saldo_disponivel) {
                $fundo_label = $origem_fundo === 'contribuicoes' ? 'Contribuições' : 'Cotas';
                $erro = "Saldo insuficiente em \"$fundo_label\". Saldo disponível: " . number_format($saldo_disponivel, 2, ',', '.') . ' Kz.';
            } else {
            try {
                $sql = "INSERT INTO saidas (descricao, valor, data_saida, categoria, origem_fundo, beneficiario, registrado_por) 
                        VALUES (:descricao, :valor, :data_saida, :categoria, :origem_fundo, :beneficiario, :registrado_por)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':descricao' => $descricao,
                    ':valor' => $valor,
                    ':data_saida' => $data_saida,
                    ':categoria' => $categoria ?: null,
                    ':origem_fundo' => $origem_fundo,
                    ':beneficiario' => $beneficiario ?: null,
                    ':registrado_por' => $_SESSION['user_id']
                ]);

                $sucesso = 'Saída registrada com sucesso!';
                registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'criar', 'expenses', "Registou a saída \"$descricao\" no valor de $valor Kz (fundo: $origem_fundo)");
                header('refresh:2;url=' . url('modules/expenses/index.php'));
            } catch (PDOException $e) {
                $erro = 'Erro ao registrar saída: ' . $e->getMessage();
            }
            }
        }
    }
    }

    /** Antes: modules/expenses/delete.php */
    public function delete(): void
    {
    // modules/expenses/delete.php

    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }


    if (!podeAcessarModulo('expenses')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sem permissão para este módulo']);
        exit;
    }
    $conn = db();

    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    try {
        // Verificar permissão (apenas admin ou editor)
        if (!in_array($_SESSION['user_perfil'], ['admin', 'editor'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Permissão negada']);
            exit;
        }

        $stmt = $conn->prepare("SELECT descricao FROM saidas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $descricao_saida = $stmt->fetchColumn();

        $stmt = $conn->prepare("DELETE FROM saidas WHERE id = :id");
        $stmt->execute([':id' => $id]);

        registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'excluir', 'expenses', "Excluiu a saída \"$descricao_saida\" (ID $id)");

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    ?>
    }

    /** Antes: modules/expenses/edit.php */
    public function edit(): void
    {
    // modules/expenses/edit.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('expenses');
    $page_title = 'Editar Saída - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        header('Location: ' . url('modules/expenses/index.php'));
        exit;
    }

    // Buscar saída
    $stmt = $conn->prepare("SELECT * FROM saidas WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $saida = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$saida) {
        header('Location: ' . url('modules/expenses/index.php'));
        exit;
    }

    // Buscar categorias existentes
    $stmt = $conn->query("SELECT DISTINCT categoria FROM saidas ORDER BY categoria");
    $categorias_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Saldo disponível de cada fundo, sem contar a própria saída que está a ser editada
    $saldo_cotas = getSaldoFundo('cotas', $id);
    $saldo_contribuicoes = getSaldoFundo('contribuicoes', $id);

    $erro = '';
    $sucesso = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0')));
        $data_saida = $_POST['data_saida'] ?? date('Y-m-d');
        $categoria = trim($_POST['categoria'] ?? '');
        $beneficiario = trim($_POST['beneficiario'] ?? '');
        $origem_fundo = ($_POST['origem_fundo'] ?? 'cotas') === 'contribuicoes' ? 'contribuicoes' : 'cotas';

        if (empty($descricao)) {
            $erro = 'A descrição é obrigatória.';
        } elseif ($valor <= 0) {
            $erro = 'O valor deve ser maior que zero.';
        } elseif (empty($data_saida)) {
            $erro = 'A data é obrigatória.';
        } else {
            $saldo_disponivel = getSaldoFundo($origem_fundo, $id);
            if ($valor > $saldo_disponivel) {
                $fundo_label = $origem_fundo === 'contribuicoes' ? 'Contribuições' : 'Cotas';
                $erro = "Saldo insuficiente em \"$fundo_label\". Saldo disponível: " . number_format($saldo_disponivel, 2, ',', '.') . ' Kz.';
            } else {
            try {
                $sql = "UPDATE saidas SET 
                            descricao = :descricao,
                            valor = :valor,
                            data_saida = :data_saida,
                            categoria = :categoria,
                            origem_fundo = :origem_fundo,
                            beneficiario = :beneficiario
                        WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':descricao' => $descricao,
                    ':valor' => $valor,
                    ':data_saida' => $data_saida,
                    ':categoria' => $categoria ?: null,
                    ':origem_fundo' => $origem_fundo,
                    ':beneficiario' => $beneficiario ?: null,
                    ':id' => $id
                ]);

                $sucesso = 'Saída atualizada com sucesso!';
                registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'editar', 'expenses', "Editou a saída \"$descricao\" (ID $id)", $saida);
                header('refresh:2;url=' . url('modules/expenses/index.php'));
            } catch (PDOException $e) {
                $erro = 'Erro ao atualizar saída: ' . $e->getMessage();
            }
            }
        }
    }
    }

    /** Antes: modules/expenses/index.php */
    public function index(): void
    {
    // modules/expenses/index.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('expenses');
    $page_title = 'Saídas/Despesas - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    $saldo_cotas = getSaldoFundo('cotas');
    $saldo_contribuicoes = getSaldoFundo('contribuicoes');

    // ============================================
    // FILTROS
    // ============================================
    $ano = validarAnoCotas(isset($_GET['ano']) ? $_GET['ano'] : date('Y'));
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : 0;
    $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
    // Nunca somar os dois fundos às cegas num único total - por isso não
    // existe opção "Todos"; só os dois fundos reais (mesmo critério dos
    // relatórios financeiros).
    $fundo = isset($_GET['fundo']) ? trim($_GET['fundo']) : 'cotas';
    if (!in_array($fundo, ['cotas', 'contribuicoes'])) {
        $fundo = 'cotas';
    }

    // ============================================
    // BUSCAR CATEGORIAS PARA FILTRO
    // ============================================
    $stmt_cat = $conn->prepare("SELECT DISTINCT categoria FROM saidas WHERE origem_fundo = :fundo ORDER BY categoria");
    $stmt_cat->execute([':fundo' => $fundo]);
    $categorias_list = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);

    // ============================================
    // BUSCAR SAÍDAS
    // ============================================
    $where = "YEAR(data_saida) = :ano";
    $params = [':ano' => $ano];

    if ($mes > 0) {
        $where .= " AND MONTH(data_saida) = :mes";
        $params[':mes'] = $mes;
    }

    if (!empty($categoria)) {
        $where .= " AND categoria = :categoria";
        $params[':categoria'] = $categoria;
    }

    $where .= " AND origem_fundo = :fundo";
    $params[':fundo'] = $fundo;

    $sql = "SELECT 
                s.*,
                u.nome_completo as registrado_nome
            FROM saidas s
            LEFT JOIN usuarios u ON s.registrado_por = u.id
            WHERE $where
            ORDER BY s.data_saida DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $saidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_saidas = 0;
    foreach ($saidas as $s) {
        $total_saidas += $s['valor'];
    }
    }

}
