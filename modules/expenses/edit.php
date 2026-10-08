<?php
// modules/expenses/edit.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /ipfva-gestao/modules/auth/login.php');
    exit;
}


require_once '../../config/session.php';
requireModuleAccess('expenses');
$page_title = 'Editar Saída - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../modules/audit/functions.php';
require_once '../reports/functions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$conn = getConnection();

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
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-edit"></i> Editar Saída/Despesa</h2>
            <a href="<?= url('modules/expenses/index.php') ?>" style="display:inline-flex;align-items:center;gap:6px;color:var(--gray-500);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;">
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

        <div class="panel" style="max-width:700px;margin:0 auto;">
            <form method="POST" action="">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Descrição <span style="color:var(--danger);">*</span>
                        </label>
                        <textarea name="descricao" rows="3" required
                                  style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;resize:vertical;"><?= htmlspecialchars($saida['descricao']) ?></textarea>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Data <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="date" name="data_saida" value="<?= $saida['data_saida'] ?>" required
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Valor (Kz) <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="number" name="valor" step="0.01" min="0.01" required
                               value="<?= $saida['valor'] ?>"
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Categoria
                        </label>
                        <input type="text" name="categoria" list="categorias_list"
                               value="<?= htmlspecialchars($saida['categoria'] ?? '') ?>"
                               placeholder="Ex: Material, Serviços..."
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                        <datalist id="categorias_list">
                            <?php foreach ($categorias_existentes as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            De onde sai o dinheiro <span style="color:var(--danger);">*</span>
                        </label>
                        <select name="origem_fundo" id="origem_fundo" required
                                style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;">
                            <option value="cotas" <?= ($saida['origem_fundo'] ?? 'cotas') === 'cotas' ? 'selected' : '' ?>>Cotas (saldo disponível: <?= number_format($saldo_cotas, 2, ',', '.') ?> Kz)</option>
                            <option value="contribuicoes" <?= ($saida['origem_fundo'] ?? '') === 'contribuicoes' ? 'selected' : '' ?>>Contribuições de fim de ano (saldo disponível: <?= number_format($saldo_contribuicoes, 2, ',', '.') ?> Kz)</option>
                        </select>
                    </div>
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Beneficiário
                        </label>
                        <input type="text" name="beneficiario" value="<?= htmlspecialchars($saida['beneficiario'] ?? '') ?>"
                               placeholder="Nome de quem recebeu"
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary" style="padding:12px 30px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-save"></i> Atualizar
                    </button>
                    <a href="<?= url('modules/expenses/index.php') ?>" style="padding:12px 24px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:14px;transition:.2s;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>