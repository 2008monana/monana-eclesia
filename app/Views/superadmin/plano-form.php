<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$plan = is_array($plan ?? null) ? $plan : [];
$errorMessage = (string) ($errorMessage ?? '');
$isEdit = !empty($isEdit);
$action = $isEdit ? 'superadmin/planos/' . (int) ($plan['id'] ?? 0) . '/editar' : 'superadmin/planos';
?>
<div class="breadcrumb"><a href="<?= e(url('superadmin')) ?>">Plataforma</a> / <a href="<?= e(url('superadmin/planos')) ?>">Planos</a> / <?= $isEdit ? 'Editar' : 'Novo' ?></div>
<div class="page-head"><div><h2><i class="fas fa-layer-group"></i><?= $isEdit ? 'Editar plano' : 'Novo plano' ?></h2><div class="sub">Define valores comerciais e limites sem pressupostos pré-configurados.</div></div></div>
<?php if ($errorMessage !== ''): ?><div class="alert-danger" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= e($errorMessage) ?></span></div><?php endif; ?>
<div class="panel"><form class="admin-form" method="POST" action="<?= e(url($action)) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field-group"><label for="nome">Nome do plano</label><input id="nome" name="nome" maxlength="100" value="<?= e($plan['nome'] ?? '') ?>" required></div>
        <div class="field-group"><label for="preco_kz">Preço mensal (Kz)</label><input type="text" inputmode="decimal" id="preco_kz" name="preco_kz" placeholder="1.250,00" value="<?= e(isset($plan['preco_kz']) ? number_format((float) $plan['preco_kz'], 2, ',', '.') : '') ?>" required><div class="hint">Formato apresentado em Kz; o preço é configurado pelo Superadmin.</div></div>
        <div class="field-group"><label for="limite_membros">Limite de membros</label><input type="number" min="0" step="1" id="limite_membros" name="limite_membros" value="<?= e($plan['limite_membros'] ?? 0) ?>" required><div class="hint">0 = sem limite configurado nesta fase.</div></div>
        <div class="field-group"><label for="limite_utilizadores">Limite de utilizadores</label><input type="number" min="0" step="1" id="limite_utilizadores" name="limite_utilizadores" value="<?= e($plan['limite_utilizadores'] ?? 0) ?>" required><div class="hint">0 = sem limite configurado nesta fase.</div></div>
        <div class="field-group"><label for="ativo">Estado</label><select id="ativo" name="ativo"><option value="1" <?= (int) ($plan['ativo'] ?? 0) === 1 ? 'selected' : '' ?>>Activo</option><option value="0" <?= (int) ($plan['ativo'] ?? 0) !== 1 ? 'selected' : '' ?>>Inactivo</option></select></div>
    </div>
    <p class="hint" style="margin:18px 0">A definição de preços não activa cobrança automática; billing não faz parte desta fase.</p>
    <div class="admin-actions"><button class="admin-btn primary" type="submit"><i class="fas fa-floppy-disk"></i>Guardar plano</button><a class="admin-btn" href="<?= e(url('superadmin/planos')) ?>">Cancelar</a></div>
</form></div>
