<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$membros = is_array($membros ?? null) ? $membros : [];
$configuracao = is_array($configuracao ?? null) ? $configuracao : [];
$dados = is_array($dados ?? null) ? $dados : [];
$erros = is_array($erros ?? null) ? $erros : [];
$titulo = (string) ($titulo ?? 'Registar pagamento');
$action = (string) ($action ?? 'cotas/novo');
$periodicidade = (string) ($configuracao['periodicidade_cota'] ?? 'mensal');
$valorCota = number_format((float) ($configuracao['valor_cota'] ?? 0), 2, ',', '.');
$inicioCotas = (string) ($configuracao['inicio_cotas'] ?? '');
?>
<div class="page-head">
    <div><h2><i class="fas fa-coins" aria-hidden="true"></i><?= e($titulo) ?></h2><div class="sub">Data do pagamento definida pelo servidor: <?= e(date('d/m/Y')) ?>.</div></div>
    <a class="admin-btn" href="<?= e(url('cotas')) ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i>Voltar</a>
</div>
<div class="panel admin-form">
    <div class="inline-notice" style="margin-bottom:18px">
        A referência é atribuída automaticamente ao período mais antigo ainda não coberto. O pagamento mantém-se inteiro, mesmo quando o valor cobre mais de um período.
        Cota configurada: <strong><?= e($valorCota) ?> Kz</strong> · <?= e($periodicidade === 'anual' ? 'ciclo móvel de 12 meses' : 'mensal') ?>.
        <?php if ($inicioCotas !== ''): ?>Início: <strong><?= e(date('d/m/Y', strtotime($inicioCotas))) ?></strong>.<?php endif; ?>
    </div>
    <?php if ($erros !== []): ?>
        <div class="danger-zone" role="alert"><strong>Confirma os campos assinalados.</strong><ul><?php foreach ($erros as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="POST" action="<?= e(url($action)) ?>">
        <?= csrf_field() ?>
        <div class="field-group">
            <label for="cota-membro">Membro <span aria-hidden="true">*</span></label>
            <select id="cota-membro" name="membro_id" required>
                <option value="">Selecciona um membro activo</option>
                <?php foreach ($membros as $membro): ?>
                    <?php $memberId = (int) ($membro['id'] ?? 0); ?>
                    <option value="<?= e($memberId) ?>"<?= (string) ($dados['membro_id'] ?? '') === (string) $memberId ? ' selected' : '' ?>><?= e($membro['nome_completo'] ?? '') ?> · <?= e(ucfirst((string) ($membro['categoria'] ?? ''))) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($erros['membro_id'])): ?><div class="hint" role="alert"><?= e($erros['membro_id']) ?></div><?php endif; ?>
        </div>
        <div class="form-grid">
            <div class="field-group">
                <label for="cota-valor">Valor pago (Kz) <span aria-hidden="true">*</span></label>
                <input id="cota-valor" name="valor" type="text" inputmode="decimal" maxlength="24" required value="<?= e($dados['valor'] ?? '') ?>" placeholder="Ex.: 1.250,00">
                <?php if (isset($erros['valor'])): ?><div class="hint" role="alert"><?= e($erros['valor']) ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="cota-observacao">Observação</label>
                <textarea id="cota-observacao" name="observacao" maxlength="1000" rows="3"><?= e($dados['observacao'] ?? '') ?></textarea>
                <?php if (isset($erros['observacao'])): ?><div class="hint" role="alert"><?= e($erros['observacao']) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="admin-actions" style="margin-top:18px">
            <button class="admin-btn primary" type="submit"><i class="fas fa-save" aria-hidden="true"></i>Guardar pagamento</button>
            <a class="admin-btn" href="<?= e(url('cotas')) ?>">Cancelar</a>
        </div>
    </form>
</div>
