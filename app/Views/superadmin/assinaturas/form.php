<?php
/** @var array $supervisor */
/** @var array $igrejas */
/** @var array $planos */
/** @var array $errors */
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('superadmin/assinaturas')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title">Nova Assinatura</h1>
    </div>
</div>

<form method="post" action="<?= esc(url('superadmin/assinaturas')) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:700px">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-file-contract" aria-hidden="true"></i>
                Dados da Assinatura
            </div>
        </div>

        <p class="muted" style="font-size:13px;margin:0 0 18px">
            Use este formulário quando o pagamento foi feito por fora (transferência/depósito) e precisa ativar a assinatura manualmente.
        </p>

        <div style="display:grid;gap:14px">
            <div>
                <label class="lb">Igreja *</label>
                <select name="igreja_id" class="select" required>
                    <option value="">Escolher igreja...</option>
                    <?php foreach ($igrejas as $ig): ?>
                        <option value="<?= (int) $ig['id'] ?>" <?= (int) ($_POST['igreja_id'] ?? 0) === (int) $ig['id'] ? 'selected' : '' ?>>
                            <?= esc($ig['nome']) ?> (<?= esc($ig['slug']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="lb">Plano *</label>
                <select name="plano_id" class="select" required>
                    <option value="">Escolher plano...</option>
                    <?php foreach ($planos as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) ($_POST['plano_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= esc($p['nome']) ?> — <?= fmtKz($p['preco_kz']) ?>
                            <?= (int) $p['periodicidade_dias'] > 0 ? '(' . (int) $p['periodicidade_dias'] . ' dias)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="lb">Duração (dias)</label>
                <input name="dias" type="number" min="1" class="input"
                       value="<?= (int) ($_POST['dias'] ?? 30) ?>">
                <p class="muted" style="font-size:11.5px;margin:4px 0 0">
                    Deixe como está para usar a periodicidade do plano.
                </p>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px">
            <a href="<?= esc(url('superadmin/assinaturas')) ?>" class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                Criar assinatura
            </button>
        </div>
    </div>
</form>