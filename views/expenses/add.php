<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-plus-circle"></i> Nova Saída/Despesa</h2>
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
                                  style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;resize:vertical;"
                                  placeholder="Descreva a despesa..."></textarea>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Data <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="date" name="data_saida" value="<?= date('Y-m-d') ?>" required
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Valor (Kz) <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="number" name="valor" step="0.01" min="0.01" required
                               placeholder="0.00"
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Categoria
                        </label>
                        <input type="text" name="categoria" list="categorias_list"
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
                            <option value="cotas">Cotas (saldo disponível: <?= number_format($saldo_cotas, 2, ',', '.') ?> Kz)</option>
                            <option value="contribuicoes">Contribuições de fim de ano (saldo disponível: <?= number_format($saldo_contribuicoes, 2, ',', '.') ?> Kz)</option>
                        </select>
                    </div>
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Beneficiário
                        </label>
                        <input type="text" name="beneficiario" placeholder="Nome de quem recebeu"
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary" style="padding:12px 30px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-save"></i> Salvar
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
