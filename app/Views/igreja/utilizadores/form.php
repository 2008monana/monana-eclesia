<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$userId = isset($utilizadorId) ? (int) $utilizadorId : 0;
$isEdit = $userId > 0;
$roles = is_array($roles ?? null) ? $roles : [];
$modules = is_array($modulosDisponiveis ?? null) ? $modulosDisponiveis : [];
$selected = is_array($modulos ?? null) ? $modulos : [];
$errors = is_array($erros ?? null) ? $erros : [];
$name = (string) ($nome_completo ?? '');
$email = (string) ($email ?? '');
$role = (string) ($papel ?? 'tesoureiro');
$action = $isEdit ? 'utilizadores/' . $userId . '/editar' : 'utilizadores/novo';
?>
<div class="page-head"><div><h2><i class="fas <?= $isEdit ? 'fa-user-pen' : 'fa-user-plus' ?>" aria-hidden="true"></i><?= e($titulo ?? 'Utilizador') ?></h2><div class="sub">A conta pertence exclusivamente à igreja da sessão.</div></div><a class="admin-btn" href="<?= e(url('utilizadores')) ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i>Voltar</a></div>
<?php if (isset($errors['geral'])): ?><div class="inline-notice" role="alert"><?= e($errors['geral']) ?></div><?php endif; ?>
<div class="panel" style="max-width:820px"><form method="POST" action="<?= e(url($action)) ?>" class="admin-form">
    <?= csrf_field() ?>
    <div class="field-group"><label for="nome-utilizador">Nome completo</label><input id="nome-utilizador" name="nome_completo" value="<?= e($name) ?>" maxlength="180" required><?php if (isset($errors['nome_completo'])): ?><div class="field-error"><?= e($errors['nome_completo']) ?></div><?php endif; ?></div>
    <div class="field-group"><label for="email-utilizador">Email</label><input id="email-utilizador" name="email" type="email" value="<?= e($email) ?>" maxlength="191" required<?= $isEdit ? ' readonly aria-describedby="email-ajuda"' : '' ?>><?php if ($isEdit): ?><div class="hint" id="email-ajuda">O email de acesso não é alterado por este formulário; usa o link de senha para confirmar a caixa de correio.</div><?php endif; ?><?php if (isset($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?></div>
    <div class="field-group"><label for="papel-utilizador">Papel</label><select id="papel-utilizador" name="papel" required><?php foreach ($roles as $value => $label): ?><option value="<?= e($value) ?>"<?= $role === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php if (isset($errors['papel'])): ?><div class="field-error"><?= e($errors['papel']) ?></div><?php endif; ?></div>
    <fieldset class="field-group" style="border:0;padding:0;margin:0"><legend class="field-label">Módulos autorizados</legend><div class="permission-grid"><?php foreach ($modules as $key => $label): ?><label class="permission-option"><input type="checkbox" name="modulos[]" value="<?= e($key) ?>"<?= in_array($key, $selected, true) || $key === 'dashboard' ? ' checked' : '' ?><?= $key === 'dashboard' ? ' disabled' : '' ?>><span><?= e($label) ?></span></label><?php endforeach; ?></div><div class="hint">O Painel fica sempre disponível. Os módulos marcados também dependem da migração segura e dos respectivos gates de activação.</div></fieldset>
    <div class="phase-notice" role="note">A senha não é definida nem visualizada pelo administrador. Depois da criação, o utilizador recebe um link temporário de uso único para criar a sua própria senha; falhas de entrega ficam na fila para retry.</div>
    <div class="admin-actions"><button class="admin-btn primary" type="submit"><i class="fas fa-save" aria-hidden="true"></i><?= $isEdit ? 'Guardar alterações' : 'Criar utilizador' ?></button><a class="admin-btn" href="<?= e(url('utilizadores')) ?>">Cancelar</a></div>
</form></div>
