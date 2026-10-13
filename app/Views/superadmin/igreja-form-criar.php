<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$plans = is_array($plans ?? null) ? $plans : [];
$errorMessage = (string) ($errorMessage ?? '');
$form = is_array($form ?? null) ? $form : [];
?>
<div class="breadcrumb"><a href="<?= e(url('superadmin')) ?>">Plataforma</a> / <a href="<?= e(url('superadmin/igrejas')) ?>">Igrejas</a> / Nova</div>
<div class="page-head"><div><h2><i class="fas fa-plus"></i>Criar igreja</h2><div class="sub">A conta de administração recebe um link de activação para definir a própria senha.</div></div></div>
<?php if ($errorMessage !== ''): ?><div class="alert-danger" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= e($errorMessage) ?></span></div><?php endif; ?>
<?php if ($plans === []): ?>
    <div class="alert-danger" role="alert"><i class="fas fa-triangle-exclamation"></i><span>Não há planos activos. Configura primeiro um plano em <a href="<?= e(url('superadmin/planos')) ?>">Gerir planos</a>.</span></div>
<?php else: ?>
    <div class="panel"><form class="admin-form" method="POST" action="<?= e(url('superadmin/igrejas')) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field-group"><label for="nome_igreja">Nome da igreja</label><input id="nome_igreja" name="nome_igreja" maxlength="180" value="<?= e($form['nome_igreja'] ?? '') ?>" required></div>
            <div class="field-group"><label for="plano_id">Plano</label><select id="plano_id" name="plano_id" required><option value="">Selecciona um plano</option>
                <?php foreach ($plans as $plan): ?><option value="<?= e((int) $plan['id']) ?>" <?= (string) ($form['plano_id'] ?? '') === (string) $plan['id'] ? 'selected' : '' ?>><?= e($plan['nome']) ?> — <?= e(number_format((float) $plan['preco_kz'], 2, ',', '.')) ?> Kz</option><?php endforeach; ?>
            </select></div>
            <div class="field-group"><label for="nome_admin">Nome completo do administrador</label><input id="nome_admin" name="nome_admin" maxlength="180" value="<?= e($form['nome_admin'] ?? '') ?>" autocomplete="name" required></div>
            <div class="field-group"><label for="email_admin">Email do administrador</label><input type="email" id="email_admin" name="email_admin" maxlength="191" value="<?= e($form['email_admin'] ?? '') ?>" autocomplete="email" required></div>
            <div class="field-group"><label for="telefone">Telefone <span style="font-weight:400;color:var(--brown-400)">(opcional)</span></label><input id="telefone" name="telefone" maxlength="40" value="<?= e($form['telefone'] ?? '') ?>"></div>
            <div class="field-group"><label for="cidade">Cidade / município <span style="font-weight:400;color:var(--brown-400)">(opcional)</span></label><input id="cidade" name="cidade" maxlength="120" value="<?= e($form['cidade'] ?? '') ?>"></div>
        </div>
        <p class="hint" style="margin:18px 0">A senha temporária é aleatória, fica apenas como hash e não é enviada. O link de activação expira em 24 horas. A criação da igreja é confirmada mesmo se o SMTP falhar; o estado fica visível na fila de emails.</p>
        <div class="admin-actions"><button class="admin-btn primary" type="submit"><i class="fas fa-church"></i>Criar igreja</button><a class="admin-btn" href="<?= e(url('superadmin/igrejas')) ?>">Cancelar</a></div>
    </form></div>
<?php endif; ?>
