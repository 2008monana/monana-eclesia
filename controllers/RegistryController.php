<?php
// controllers/RegistryController.php
// Controlador do modulo "registry" - arquitectura MVC.
// Logica original dos antigos modules/registry/*.php preservada,
// agora separada em Model (dados) / Controller (fluxo) / View (HTML).

require_once BASE_PATH . '/models/RegistryModel.php';
require_once BASE_PATH . '/models/AuditModel.php';
use Dompdf\Dompdf;
use Dompdf\Options;

class RegistryController extends Controller
{
    protected string $modulo = 'registry';

    /** Antes: modules/registry/add.php */
    public function add(): void
    {
    // modules/registry/add.php

    if (!isset($_SESSION['user_id'])) {
        redirect('modules/auth/login.php');
        exit;
    }


    requireModuleAccess('registry');
    $page_title = 'Novo Registo - I.P.F.V.A Calemba 2';

    require_once '../lists/functions.php';
    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    $erro = '';
    $sucesso = '';

    // Buscar membros ativos para o select
    $stmt = $conn->query("SELECT id, nome_completo, categoria FROM membros WHERE ativo = 1 ORDER BY nome_completo");
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $categoria_labels_form = [
        'mama' => 'Mamã',
        'papa' => 'Papá',
        'jovem' => 'Jovem',
        'crianca' => 'Criança'
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $membro_id = intval($_POST['membro_id'] ?? 0);
        $valor = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0')));
        $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
        $observacao = trim($_POST['observacao'] ?? '');

        if ($membro_id <= 0) {
            $erro = 'Selecione um membro.';
        } elseif ($valor <= 0) {
            $erro = 'O valor deve ser maior que zero.';
        } else {
            try {
                // O valor pago é distribuído automaticamente pelos meses em
                // dívida, do mais antigo para o mais recente, até ao limite da
                // cota mensal em cada um: se entrou 1000 Kz, quita 1 mês; se
                // entrou 2000 Kz, quita 2 meses; e assim sucessivamente. Isto
                // garante que o selo de "Pago" apareça no(s) mês(es) mais
                // antigo(s) em dívida, e não fique tudo preso apenas ao
                // primeiro mês encontrado.
                $distribuicao = distribuirPagamentoPorMeses($membro_id, $valor);

                if (empty($distribuicao)) {
                    // Segurança: não deveria acontecer com valor > 0, mas evita
                    // perder o registo caso a distribuição não encontre mês.
                    $distribuicao[] = [
                        'mes_referencia' => sprintf('%04d-%02d-01', (int) date('Y'), (int) date('m')),
                        'valor' => $valor
                    ];
                }

                $sql = "INSERT INTO cotas_diarias (membro_id, data_pagamento, mes_referencia, valor, observacao, registrado_por) 
                        VALUES (:membro_id, :data_pagamento, :mes_referencia, :valor, :observacao, :registrado_por)";
                $stmt = $conn->prepare($sql);

                foreach ($distribuicao as $parcela) {
                    $stmt->execute([
                        ':membro_id' => $membro_id,
                        ':data_pagamento' => $data_pagamento,
                        ':mes_referencia' => $parcela['mes_referencia'],
                        ':valor' => $parcela['valor'],
                        ':observacao' => $observacao,
                        ':registrado_por' => $_SESSION['user_id']
                    ]);
                }

                $meses_lista = array_map(function ($p) {
                    return date('m/Y', strtotime($p['mes_referencia'])) . ' (' . number_format($p['valor'], 2, ',', '.') . ' Kz)';
                }, $distribuicao);
                $meses_texto = implode(', ', $meses_lista);

                $sucesso = 'Registo adicionado com sucesso! Mês(es) quitado(s): ' . $meses_texto;

                // Nome do membro para a auditoria (em vez de apenas o ID)
                $nome_membro_log = $membro_id;
                foreach ($membros as $m) {
                    if ($m['id'] == $membro_id) {
                        $nome_membro_log = $m['nome_completo'];
                        break;
                    }
                }
                registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'criar', 'registry', "Registou uma cota de $valor Kz para $nome_membro_log (referência: $meses_texto)");
                header('refresh:2;url=' . url('modules/registry/index.php'));
            } catch (PDOException $e) {
                $erro = 'Erro ao adicionar registo: ' . $e->getMessage();
            }
        }
    }
    }

    /** Antes: modules/registry/delete.php */
    public function delete(): void
    {
    // modules/registry/delete.php

    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }


    if (!podeAcessarModulo('registry')) {
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

    // Verificar permissão (apenas admin ou editor)
    if (!in_array($_SESSION['user_perfil'], ['admin', 'editor'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permissão negada']);
        exit;
    }

    // Buscar registro para verificar a data
    $stmt = $conn->prepare("
        SELECT c.data_pagamento, c.valor, m.nome_completo
        FROM cotas_diarias c
        JOIN membros m ON c.membro_id = m.id
        WHERE c.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
        exit;
    }

    // ============================================
    // VERIFICAR SE É O DIA ATUAL (BLOQUEAR EXCLUSÃO DE DIAS PASSADOS)
    // ============================================
    $hoje = date('Y-m-d');
    $data_registro = date('Y-m-d', strtotime($registro['data_pagamento']));

    if ($data_registro != $hoje) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Não é possível excluir registos de dias passados. Apenas o dia atual pode ser excluído.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("DELETE FROM cotas_diarias WHERE id = :id");
        $stmt->execute([':id' => $id]);

        registrarLog($_SESSION['user_id'], $_SESSION['user_nome'], 'excluir', 'registry', "Excluiu o registo de cota de {$registro['nome_completo']} - " . number_format($registro['valor'], 2, ',', '.') . " Kz (pago em " . date('d/m/Y', strtotime($registro['data_pagamento'])) . ")");

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    ?>
    }

    /** Antes: modules/registry/edit.php */
    public function edit(): void
    {
    // modules/registry/edit.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('registry');
    $page_title = 'Editar Registo - I.P.F.V.A Calemba 2';

    require_once '../lists/functions.php';
    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        redirect('modules/registry/index.php');
        exit;
    }

    // Buscar registro
    $stmt = $conn->prepare("
        SELECT c.*, m.nome_completo, m.categoria 
        FROM cotas_diarias c
        JOIN membros m ON c.membro_id = m.id
        WHERE c.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        redirect('modules/registry/index.php');
        exit;
    }

    // ============================================
    // VERIFICAR SE É O DIA ATUAL (BLOQUEAR EDIÇÃO DE DIAS PASSADOS)
    // ============================================
    $hoje = date('Y-m-d');
    $data_registro = date('Y-m-d', strtotime($registro['data_pagamento']));

    if ($data_registro != $hoje) {
        $_SESSION['erro'] = '⚠️ Não é possível editar registos de dias passados. Apenas o dia atual pode ser editado.';
        header('Location: ' . url('modules/registry/index.php'));
        exit;
    }

    // Buscar membros ativos para o select
    $stmt = $conn->query("SELECT id, nome_completo, categoria FROM membros WHERE ativo = 1 ORDER BY nome_completo");
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $erro = '';
    $sucesso = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $membro_id = intval($_POST['membro_id'] ?? 0);
        $valor = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0')));
        $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
        $observacao = trim($_POST['observacao'] ?? '');

        // Verificar se a data do pagamento é o dia atual (bloqueio adicional)
        if ($data_pagamento != $hoje) {
            $erro = '⚠️ Não é permitido alterar a data do pagamento para dias diferentes do atual.';
        } elseif ($membro_id <= 0) {
            $erro = 'Selecione um membro.';
        } elseif ($valor <= 0) {
            $erro = 'O valor deve ser maior que zero.';
        } else {
            try {
                // Verificar se o membro existe
                $stmt = $conn->prepare("SELECT id FROM membros WHERE id = :id AND ativo = 1");
                $stmt->execute([':id' => $membro_id]);
                if (!$stmt->fetch()) {
                    $erro = 'Membro inválido ou inativo.';
                } else {
                    // O novo valor é redistribuído automaticamente pelos meses em
                    // dívida do membro, do mais antigo para o mais recente - exatamente
                    // a mesma regra usada em Novo Registo. O próprio registo que está a
                    // ser editado é excluído do cálculo de "já pago" (senão contaria
                    // contra si mesmo). Isto pode transformar 1 registo em vários, se o
                    // valor cobrir mais de um mês - por isso o registo antigo é apagado
                    // e substituído pelas novas parcelas dentro de uma transação.
                    $distribuicao = distribuirPagamentoPorMeses($membro_id, $valor, $id);

                    if (empty($distribuicao)) {
                        $distribuicao[] = [
                            'mes_referencia' => sprintf('%04d-%02d-01', (int) date('Y'), (int) date('m')),
                            'valor' => $valor
                        ];
                    }

                    $conn->beginTransaction();

                    $stmtDel = $conn->prepare("DELETE FROM cotas_diarias WHERE id = :id");
                    $stmtDel->execute([':id' => $id]);

                    $stmtIns = $conn->prepare("
                        INSERT INTO cotas_diarias (membro_id, data_pagamento, mes_referencia, valor, observacao, registrado_por, criado_em)
                        VALUES (:membro_id, :data_pagamento, :mes_referencia, :valor, :observacao, :registrado_por, :criado_em)
                    ");
                    foreach ($distribuicao as $parcela) {
                        $stmtIns->execute([
                            ':membro_id' => $membro_id,
                            ':data_pagamento' => $data_pagamento,
                            ':mes_referencia' => $parcela['mes_referencia'],
                            ':valor' => $parcela['valor'],
                            ':observacao' => $observacao,
                            ':registrado_por' => $registro['registrado_por'],
                            ':criado_em' => $registro['criado_em'],
                        ]);
                    }

                    $conn->commit();

                    $meses_lista = array_map(function ($p) {
                        return date('m/Y', strtotime($p['mes_referencia'])) . ' (' . number_format($p['valor'], 2, ',', '.') . ' Kz)';
                    }, $distribuicao);
                    $meses_texto = implode(', ', $meses_lista);

                    // Registrar log
                    // Nome do membro selecionado (pode ter mudado no formulário)
                    $nome_membro_log = $registro['nome_completo'];
                    foreach ($membros as $m) {
                        if ($m['id'] == $membro_id) {
                            $nome_membro_log = $m['nome_completo'];
                            break;
                        }
                    }
                    registrarLog(
                        $_SESSION['user_id'],
                        $_SESSION['user_nome'],
                        'editar',
                        'registry',
                        "Editou o registo de cota de {$nome_membro_log} - Valor: {$valor} Kz (referência recalculada: {$meses_texto})"
                    );

                    $sucesso = '✅ Registo atualizado com sucesso! Mês(es) quitado(s): ' . $meses_texto;
                    header('refresh:2;url=' . url('modules/registry/index.php'));
                }
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $erro = 'Erro ao atualizar registo: ' . $e->getMessage();
            }
        }
    }
    }

    /** Antes: modules/registry/generate-pdf.php */
    public function generatePdf(): void
    {
    require_once __DIR__ . '/../../includes/pdf-theme.php';
    // modules/registry/generate-pdf.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('registry');

    // Verificar se o DOMPDF está instalado
    }


    use Dompdf\Dompdf;
    use Dompdf\Options;

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $data_filtro = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_filtro)) {
        $data_filtro = date('Y-m-d');
    }

    // ============================================
    // BUSCAR REGISTROS DO DIA
    // ============================================
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            m.nome_completo,
            m.categoria,
            u.nome_completo as registrado_nome
        FROM cotas_diarias c
        JOIN membros m ON c.membro_id = m.id
        LEFT JOIN usuarios u ON c.registrado_por = u.id
        WHERE DATE(c.data_pagamento) = :data
        ORDER BY c.criado_em DESC
    ");
    $stmt->execute([':data' => $data_filtro]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // CALCULAR TOTAIS
    // ============================================
    $total_geral = 0;
    $total_mamas = 0;
    $total_papas = 0;
    $total_jovens = 0;
    $total_membros_pagaram = [];

    foreach ($registros as $r) {
        $total_geral += $r['valor'];

        if ($r['categoria'] == 'mama') {
            $total_mamas += $r['valor'];
        } elseif ($r['categoria'] == 'papa') {
            $total_papas += $r['valor'];
        } elseif ($r['categoria'] == 'jovem') {
            $total_jovens += $r['valor'];
        }

        if (!in_array($r['membro_id'], $total_membros_pagaram)) {
            $total_membros_pagaram[] = $r['membro_id'];
        }
    }

    $total_membros_pagaram = count($total_membros_pagaram);

    // ============================================
    // RESPONSÁVEL DA COTA
    // ============================================
    // O responsável que assina o PDF é sempre quem efetivamente registou as
    // cotas naquele dia (não um cargo genérico). Se todos os registos do dia
    // foram feitos pela mesma pessoa, usamos o nome dela; caso contrário (ou
    // se não houver registos), caímos para o utilizador com sessão activa.
    $nomes_registrantes = array_unique(array_filter(array_column($registros, 'registrado_nome')));
    $responsavel_nome = count($nomes_registrantes) === 1 ? reset($nomes_registrantes) : ($_SESSION['user_nome'] ?? '-');

    // ============================================
    // MONTAR HTML PARA O PDF
    // ============================================
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: "DejaVu Sans", sans-serif;
                font-size: 10px;
                padding: 20px;
            }
            .header {
                text-align: center;
                border-bottom: 2px solid #3a271a;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .header h1 {
                color: #3a271a;
                margin: 0;
                font-size: 18px;
            }
            .header h3 {
                color: #c99a2e;
                margin: 5px 0;
                font-size: 14px;
            }
            .header p {
                color: #7d6a5b;
                font-size: 11px;
                margin: 2px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 9px;
            }
            th {
                background: #3a271a;
                color: white;
                padding: 5px 6px;
                text-align: center;
                font-weight: bold;
            }
            td {
                padding: 4px 6px;
                border: 1px solid #ece4d2;
            }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-left { text-align: left; }
            .total-row {
                background: #fbf8f1;
                font-weight: bold;
                border-top: 2px solid #333;
            }
            .footer {
                text-align: center;
                margin-top: 30px;
                font-size: 9px;
                color: #7d6a5b;
                border-top: 1px solid #ece4d2;
                padding-top: 10px;
            }
            .assinatura {
                margin-top: 30px;
                display: flex;
                justify-content: center;
            }
            .assinatura div {
                text-align: center;
            }
            .assinatura .line {
                border-top: 1px solid #000;
                width: 200px;
                margin: 0 auto;
                padding-top: 5px;
            }
            .resumo-categorias {
                margin-top: 15px;
                display: flex;
                justify-content: space-around;
                font-size: 10px;
            }
            .resumo-categorias .cat {
                padding: 8px 16px;
                border-radius: 8px;
            }
            .resumo-categorias .cat .label {
                font-weight: bold;
            }
            .resumo-categorias .cat .valor {
                font-size: 14px;
                font-weight: bold;
            }
            .badge-categoria {
                display: inline-block;
                padding: 1px 8px;
                border-radius: 12px;
                color: #fff;
                font-weight: 600;
                font-size: 8px;
            }
        ' . pdfBrandCss() . '
        </style>
    </head>
    <body>';

    if (empty($registros)) {
        $html .= '
        <div class="header">
            ' . pdfCabecalho() . '
        <p class="doc-title">REGISTO DIÁRIO DE COTAS</p>
            <p>Data: ' . date('d/m/Y', strtotime($data_filtro)) . '</p>
            <p>Data de emissão: ' . date('d/m/Y H:i') . '</p>
        </div>
        <div style="text-align:center;padding:50px;color:#a39485;font-size:14px;">
            <p>Nenhum registro encontrado para esta data.</p>
        </div>';
    } else {
        // Categorias para cores
        $categoria_cores = [
            'mama' => ['label' => 'Mamã', 'color' => '#b5527a'],
            'papa' => ['label' => 'Papá', 'color' => '#3f7d4e'],
            'jovem' => ['label' => 'Jovem', 'color' => '#7a5ca8']
        ];

        $html .= '
        <div class="header">
            ' . pdfCabecalho() . '
        <p class="doc-title">REGISTO DIÁRIO DE COTAS</p>
            <p>Data: ' . date('d/m/Y', strtotime($data_filtro)) . '</p>
            <p>Data de emissão: ' . date('d/m/Y H:i') . '</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="text-align:left;">#</th>
                    <th style="text-align:left;">Membro</th>
                    <th style="text-align:left;">Categoria</th>
                    <th style="text-align:left;">Mês Referência</th>
                    <th style="text-align:right;">Valor (Kz)</th>
                    <th style="text-align:left;">Registrado por</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($registros as $index => $r) {
            $cat = $categoria_cores[$r['categoria']] ?? ['label' => $r['categoria'], 'color' => '#gray-500'];

            $html .= '
            <tr>
                <td>' . ($index + 1) . '</td>
                <td style="font-weight:bold;">' . htmlspecialchars($r['nome_completo']) . '</td>
                <td><span class="badge-categoria" style="background:' . $cat['color'] . ';">' . $cat['label'] . '</span></td>
                <td>' . date('m/Y', strtotime($r['mes_referencia'])) . '</td>
                <td class="text-right" style="color:#3f7d4e;font-weight:bold;">' . number_format($r['valor'], 2, ',', '.') . '</td>
                <td>' . htmlspecialchars($r['registrado_nome'] ?? '-') . '</td>
            </tr>';
        }

        $html .= '
            <tr class="total-row">
                <td colspan="4" class="text-right" style="font-size:11px;">TOTAL DO DIA:</td>
                <td class="text-right" style="font-size:11px;color:#3f7d4e;font-weight:bold;">' . number_format($total_geral, 2, ',', '.') . ' Kz</td>
                <td></td>
            </tr>
        </tbody>
        </table>

        <!-- Resumo por Categoria -->
        <div class="resumo-categorias">
            <div class="cat" style="background:#fce4ec;">
                <div class="label" style="color:#b5527a;"><i class="fas fa-female"></i> Mamãs</div>
                <div class="valor" style="color:#b5527a;">' . number_format($total_mamas, 2, ',', '.') . ' Kz</div>
            </div>
            <div class="cat" style="background:#eaf3ec;">
                <div class="label" style="color:#3f7d4e;"><i class="fas fa-male"></i> Papás</div>
                <div class="valor" style="color:#3f7d4e;">' . number_format($total_papas, 2, ',', '.') . ' Kz</div>
            </div>
            <div class="cat" style="background:#ede7f6;">
                <div class="label" style="color:#7a5ca8;"><i class="fas fa-user-graduate"></i> Jovens</div>
                <div class="valor" style="color:#7a5ca8;">' . number_format($total_jovens, 2, ',', '.') . ' Kz</div>
            </div>
        </div>

        <div style="margin-top:15px;font-size:10px;color:#7d6a5b;text-align:center;">
            <p><strong>Resumo:</strong> ' . $total_membros_pagaram . ' membros pagaram | Total: ' . number_format($total_geral, 2, ',', '.') . ' Kz</p>
        </div>';
    }

    $html .= '
        <div class="assinatura">
            <div>
                <div class="line">_________________</div>
                <p style="font-size:10px;margin-top:5px;">Assinatura do Responsável da Cota</p>
                <p style="font-size:10px;margin-top:2px;font-weight:bold;">' . htmlspecialchars($responsavel_nome) . '</p>
            </div>
        </div>

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

    // Orientação: portrait (retrato) para registo diário
    $dompdf->setPaper('A4', 'portrait');

    $dompdf->render();

    // Nome do arquivo
    $nome_arquivo = 'registo_diario_' . date('Y-m-d', strtotime($data_filtro)) . '.pdf';

    // Forçar download ou exibir no navegador
    $dompdf->stream($nome_arquivo, array("Attachment" => false));
    exit;
    }

    /** Antes: modules/registry/index.php */
    public function index(): void
    {
    // modules/registry/index.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }


    requireModuleAccess('registry');
    $page_title = 'Registo Diário - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    // ============================================
    // PARÂMETROS
    // ============================================
    $data_filtro = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

    // Validar data
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_filtro)) {
        $data_filtro = date('Y-m-d');
    }

    // Verificar se é o dia atual
    $hoje = date('Y-m-d');
    $is_hoje = ($data_filtro == $hoje);

    // ============================================
    // BUSCAR REGISTROS DO DIA
    // ============================================
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            m.nome_completo,
            m.categoria,
            u.nome_completo as registrado_nome
        FROM cotas_diarias c
        JOIN membros m ON c.membro_id = m.id
        LEFT JOIN usuarios u ON c.registrado_por = u.id
        WHERE DATE(c.data_pagamento) = :data
        ORDER BY c.criado_em DESC
    ");
    $stmt->execute([':data' => $data_filtro]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // CALCULAR TOTAIS
    // ============================================
    $total_geral = 0;
    $total_mamas = 0;
    $total_papas = 0;
    $total_jovens = 0;
    $total_membros_pagaram = [];

    foreach ($registros as $r) {
        $total_geral += $r['valor'];

        if ($r['categoria'] == 'mama') {
            $total_mamas += $r['valor'];
        } elseif ($r['categoria'] == 'papa') {
            $total_papas += $r['valor'];
        } elseif ($r['categoria'] == 'jovem') {
            $total_jovens += $r['valor'];
        }

        if (!in_array($r['membro_id'], $total_membros_pagaram)) {
            $total_membros_pagaram[] = $r['membro_id'];
        }
    }

    $total_membros_pagaram = count($total_membros_pagaram);

    // ============================================
    // BUSCAR DATAS COM REGISTROS PARA NAVEGAÇÃO
    // ============================================
    $stmt = $conn->query("
        SELECT DISTINCT DATE(data_pagamento) as data 
        FROM cotas_diarias 
        ORDER BY data_pagamento DESC 
        LIMIT 30
    ");
    $datas_com_registros = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Mensagem de erro (para edição bloqueada)
    if (isset($_SESSION['erro'])) {
        $erro_msg = $_SESSION['erro'];
        unset($_SESSION['erro']);
    } else {
        $erro_msg = '';
    }
    }

    /** Antes: modules/registry/recalcular-referencias.php */
    public function recalcularReferencias(): void
    {
    // modules/registry/recalcular-referencias.php
    //
    // FERRAMENTA DE CORREÇÃO DE DADOS ANTIGOS
    // ==========================================================
    // Registos de cotas criados ANTES da distribuição automática existir (ou
    // editados manualmente em edit.php) podem ter o "mês de referência" preso
    // ao mês do calendário em que o dinheiro entrou, em vez do mês mais antigo
    // que o membro ainda devia - o que faz meses antigos aparecerem em dívida
    // mesmo havendo dinheiro suficiente já pago mais adiante no ano.
    //
    // Esta página reprocessa, por membro e por ano, todos os pagamentos em
    // ordem cronológica (data_pagamento, id) e reaplica exatamente a mesma
    // regra usada em modules/lists/functions.php::distribuirPagamentoPorMeses():
    // preenche sempre o mês mais antigo em dívida até 1.000 Kz antes de passar
    // ao seguinte, dividindo um pagamento entre dois meses quando necessário.
    //
    // Fluxo: 1) ANALISAR (GET, só leitura) mostra uma pré-visualização com o
    // que mudaria. 2) Só depois de rever, o utilizador confirma e os registos
    // desse membro/ano são substituídos dentro de uma transação.
    //
    // Restrito a administradores - reescreve registos financeiros.

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }

    requireModuleAccess('registry');

    if (($_SESSION['user_perfil'] ?? '') !== 'admin') {
        redirect('profile?sem_acesso=1');
        exit;
    }

    $page_title = 'Recalcular Referências - I.P.F.V.A Calemba 2';

    require_once '../lists/functions.php';
    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    // ============================================
    // FUNÇÃO PRINCIPAL DE RECÁLCULO (só lê a BD, não escreve nada)
    // ============================================
    /**
     * Reprocessa os pagamentos de um membro num ano, na ordem em que
     * entraram, e devolve a distribuição "correta" (mês mais antigo em
     * dívida primeiro), junto com a distribuição atual, para comparação.
     *
     * IMPORTANTE (corrigido): um pagamento real NUNCA é dividido entre dois
     * meses. Cada pagamento (identificado pela sua data_pagamento) é atribuído
     * por inteiro a um único mês de referência - o mês mais antigo ainda em
     * dívida nesse momento - mesmo que o valor ultrapasse a cota desse mês.
     * O excedente fica "adiantado" nesse mesmo mês; é o saldo acumulado
     * (calcularSaldoMembro / verificarStatusMes) que reconhece esse adiantamento
     * e mostra os meses seguintes como quitados, sem precisar de fragmentar o
     * registo. Isto evita que o registo diário e as listas mostrem um único
     * pagamento partido em pedaços.
     *
     * Também consolida, num único registo, pagamentos que uma versão anterior
     * desta ferramenta já tenha dividido entre dois meses (vários registos com
     * a mesma data_pagamento passam a ser tratados como um só pagamento real).
     */
    function calcularRedistribuicao($conn, $membro_id, $ano) {
        $cota = getCotaMensal();

        $stmt = $conn->prepare("
            SELECT id, data_pagamento, mes_referencia, valor, observacao, registrado_por, criado_em
            FROM cotas_diarias
            WHERE membro_id = :membro_id AND YEAR(data_pagamento) = :ano
            ORDER BY data_pagamento ASC, id ASC
        ");
        $stmt->execute([':membro_id' => $membro_id, ':ano' => $ano]);
        $originais = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($originais)) {
            return null;
        }

        // 1) Agrupar por data_pagamento: vários registos com a MESMA data são,
        // na prática, um único pagamento real - possivelmente já fragmentado por
        // uma versão anterior desta ferramenta. Junta-os antes de recalcular.
        $pagamentos = [];
        foreach ($originais as $r) {
            $data = $r['data_pagamento'];
            if (!isset($pagamentos[$data])) {
                $pagamentos[$data] = [
                    'data_pagamento' => $data,
                    'valor'          => 0.0,
                    'orig_ids'       => [],
                    'observacao'     => $r['observacao'],
                    'registrado_por' => $r['registrado_por'],
                    'criado_em'      => $r['criado_em'],
                ];
            }
            $pagamentos[$data]['valor'] += round((float) $r['valor'], 2);
            $pagamentos[$data]['orig_ids'][] = (int) $r['id'];
        }
        $pagamentos = array_values($pagamentos); // mantém a ordem cronológica das chaves

        // 2) Percorrer os pagamentos em ordem cronológica e atribuir cada um,
        // por inteiro, ao mês mais antigo ainda em dívida.
        $novos = [];
        $total_por_mes = array_fill(1, 12, 0.0);
        $cursor = 1;

        foreach ($pagamentos as $p) {
            while ($cursor <= 12 && $total_por_mes[$cursor] >= $cota - 0.001) {
                $cursor++;
            }

            if ($cursor <= 12) {
                $ano_destino = $ano;
                $mes_destino = $cursor;
            } else {
                // Todos os meses do ano já quitados: adiantamento para Janeiro do ano seguinte.
                $ano_destino = $ano + 1;
                $mes_destino = 1;
            }
            $mes_referencia = sprintf('%04d-%02d-01', $ano_destino, $mes_destino);

            $novos[] = [
                'orig_ids'       => $p['orig_ids'],
                'mes_referencia' => $mes_referencia,
                'data_pagamento' => $p['data_pagamento'],
                'valor'          => round($p['valor'], 2),
                'observacao'     => $p['observacao'],
                'registrado_por' => $p['registrado_por'],
                'criado_em'      => $p['criado_em'],
            ];

            if ($ano_destino == $ano) {
                $total_por_mes[$mes_destino] += $p['valor'];
            }
        }

        // 3) Verificar se mudou algo: mudou se algum pagamento estava
        // fragmentado em mais de um registo, ou se o mês/valor final é
        // diferente do que já lá estava.
        $mudou = false;
        $originais_por_id = [];
        foreach ($originais as $r) {
            $originais_por_id[(int) $r['id']] = $r;
        }
        foreach ($novos as $n) {
            if (count($n['orig_ids']) > 1) {
                $mudou = true;
                break;
            }
            $orig = $originais_por_id[$n['orig_ids'][0]];
            if ($orig['mes_referencia'] !== $n['mes_referencia'] || abs((float) $orig['valor'] - $n['valor']) > 0.001) {
                $mudou = true;
                break;
            }
        }

        return [
            'originais' => $originais,
            'novos'     => $novos,
            'mudou'     => $mudou,
        ];
    }

    /**
     * Aplica a redistribuição calculada, substituindo os registos antigos.
     *
     * IMPORTANTE: a tabela `cotas_diarias` usa engine MyISAM, que não suporta
     * transações reais (um ROLLBACK não desfaz nada nela). Por isso, em vez de
     * apagar primeiro e inserir depois, fazemos o inverso: inserimos todos os
     * novos registos primeiro e só apagamos os antigos depois de confirmar que
     * TODOS os novos foram gravados com sucesso. Se algo falhar a meio, os
     * registos antigos permanecem intactos e os novos que já tenham entrado
     * são removidos (melhor esforço), para não haver duplicação de valores.
     */
    function aplicarRedistribuicao($conn, $membro_id, $ano, $resultado) {
        $ins = $conn->prepare("
            INSERT INTO cotas_diarias (membro_id, data_pagamento, mes_referencia, valor, observacao, registrado_por, criado_em)
            VALUES (:membro_id, :data_pagamento, :mes_referencia, :valor, :observacao, :registrado_por, :criado_em)
        ");

        $ids_inseridos = [];
        try {
            foreach ($resultado['novos'] as $n) {
                $ins->execute([
                    ':membro_id'      => $membro_id,
                    ':data_pagamento' => $n['data_pagamento'],
                    ':mes_referencia' => $n['mes_referencia'],
                    ':valor'          => $n['valor'],
                    ':observacao'     => $n['observacao'],
                    ':registrado_por' => $n['registrado_por'],
                    ':criado_em'      => $n['criado_em'],
                ]);
                $ids_inseridos[] = (int) $conn->lastInsertId();
            }
        } catch (Exception $e) {
            // Melhor esforço: remove os novos registos já inseridos antes de
            // propagar o erro, para não deixar valores duplicados.
            if (!empty($ids_inseridos)) {
                $placeholders = implode(',', array_fill(0, count($ids_inseridos), '?'));
                $conn->prepare("DELETE FROM cotas_diarias WHERE id IN ($placeholders)")->execute($ids_inseridos);
            }
            throw $e;
        }

        // Só chega aqui se TODOS os novos registos foram gravados com sucesso.
        $ids_antigos = array_map(function ($r) { return $r['id']; }, $resultado['originais']);
        if (!empty($ids_antigos)) {
            $placeholders = implode(',', array_fill(0, count($ids_antigos), '?'));
            $conn->prepare("DELETE FROM cotas_diarias WHERE id IN ($placeholders)")->execute($ids_antigos);
        }

        return true;
    }

    // ============================================
    // PARÂMETROS
    // ============================================
    $ano = validarAnoCotas($_GET['ano'] ?? $_POST['ano'] ?? date('Y'));
    $membro_id_filtro = intval($_GET['membro_id'] ?? $_POST['membro_id'] ?? 0);
    $modo = $_GET['modo'] ?? '';
    $mensagem = '';
    $erro = '';

    // Lista de membros para o seletor
    $stmt = $conn->query("SELECT id, nome_completo, categoria FROM membros ORDER BY nome_completo");
    $todos_membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Membros a analisar: um específico, ou todos os que têm cotas nesse ano
    if ($membro_id_filtro > 0) {
        $membros_analisar = array_values(array_filter($todos_membros, function ($m) use ($membro_id_filtro) {
            return $m['id'] == $membro_id_filtro;
        }));
    } else {
        $stmt = $conn->prepare("
            SELECT DISTINCT m.id, m.nome_completo, m.categoria
            FROM membros m
            JOIN cotas_diarias c ON c.membro_id = m.id
            WHERE YEAR(c.data_pagamento) = :ano
            ORDER BY m.nome_completo
        ");
        $stmt->execute([':ano' => $ano]);
        $membros_analisar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================
    // APLICAR (POST de confirmação)
    // ============================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'aplicar') {
        $ids_para_aplicar = isset($_POST['aplicar_membro']) && is_array($_POST['aplicar_membro'])
            ? array_map('intval', $_POST['aplicar_membro'])
            : [];

        if (empty($ids_para_aplicar)) {
            $erro = 'Nenhum membro selecionado para corrigir.';
        } else {
            $corrigidos = 0;
            try {
                foreach ($ids_para_aplicar as $mid) {
                    $resultado = calcularRedistribuicao($conn, $mid, $ano);
                    if ($resultado && $resultado['mudou']) {
                        aplicarRedistribuicao($conn, $mid, $ano, $resultado);
                        $corrigidos++;
                    }
                }
                registrarLog(
                    $_SESSION['user_id'],
                    $_SESSION['user_nome'],
                    'editar',
                    'registry',
                    "Recalculou automaticamente o mês de referência das cotas de {$corrigidos} membro(s) para o ano {$ano}"
                );
                $mensagem = "✅ Referências corrigidas com sucesso para {$corrigidos} membro(s) no ano {$ano}.";
            } catch (Exception $e) {
                $erro = 'Erro ao aplicar as correções: ' . $e->getMessage();
            }
        }
    }

    // ============================================
    // ANALISAR (calcula a pré-visualização para todos os membros selecionados)
    // ============================================
    $resultados = [];
    if ($modo === 'analisar' || $mensagem) {
        foreach ($membros_analisar as $m) {
            $r = calcularRedistribuicao($conn, $m['id'], $ano);
            if ($r) {
                $resultados[] = [
                    'membro' => $m,
                    'resultado' => $r,
                ];
            }
        }
    }

    $total_com_diferenca = count(array_filter($resultados, function ($r) { return $r['resultado']['mudou']; }));
    }

}
