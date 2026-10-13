<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$errorMessage = (string) ($errorMessage ?? '');
$mustChange = !empty($mustChange);
?>
<h1><?= $mustChange ? 'Define a tua senha' : 'Alterar senha' ?></h1>
<p class="lead"><?= $mustChange ? 'Por segurança, tens de escolher uma senha pessoal antes de continuar.' : 'Confirma a senha actual e escolhe uma nova senha.' ?></p>
<?php if ($errorMessage !== ''): ?>
    <div class="alert-danger" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= e($errorMessage) ?></span></div>
<?php endif; ?>
<form method="POST" action="<?= e(url('trocar-senha')) ?>">
    <?= csrf_field() ?>
    <?php if (!$mustChange): ?>
        <div class="field-group">
            <label class="field-label" for="senha_actual">Senha actual</label>
            <div class="field"><span class="ico"><i class="fas fa-lock"></i></span>
                <input type="password" name="senha_actual" id="senha_actual" autocomplete="current-password" maxlength="200" required>
            </div>
        </div>
    <?php endif; ?>
    <div class="field-group">
        <label class="field-label" for="senha">Nova senha</label>
        <div class="field"><span class="ico"><i class="fas fa-key"></i></span>
            <input type="password" name="senha" id="senha" autocomplete="new-password" minlength="12" maxlength="<?= e(\App\Services\PasswordHasher::maximumLength()) ?>" required>
        </div>
    </div>
    <div class="field-group">
        <label class="field-label" for="confirmacao">Confirmar nova senha</label>
        <div class="field"><span class="ico"><i class="fas fa-shield-halved"></i></span>
            <input type="password" name="confirmacao" id="confirmacao" autocomplete="new-password" minlength="12" maxlength="<?= e(\App\Services\PasswordHasher::maximumLength()) ?>" required>
        </div>
    </div>
    <button class="btn-primary" type="submit">Guardar senha</button>
</form>
<?php if (!$mustChange): ?>
    <div class="auth-aux"><form method="POST" action="<?= e(url('sair')) ?>"><?= csrf_field() ?><button type="submit" class="admin-btn">Sair da conta</button></form></div>
<?php endif; ?>
