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
