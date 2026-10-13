<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$users = is_array($utilizadores ?? null) ? $utilizadores : [];
$roles = ['pastor' => 'Pastor', 'tesoureiro' => 'Tesoureiro', 'secretario' => 'Secretário(a)', 'lider' => 'Líder'];
$filterRole = (string) ($filtroPapel ?? '');
$filterStatus = (string) ($filtroEstado ?? '');
$message = (string) ($mensagem ?? '');
?>
<div class="page-head"><div><h2><i class="fas fa-users-gear" aria-hidden="true"></i>Utilizadores</h2><div class="sub">Contas e permissões limitadas à tua igreja. O acesso Superadmin é gerido separadamente.</div></div><a class="admin-btn primary" href="<?= e(url('utilizadores/novo')) ?>"><i class="fas fa-user-plus" aria-hidden="true"></i>Novo utilizador</a></div>
<?php if ($message !== ''): ?><div class="inline-notice" role="status"><?= e($message) ?></div><?php endif; ?>
<div class="grid-stats" style="margin-bottom:20px"><div class="stat-card"><div class="stat-icon"><i class="fas fa-users" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($total ?? 0)) ?></div><div class="stat-label">Contas geridas</div></div><div class="stat-card"><div class="stat-icon"><i class="fas fa-user-check" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($activos ?? 0)) ?></div><div class="stat-label">Activas</div></div><div class="stat-card"><div class="stat-icon"><i class="fas fa-user-slash" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($inactivos ?? 0)) ?></div><div class="stat-label">Inactivas</div></div></div>
<div class="panel" style="margin-bottom:20px"><form method="GET" action="<?= e(url('utilizadores')) ?>" class="admin-form" style="max-width:none;display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap"><div class="field-group" style="min-width:180px;margin:0"><label for="filtro-papel">Papel</label><select name="papel" id="filtro-papel"><option value="">Todos</option><?php foreach ($roles as $value => $label): ?><option value="<?= e($value) ?>"<?= $filterRole === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="field-group" style="min-width:160px;margin:0"><label for="filtro-estado">Estado</label><select name="estado" id="filtro-estado"><option value="">Todos</option><option value="activo"<?= $filterStatus === 'activo' ? ' selected' : '' ?>>Activos</option><option value="inactivo"<?= $filterStatus === 'inactivo' ? ' selected' : '' ?>>Inactivos</option></select></div><button class="admin-btn" type="submit"><i class="fas fa-filter" aria-hidden="true"></i>Filtrar</button><a class="admin-btn" href="<?= e(url('utilizadores')) ?>">Limpar</a></form></div>
<div class="panel"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Utilizador</th><th>Papel</th><th>Permissões</th><th>Estado</th><th>Último acesso</th><th>Acções</th></tr></thead><tbody>
<?php if ($users === []): ?><tr><td colspan="6" class="empty-note">Não há utilizadores nesta selecção.</td></tr><?php else: foreach ($users as $user): ?>
<?php
$id = (int) ($user['id'] ?? 0);
$active = (int) ($user['ativo'] ?? 0) === 1;
$permissions = array_filter(explode(',', (string) ($user['permissoes'] ?? '')));
?>
<tr><td><strong><?= e($user['nome_completo'] ?? '') ?></strong><div class="hint"><?= e($user['email'] ?? '') ?></div></td><td><?= e($roles[$user['papel'] ?? ''] ?? '—') ?></td><td><?= e(count($permissions)) ?> módulo(s)<?= empty($user['email_verificado_em']) ? '<div class="hint">Email por confirmar</div>' : '' ?></td><td><span class="status-pill<?= $active ? ' ok' : ' bad' ?>"><?= $active ? 'Activo' : 'Inactivo' ?></span></td><td><?= !empty($user['ultimo_login']) ? e(date('d/m/Y H:i', strtotime((string) $user['ultimo_login']))) : 'Nunca' ?></td><td><div class="admin-actions"><a class="admin-btn small" href="<?= e(url('utilizadores/' . $id . '/editar')) ?>" aria-label="Editar utilizador"><i class="fas fa-pen" aria-hidden="true"></i></a>
<form method="POST" action="<?= e(url('utilizadores/' . $id . '/estado')) ?>"><?= csrf_field() ?><input type="hidden" name="activo" value="<?= $active ? '0' : '1' ?>"><button class="admin-btn small<?= $active ? ' danger' : ' primary' ?>" type="submit" aria-label="<?= $active ? 'Desactivar' : 'Activar' ?> utilizador"><?= $active ? 'Desactivar' : 'Activar' ?></button></form>
<form method="POST" action="<?= e(url('utilizadores/' . $id . '/redefinir-senha')) ?>"><?= csrf_field() ?><button class="admin-btn small" type="submit" aria-label="Enviar link de senha"><i class="fas fa-key" aria-hidden="true"></i></button></form>
<form method="POST" action="<?= e(url('utilizadores/' . $id . '/apagar')) ?>" onsubmit="return confirm('Desactivar esta conta? Os registos de auditoria serão preservados.');"><?= csrf_field() ?><button class="admin-btn small danger" type="submit" aria-label="Desactivar e preservar"><i class="fas fa-user-slash" aria-hidden="true"></i></button></form>
</div></td></tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>
