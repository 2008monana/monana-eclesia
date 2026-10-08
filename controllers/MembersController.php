<?php
// controllers/MembersController.php
// Controlador do modulo "members" - arquitectura MVC.
// Logica original dos antigos modules/members/*.php preservada,
// agora separada em Model (dados) / Controller (fluxo) / View (HTML).

require_once BASE_PATH . '/models/MembersModel.php';
require_once BASE_PATH . '/models/AuditModel.php';
class MembersController extends Controller
{
    protected string $modulo = 'members';

    /** Antes: modules/members/add.php */
    public function add(): void
    {
    // modules/members/add.php

    if (!isset($_SESSION['user_id'])) {
        redirect('modules/auth/login.php');
        exit;
    }


    requireModuleAccess('members');
    $page_title = 'Adicionar Membro - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();
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
                $sql = "INSERT INTO membros (nome_completo, categoria, telefone, endereco, data_nascimento) 
                        VALUES (:nome, :categoria, :telefone, :endereco, :data_nascimento)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':nome' => $nome,
                    ':categoria' => $categoria,
                    ':telefone' => $telefone,
                    ':endereco' => $endereco,
                    ':data_nascimento' => $data_nascimento ?: null
                ]);

                $sucesso = 'Membro adicionado com sucesso!';
                registrarLog(
                    $_SESSION['user_id'],
                    $_SESSION['user_nome'],
                    'criar',
                    'members',
                    "Criou o membro \"$nome\" (categoria: $categoria)"
                );
                header('refresh:2;url=' . url('modules/members/index.php'));
            } catch (PDOException $e) {
                $erro = 'Erro ao adicionar membro: ' . $e->getMessage();
            }
        }
    }
    }

    /** Antes: modules/members/delete.php */
    public function delete(): void
    {
    // modules/members/delete.php

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

    try {
        $stmt = $conn->prepare("SELECT nome_completo FROM membros WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $nome_membro = $stmt->fetchColumn();

        // Verificar se existem registros de cotas
        $stmt = $conn->prepare("SELECT COUNT(*) FROM cotas_diarias WHERE membro_id = :id");
        $stmt->execute([':id' => $id]);
        $temCotas = $stmt->fetchColumn() > 0;

        if ($temCotas) {
            // Se tem cotas, apenas desativar
            $stmt = $conn->prepare("UPDATE membros SET ativo = 0 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $mensagem = 'Membro desativado pois possui registros de cotas.';
            registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'desativar', 'members', "Desativou o membro \"$nome_membro\" (ID $id) por ter cotas associadas");
        } else {
            // Se não tem cotas, excluir permanentemente
            $stmt = $conn->prepare("DELETE FROM membros WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $mensagem = 'Membro excluído permanentemente.';
            registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'excluir', 'members', "Excluiu o membro \"$nome_membro\" (ID $id)");
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $mensagem]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    ?>
    }

    /** Antes: modules/members/edit.php */
    public function edit(): void
    {
    // modules/members/edit.php

    if (!isset($_SESSION['user_id'])) {
        redirect('modules/auth/login.php');
        exit;
    }


    requireModuleAccess('members');
    $page_title = 'Editar Membro - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

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
    }

    /** Antes: modules/members/historico-pagamentos.php */
    public function historicoPagamentos(): void
    {
    // modules/members/historico-pagamentos.php
    // Devolve, em JSON, o histórico completo de pagamentos (cotas diárias e
    // contribuição de fim de ano) de um membro - usado pelo card/modal de
    // histórico nas listas de Membros, Mamãs, Papás e Jovens.

    header('Content-Type: application/json; charset=utf-8');


    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
        exit;
    }

    // Acessível a quem tenha acesso a Membros OU a Listas (é usado nas duas áreas).
    if (!podeAcessarModulo('members') && !podeAcessarModulo('lists')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissão para consultar este histórico.']);
        exit;
    }

    require_once '../lists/functions.php';

    $membro_id = intval($_GET['membro_id'] ?? 0);

    if ($membro_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Membro inválido.']);
        exit;
    }

    $conn = db();

    $stmt = $conn->prepare("SELECT id, nome_completo, categoria FROM membros WHERE id = :id");
    $stmt->execute([':id' => $membro_id]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$membro) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Membro não encontrado.']);
        exit;
    }

    // ------------------------------------------------------------
    // Cotas diárias (pagamentos por domingo/dia, com mês de referência)
    // ------------------------------------------------------------
    $stmt = $conn->prepare("
        SELECT id, data_pagamento, mes_referencia, valor, observacao
        FROM cotas_diarias
        WHERE membro_id = :membro_id
        ORDER BY data_pagamento DESC, id DESC
    ");
    $stmt->execute([':membro_id' => $membro_id]);
    $cotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_cotas = 0;
    $anos_disponiveis = [];
    $pagamentos = [];

    // Total pago especificamente em cada mês de referência (não acumulado),
    // usado para calcular corretamente a dívida em falta de cada mês.
    $total_por_mes_referencia = [];

    foreach ($cotas as $c) {
        $valor = (float) $c['valor'];
        $total_cotas += $valor;

        $chave_mes_ref = date('Y-n', strtotime($c['mes_referencia']));
        $total_por_mes_referencia[$chave_mes_ref] = ($total_por_mes_referencia[$chave_mes_ref] ?? 0) + $valor;

        $ano_pagamento = (int) date('Y', strtotime($c['data_pagamento']));
        $anos_disponiveis[$ano_pagamento] = true;

        $mes_ref_ts = strtotime($c['mes_referencia']);

        $pagamentos[] = [
            'id'                    => (int) $c['id'],
            'data_pagamento'        => $c['data_pagamento'],
            'data_pagamento_fmt'    => date('d/m/Y', strtotime($c['data_pagamento'])),
            'ano_pagamento'         => $ano_pagamento,
            'mes_referencia'        => $c['mes_referencia'],
            'mes_referencia_fmt'    => nomeMesPT((int) date('n', $mes_ref_ts)) . ' de ' . date('Y', $mes_ref_ts),
            'mes_referencia_ano'    => (int) date('Y', $mes_ref_ts),
            'mes_referencia_mes'    => (int) date('n', $mes_ref_ts),
            'valor'                 => $valor,
            'valor_fmt'             => number_format($valor, 2, ',', '.'),
            'observacao'            => $c['observacao'] ?: '',
        ];
    }

    krsort($anos_disponiveis);
    $anos_disponiveis = array_keys($anos_disponiveis);

    // ------------------------------------------------------------
    // Meses pagos / meses em dívida (com base no mes_referencia real,
    // não no mês do calendário em que o dinheiro entrou) - mesma regra
    // de verificarStatusMes() usada nas Listas, mês a mês.
    //
    // Considera-se sempre o ano corrente (para mostrar a dívida em
    // aberto até hoje) mais qualquer outro ano em que haja pagamentos,
    // desde que não seja anterior ao início das cotas (as dívidas
    // reiniciam a cada ano).
    // ------------------------------------------------------------
    $ano_atual_status = (int) date('Y');
    $mes_atual_status = (int) date('n');

    $anos_para_status = $anos_disponiveis;
    if (!in_array($ano_atual_status, $anos_para_status)) {
        $anos_para_status[] = $ano_atual_status;
    }
    $anos_para_status = array_values(array_unique(array_filter(
        $anos_para_status,
        function ($a) { return $a >= ANO_INICIO_COTAS; }
    )));
    rsort($anos_para_status);

    $meses_status = [];
    foreach ($anos_para_status as $ano_s) {
        $mes_limite = ($ano_s === $ano_atual_status) ? $mes_atual_status : 12;
        $meses_ano = [];
        for ($m = 1; $m <= $mes_limite; $m++) {
            $status = verificarStatusMes($membro_id, $m, $ano_s);
            // Valor pago especificamente NESTE mês de referência (não o
            // acumulado desde Janeiro que verificarStatusMes() devolve).
            $total_mes = $total_por_mes_referencia["$ano_s-$m"] ?? 0;
            $meses_ano[] = [
                'mes'       => $m,
                'ano'       => $ano_s,
                'mes_nome'  => nomeMesPT($m),
                'quitado'   => $status['quitado'],
                'total'     => $total_mes,
                'total_fmt' => number_format($total_mes, 2, ',', '.'),
                'falta'     => max(0, 1000 - $total_mes),
            ];
        }
        $meses_status[] = [
            'ano'           => $ano_s,
            'meses'         => $meses_ano,
            'qtd_pagos'     => count(array_filter($meses_ano, function ($x) { return $x['quitado']; })),
            'qtd_em_divida' => count(array_filter($meses_ano, function ($x) { return !$x['quitado']; })),
        ];
    }

    // ------------------------------------------------------------
    // Contribuição de fim de ano (informação complementar)
    // ------------------------------------------------------------
    $stmt = $conn->prepare("
        SELECT id, ano, valor, data_pagamento
        FROM contribuicao_fim_ano
        WHERE membro_id = :membro_id
        ORDER BY ano DESC
    ");
    $stmt->execute([':membro_id' => $membro_id]);
    $contribuicoes_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_contribuicoes = 0;
    $contribuicoes = [];
    foreach ($contribuicoes_raw as $c) {
        $valor = (float) $c['valor'];
        $total_contribuicoes += $valor;
        $contribuicoes[] = [
            'ano'                => (int) $c['ano'],
            'valor'              => $valor,
            'valor_fmt'          => number_format($valor, 2, ',', '.'),
            'data_pagamento_fmt' => date('d/m/Y', strtotime($c['data_pagamento'])),
        ];
    }

    echo json_encode([
        'success' => true,
        'membro' => [
            'id'        => (int) $membro['id'],
            'nome'      => $membro['nome_completo'],
            'categoria' => $membro['categoria'],
        ],
        'pagamentos'         => $pagamentos,
        'contribuicoes'      => $contribuicoes,
        'anos_disponiveis'   => $anos_disponiveis,
        'meses_status'       => $meses_status,
        'totais' => [
            'total_cotas'          => $total_cotas,
            'total_cotas_fmt'      => number_format($total_cotas, 2, ',', '.'),
            'quantidade_cotas'     => count($pagamentos),
            'total_contribuicoes'     => $total_contribuicoes,
            'total_contribuicoes_fmt' => number_format($total_contribuicoes, 2, ',', '.'),
            'total_geral'          => $total_cotas + $total_contribuicoes,
            'total_geral_fmt'      => number_format($total_cotas + $total_contribuicoes, 2, ',', '.'),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
    ?>
    }

    /** Antes: modules/members/index.php */
    public function index(): void
    {
    // modules/members/index.php

    if (!isset($_SESSION['user_id'])) {
        redirect('modules/auth/login.php');
        exit;
    }


    requireModuleAccess('members');
    $page_title = 'Membros - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    // Filtros: a pesquisa por nome/telefone, categoria e status agora são
    // aplicados inteiramente no navegador (JS), em tempo real, enquanto o
    // utilizador digita/seleciona - sem precisar clicar em "Filtrar" nem
    // recarregar a página. Por isso a query já traz TODOS os membros
    // (ativos e inativos) e a filtragem visual acontece client-side.
    $sql = "SELECT * FROM membros ORDER BY nome_completo ASC";
    $stmt = $conn->query($sql);
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estatísticas
    $stmt = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN categoria = 'mama' AND ativo = 1 THEN 1 ELSE 0 END) as mamas,
        SUM(CASE WHEN categoria = 'papa' AND ativo = 1 THEN 1 ELSE 0 END) as papas,
        SUM(CASE WHEN categoria = 'jovem' AND ativo = 1 THEN 1 ELSE 0 END) as jovens,
        SUM(CASE WHEN categoria = 'crianca' AND ativo = 1 THEN 1 ELSE 0 END) as criancas
    FROM membros");
    $stats = $stmt->fetch();
    }

    /** Antes: modules/members/toggle-status.php */
    public function toggleStatus(): void
    {
    // modules/members/toggle-status.php

    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }


    if (!podeAcessarModulo('members')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sem permissão para este módulo']);
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

    try {
        $stmt = $conn->prepare("UPDATE membros SET ativo = :ativo WHERE id = :id");
        $stmt->execute([':ativo' => $ativo, ':id' => $id]);

        $stmt = $conn->prepare("SELECT nome_completo FROM membros WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $nome_membro = $stmt->fetchColumn();
        registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], $ativo ? 'ativar' : 'desativar', 'members', ($ativo ? "Ativou" : "Desativou") . " o membro \"$nome_membro\" (ID $id)");

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    ?>
    }

    /** Antes: modules/members/view.php */
    public function view(): void
    {
    // modules/members/view.php

    if (!isset($_SESSION['user_id'])) {
        redirect('modules/auth/login.php');
        exit;
    }


    requireModuleAccess('members');
    $page_title = 'Visualizar Membro - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        redirect('modules/members/index.php');
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM membros WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$membro) {
        redirect('modules/members/index.php');
        exit;
    }

    $categoria_labels = [
        'mama' => ['label' => 'Mamã', 'color' => '#b5527a', 'icon' => 'fa-female'],
        'papa' => ['label' => 'Papá', 'color' => '#3f7d4e', 'icon' => 'fa-male'],
        'jovem' => ['label' => 'Jovem', 'color' => '#7a5ca8', 'icon' => 'fa-user-graduate'],
        'crianca' => ['label' => 'Criança', 'color' => '#c99a2e', 'icon' => 'fa-child']
    ];
    $cat = $categoria_labels[$membro['categoria']] ?? ['label' => $membro['categoria'], 'color' => '#gray-500', 'icon' => 'fa-user'];
    }

}
