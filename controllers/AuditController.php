<?php
// controllers/AuditController.php
// Controlador do modulo "audit" - arquitectura MVC.
// Logica original dos antigos modules/audit/*.php preservada,
// agora separada em Model (dados) / Controller (fluxo) / View (HTML).

require_once BASE_PATH . '/models/AuditModel.php';
require_once BASE_PATH . '/models/AuditModel.php';
use Dompdf\Dompdf;
use Dompdf\Options;

class AuditController extends Controller
{
    protected string $modulo = 'audit';

    /** Antes: modules/audit/functions.php */
    public function functions(): void
    {
    // modules/audit/functions.php

    function registrarLog($usuario_id, $usuario_nome, $acao, $modulo, $descricao, $dados_anteriores = null, $dados_novos = null) {
        global $conn;

        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $stmt = $conn->prepare("
                INSERT INTO logs_auditoria 
                (usuario_id, usuario_nome, acao, modulo, descricao, dados_anteriores, dados_novos, ip, user_agent) 
                VALUES 
                (:usuario_id, :usuario_nome, :acao, :modulo, :descricao, :dados_anteriores, :dados_novos, :ip, :user_agent)
            ");

            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':usuario_nome' => $usuario_nome,
                ':acao' => $acao,
                ':modulo' => $modulo,
                ':descricao' => $descricao,
                ':dados_anteriores' => $dados_anteriores ? json_encode($dados_anteriores) : null,
                ':dados_novos' => $dados_novos ? json_encode($dados_novos) : null,
                ':ip' => $ip,
                ':user_agent' => $user_agent
            ]);

            return true;
        } catch (PDOException $e) {
            // Não interromper o sistema se o log falhar
            error_log("Erro ao registrar log: " . $e->getMessage());
            return false;
        }
    }

    function getAcaoLabel($acao) {
        $labels = [
            'login' => ['label' => 'Login', 'color' => '#3f7d4e', 'icon' => 'fa-sign-in-alt'],
            'logout' => ['label' => 'Logout', 'color' => '#6b7280', 'icon' => 'fa-sign-out-alt'],
            'criar' => ['label' => 'Criar', 'color' => '#8a6317', 'icon' => 'fa-plus'],
            'editar' => ['label' => 'Editar', 'color' => '#b9770e', 'icon' => 'fa-edit'],
            'excluir' => ['label' => 'Excluir', 'color' => '#b5412f', 'icon' => 'fa-trash'],
            'ativar' => ['label' => 'Ativar', 'color' => '#3f7d4e', 'icon' => 'fa-user-check'],
            'desativar' => ['label' => 'Desativar', 'color' => '#b5412f', 'icon' => 'fa-user-slash'],
            'remover' => ['label' => 'Remover', 'color' => '#b5412f', 'icon' => 'fa-undo'],
            'registrar' => ['label' => 'Registrar', 'color' => '#8a6317', 'icon' => 'fa-save'],
            'aprovar' => ['label' => 'Aprovar', 'color' => '#3f7d4e', 'icon' => 'fa-check'],
            'cancelar' => ['label' => 'Cancelar', 'color' => '#b5412f', 'icon' => 'fa-times'],
            'visualizar' => ['label' => 'Visualizar', 'color' => '#7a5ca8', 'icon' => 'fa-eye'],
            'gerar_pdf' => ['label' => 'Gerar PDF', 'color' => '#b5412f', 'icon' => 'fa-file-pdf'],
            'exportar' => ['label' => 'Exportar', 'color' => '#16a34a', 'icon' => 'fa-file-excel'],
            'resetar_senha' => ['label' => 'Resetar Senha', 'color' => '#b9770e', 'icon' => 'fa-key'],
            'marcar_pago' => ['label' => 'Marcar Pago', 'color' => '#3f7d4e', 'icon' => 'fa-check-circle'],
            'desmarcar' => ['label' => 'Desmarcar', 'color' => '#b5412f', 'icon' => 'fa-undo-alt'],
        ];
        return $labels[$acao] ?? ['label' => ucfirst($acao), 'color' => '#6b7280', 'icon' => 'fa-circle'];
    }

    function getModuloLabel($modulo) {
        $labels = [
            'auth' => 'Autenticação',
            'dashboard' => 'Dashboard',
            'members' => 'Membros',
            'registry' => 'Registo Diário',
            'lists' => 'Listas',
            'year-end' => 'Fim de Ano',
            'reports' => 'Relatórios',
            'expenses' => 'Saídas/Despesas',
            'users' => 'Utilizadores',
            'audit' => 'Auditoria',
            'backups' => 'Backups',
            'profile' => 'Perfil'
        ];
        return $labels[$modulo] ?? ucfirst($modulo);
    }
    ?>
    }

    /** Antes: modules/audit/generate-pdf.php */
    public function generatePdf(): void
    {
    require_once __DIR__ . '/../../includes/pdf-theme.php';
    // modules/audit/generate-pdf.php

    if (!isset($_SESSION['user_id']) || $_SESSION['user_perfil'] != 'admin') {
        redirect('login');
        exit;
    }


    }


    use Dompdf\Dompdf;
    use Dompdf\Options;

    $conn = db();

    // ============================================
    // PARÂMETROS DE FILTRO
    // ============================================
    $usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;
    $modulo = isset($_GET['modulo']) ? $_GET['modulo'] : '';
    $acao = isset($_GET['acao']) ? $_GET['acao'] : '';
    $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
    $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

    // ============================================
    // CONSTRUIR QUERY
    // ============================================
    $where = "1=1";
    $params = [];

    if ($usuario_id > 0) {
        $where .= " AND usuario_id = :usuario_id";
        $params[':usuario_id'] = $usuario_id;
    }

    if (!empty($modulo)) {
        $where .= " AND modulo = :modulo";
        $params[':modulo'] = $modulo;
    }

    if (!empty($acao)) {
        $where .= " AND acao = :acao";
        $params[':acao'] = $acao;
    }

    if (!empty($data_inicio)) {
        $where .= " AND DATE(criado_em) >= :data_inicio";
        $params[':data_inicio'] = $data_inicio;
    }

    if (!empty($data_fim)) {
        $where .= " AND DATE(criado_em) <= :data_fim";
        $params[':data_fim'] = $data_fim;
    }

    $sql = "SELECT * FROM logs_auditoria WHERE $where ORDER BY criado_em DESC LIMIT 500";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // HTML PARA PDF
    // ============================================
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: "DejaVu Sans", sans-serif;
                font-size: 9px;
                padding: 15px;
            }
            .header {
                text-align: center;
                border-bottom: 2px solid #3a271a;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }
            .header h1 {
                color: #3a271a;
                margin: 0;
                font-size: 16px;
            }
            .header h3 {
                color: #c99a2e;
                margin: 3px 0;
                font-size: 12px;
            }
            .header p {
                color: #7d6a5b;
                font-size: 10px;
                margin: 2px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 8px;
            }
            th {
                background: #3a271a;
                color: white;
                padding: 4px 5px;
                text-align: left;
                font-weight: bold;
            }
            td {
                padding: 3px 5px;
                border: 1px solid #ece4d2;
            }
            .text-center { text-align: center; }
            .footer {
                text-align: center;
                margin-top: 20px;
                font-size: 8px;
                color: #7d6a5b;
                border-top: 1px solid #ece4d2;
                padding-top: 8px;
            }
            .badge {
                display: inline-block;
                padding: 1px 8px;
                border-radius: 12px;
                font-size: 7px;
                font-weight: bold;
            }
        ' . pdfBrandCss() . '
        </style>
    </head>
    <body>

    <div class="header">
        ' . pdfCabecalho() . '
        <p class="doc-title">RELATÓRIO DE AUDITORIA</p>
        <p>Data de emissão: ' . date('d/m/Y H:i') . '</p>
    </div>';

    if (empty($logs)) {
        $html .= '<p style="text-align:center;padding:30px;color:#a39485;">Nenhuma atividade registada para os filtros selecionados.</p>';
    } else {
        $html .= '
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Data/Hora</th>
                    <th>Utilizador</th>
                    <th>Ação</th>
                    <th>Módulo</th>
                    <th>Descrição</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($logs as $index => $log) {
            $acao_info = getAcaoLabel($log['acao']);
            $html .= '
            <tr>
                <td>' . ($index + 1) . '</td>
                <td>' . date('d/m/Y H:i:s', strtotime($log['criado_em'])) . '</td>
                <td>' . htmlspecialchars($log['usuario_nome']) . '</td>
                <td><span class="badge" style="background:' . $acao_info['color'] . '20;color:' . $acao_info['color'] . ';">' . $acao_info['label'] . '</span></td>
                <td>' . getModuloLabel($log['modulo']) . '</td>
                <td>' . htmlspecialchars($log['descricao']) . '</td>
                <td>' . htmlspecialchars($log['ip']) . '</td>
            </tr>';
        }

        $html .= '
            </tbody>
        </table>

        <div style="margin-top:10px;font-size:8px;color:#7d6a5b;">
            <p><strong>Resumo:</strong> Total de registos: ' . count($logs) . '</p>
        </div>';
    }

    $html .= '
        <div class="footer">
            <p>MonanaEclésia &middot; Plataforma Integrada de Gestão de Igrejas &mdash; I.P.F.V.A Calemba 2 &nbsp;|&nbsp; João 14:6</p>
        </div>
    </body>
    </html>';

    // ============================================
    // GERAR PDF
    // ============================================
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $nome_arquivo = 'auditoria_' . date('Y-m-d') . '.pdf';
    $dompdf->stream($nome_arquivo, array("Attachment" => false));
    exit;
    ?>
    }

    /** Antes: modules/audit/index.php */
    public function index(): void
    {
    // modules/audit/index.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }

    // Apenas admin pode acessar
    if ($_SESSION['user_perfil'] != 'admin') {
        redirect('dashboard');
        exit;
    }

    $page_title = 'Auditoria - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    // ============================================
    // PARÂMETROS DE FILTRO
    // ============================================
    $usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;
    $modulo = isset($_GET['modulo']) ? $_GET['modulo'] : '';
    $acao = isset($_GET['acao']) ? $_GET['acao'] : '';
    $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
    $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

    // ============================================
    // CONSTRUIR QUERY COM FILTROS
    // ============================================
    $where = "1=1";
    $params = [];

    if ($usuario_id > 0) {
        $where .= " AND l.usuario_id = :usuario_id";
        $params[':usuario_id'] = $usuario_id;
    }

    if (!empty($modulo)) {
        $where .= " AND l.modulo = :modulo";
        $params[':modulo'] = $modulo;
    }

    if (!empty($acao)) {
        $where .= " AND l.acao = :acao";
        $params[':acao'] = $acao;
    }

    if (!empty($data_inicio)) {
        $where .= " AND DATE(l.criado_em) >= :data_inicio";
        $params[':data_inicio'] = $data_inicio;
    }

    if (!empty($data_fim)) {
        $where .= " AND DATE(l.criado_em) <= :data_fim";
        $params[':data_fim'] = $data_fim;
    }

    // ============================================
    // BUSCAR LOGS
    // ============================================
    $sql = "SELECT l.* FROM logs_auditoria l WHERE $where ORDER BY l.criado_em DESC LIMIT 500";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // BUSCAR UTILIZADORES PARA FILTRO
    // ============================================
    $stmt = $conn->query("SELECT id, nome_completo FROM usuarios ORDER BY nome_completo");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // BUSCAR MÓDULOS E AÇÕES PARA FILTRO
    // ============================================
    $stmt = $conn->query("SELECT DISTINCT modulo FROM logs_auditoria ORDER BY modulo");
    $modulos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $conn->query("SELECT DISTINCT acao FROM logs_auditoria ORDER BY acao");
    $acoes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // ============================================
    // ESTATÍSTICAS
    // ============================================
    $total_logs = count($logs);
    $stmt = $conn->query("SELECT COUNT(*) as total FROM logs_auditoria");
    $total_geral = $stmt->fetch()['total'] ?? 0;

    // Último login
    $stmt = $conn->query("SELECT * FROM logs_auditoria WHERE acao = 'login' ORDER BY criado_em DESC LIMIT 1");
    $ultimo_login = $stmt->fetch(PDO::FETCH_ASSOC);
    }

}
