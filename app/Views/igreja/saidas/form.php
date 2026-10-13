<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$dados = is_array($dados ?? null) ? $dados : [];
$erros = is_array($erros ?? null) ? $erros : [];
$titulo = (string) ($titulo ?? 'Saída');
$action = (string) ($action ?? 'saidas/nova');
$saidaId = isset($saidaId) ? (int) $saidaId : null;
$fundos = ['cotas' => 'Fundo de cotas', 'contribuicoes' => 'Fundo de contribuições de fim de ano'];
?>
<div class="page-head"><div><h2><i class="fas fa-arrow-trend-down" aria-hidden="true"></i><?= e($titulo) ?></h2><div class="sub">Selecciona o fundo correcto; os saldos não são misturados.</div></div><a class="admin-btn" href="<?= e(url('saidas')) ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i>Voltar</a></div>
<div class="panel admin-form">
    <?php if ($erros !== []): ?><div class="danger-zone" role="alert"><strong>Confirma os campos assinalados.</strong><ul><?php foreach ($erros as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="POST" action="<?= e(url($action)) ?>">
        <?= csrf_field() ?>
        <div class="field-group"><label for="saida-descricao">Descrição <span aria-hidden="true">*</span></label><input id="saida-descricao" name="descricao" maxlength="255" required value="<?= e($dados['descricao'] ?? '') ?>"><?php if (isset($erros['descricao'])): ?><div class="hint" role="alert"><?= e($erros['descricao']) ?></div><?php endif; ?></div>
        <div class="form-grid">
            <div class="field-group"><label for="saida-valor">Valor (Kz) <span aria-hidden="true">*</span></label><input id="saida-valor" name="valor" type="text" inputmode="decimal" maxlength="24" required placeholder="Ex.: 1.250,00" value="<?= e($dados['valor'] ?? '') ?>"><?php if (isset($erros['valor'])): ?><div class="hint" role="alert"><?= e($erros['valor']) ?></div><?php endif; ?></div>
            <div class="field-group"><label for="saida-data">Data <span aria-hidden="true">*</span></label><input id="saida-data" name="data_saida" type="date" required value="<?= e($dados['data_saida'] ?? date('Y-m-d')) ?>"><?php if (isset($erros['data_saida'])): ?><div class="hint" role="alert"><?= e($erros['data_saida']) ?></div><?php endif; ?></div>
            <div class="field-group"><label for="saida-categoria">Categoria <span aria-hidden="true">*</span></label><input id="saida-categoria" name="categoria" maxlength="120" required value="<?= e($dados['categoria'] ?? '') ?>"><?php if (isset($erros['categoria'])): ?><div class="hint" role="alert"><?= e($erros['categoria']) ?></div><?php endif; ?></div>
            <div class="field-group"><label for="saida-fundo">Fundo <span aria-hidden="true">*</span></label><select id="saida-fundo" name="origem_fundo" required><?php foreach ($fundos as $value => $label): ?><option value="<?= e($value) ?>"<?= ($dados['origem_fundo'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php if (isset($erros['origem_fundo'])): ?><div class="hint" role="alert"><?= e($erros['origem_fundo']) ?></div><?php endif; ?></div>
            <div class="field-group"><label for="saida-beneficiario">Beneficiário</label><input id="saida-beneficiario" name="beneficiario" maxlength="180" value="<?= e($dados['beneficiario'] ?? '') ?>"><?php if (isset($erros['beneficiario'])): ?><div class="hint" role="alert"><?= e($erros['beneficiario']) ?></div><?php endif; ?></div>
        </div>
        <div class="admin-actions" style="margin-top:18px"><button class="admin-btn primary" type="submit"><i class="fas fa-save" aria-hidden="true"></i>Guardar</button><a class="admin-btn" href="<?= e(url('saidas')) ?>">Cancelar</a></div>
    </form>
</div>
