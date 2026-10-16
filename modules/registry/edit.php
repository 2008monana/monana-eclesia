<?php
// modules/registry/edit.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('registry');
$page_title = 'Editar Registo - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../lists/functions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$conn = getConnection();

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
                require_once '../../modules/audit/functions.php';
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
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>
    
    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-edit"></i> Editar Registo</h2>
            <a href="<?= url('modules/registry/index.php') ?>" style="display:inline-flex;align-items:center;gap:6px;color:var(--gray-500);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;">
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

        <!-- Aviso de bloqueio -->
        <div style="background:#fdf1d9;border:1px solid #f5c6a0;color:#856404;padding:10px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px;">
            <i class="fas fa-lock"></i> 
            <strong>Atenção:</strong> Apenas registos do dia atual podem ser editados. A data do pagamento está fixa em <strong><?= date('d/m/Y', strtotime($hoje)) ?></strong>.
        </div>

        <div class="panel" style="max-width:700px;margin:0 auto;">
            <div style="background:#fcf8ec;border:1px solid #eddba0;color:var(--blue-800);padding:10px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:12.5px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-info-circle"></i>
                O <strong>mês de referência</strong> é recalculado automaticamente para o(s) mês(es) mais antigo(s) em dívida do membro, tal como no Novo Registo - não é possível escolhê-lo manualmente. Se o novo valor cobrir mais de um mês, o sistema volta a dividir este registo em várias parcelas.
            </div>

            <form method="POST" action="">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Membro <span style="color:var(--danger);">*</span>
                        </label>
                        <select name="membro_id" required
                                style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;">
                            <option value="">Selecione um membro...</option>
                            <?php foreach ($membros as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $registro['membro_id'] == $m['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nome_completo']) ?> (<?= ucfirst($m['categoria']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Data do Pagamento <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="date" name="data_pagamento" value="<?= $hoje ?>" readonly
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;background:var(--gray-100);cursor:not-allowed;">
                        <span style="font-size:11px;color:var(--gray-400);">(Data fixa - apenas hoje)</span>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Valor (Kz) <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="number" name="valor" step="0.01" min="0.01" required
                               value="<?= $registro['valor'] ?>"
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Observação (opcional)
                        </label>
                        <textarea name="observacao" rows="2"
                                  style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;resize:vertical;"><?= htmlspecialchars($registro['observacao'] ?? '') ?></textarea>
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary" style="padding:12px 30px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-save"></i> Atualizar
                    </button>
                    <a href="<?= url('modules/registry/index.php') ?>" style="padding:12px 24px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:14px;transition:.2s;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>