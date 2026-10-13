<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$user = is_array($utilizador ?? null) ? $utilizador : [];
$name = (string) ($nomeEntrada ?? $user['nome_completo'] ?? '');
$message = (string) ($mensagem ?? '');
$error = (string) ($erro ?? '');
$initial = 'U';
if (preg_match('/^./u', $name, $match) === 1 && isset($match[0])) {
    $initial = strtoupper($match[0]);
}
?>
<div class="page-head"><div><h2><i class="fas fa-user-circle" aria-hidden="true"></i>Meu perfil</h2><div class="sub">Dados da tua própria conta nesta igreja.</div></div></div>
<?php if ($message !== ''): ?><div class="inline-notice" role="status"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="inline-notice" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="panel" style="max-width:760px"><div style="display:flex;align-items:center;gap:16px;margin-bottom:24px"><div class="profile-avatar" aria-hidden="true"><?= e($initial) ?></div><div><h3 style="margin:0"><?= e($user['nome_completo'] ?? '') ?></h3><div class="hint"><?= e($user['papel'] ?? '') ?></div></div></div>
<form method="POST" action="<?= e(url('perfil')) ?>" class="admin-form"><?= csrf_field() ?><div class="field-group"><label for="perfil-nome">Nome completo</label><input id="perfil-nome" name="nome_completo" value="<?= e($name) ?>" maxlength="180" required><div class="hint">O nome é usado na navegação e nos registos de auditoria.</div></div><div class="field-group"><label for="perfil-email">Email de acesso</label><input id="perfil-email" type="email" value="<?= e($user['email'] ?? '') ?>" readonly><div class="hint">O email não pode ser alterado sem um processo de verificação próprio.</div></div><div class="admin-actions"><button class="admin-btn primary" type="submit"><i class="fas fa-save" aria-hidden="true"></i>Guardar nome</button><a class="admin-btn" href="<?= e(url('trocar-senha')) ?>"><i class="fas fa-key" aria-hidden="true"></i>Alterar senha</a></div></form>
<div class="profile-meta" style="margin-top:22px;padding-top:18px;border-top:1px solid var(--line)"><span>Conta criada: <strong><?= !empty($user['criado_em']) ? e(date('d/m/Y', strtotime((string) $user['criado_em']))) : '—' ?></strong></span><span>Último acesso: <strong><?= !empty($user['ultimo_login']) ? e(date('d/m/Y H:i', strtotime((string) $user['ultimo_login']))) : 'Nunca' ?></strong></span></div></div>
