<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$errorMessage = (string) ($errorMessage ?? '');
$successMessage = (string) ($successMessage ?? '');
$validToken = !empty($validToken);
$token = (string) ($token ?? '');
$churchName = (string) ($churchName ?? 'a tua igreja');
?>
<h1>Activar conta</h1>
<p class="lead">Define uma senha pessoal para concluir a activação da conta de administração de <?= e($churchName) ?>.</p>
<?php if ($successMessage !== ''): ?>
    <div class="success-box" role="status"><i class="fas fa-circle-check"></i><span><?= e($successMessage) ?></span></div>
    <div class="auth-aux"><a href="<?= e(url('login')) ?>">Ir para o login <i class="fas fa-arrow-right"></i></a></div>
<?php elseif ($validToken): ?>
    <?php if ($errorMessage !== ''): ?>
        <div class="alert-danger" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= e($errorMessage) ?></span></div>
    <?php endif; ?>
    <form method="POST" action="<?= e(url('activar-conta')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field-group">
            <label class="field-label" for="senha">Nova senha</label>
            <div class="field"><span class="ico"><i class="fas fa-lock"></i></span>
                <input type="password" name="senha" id="senha" autocomplete="new-password" minlength="12" maxlength="<?= e(\App\Services\PasswordHasher::maximumLength()) ?>" required>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label" for="confirmacao">Confirmar nova senha</label>
            <div class="field"><span class="ico"><i class="fas fa-shield-halved"></i></span>
                <input type="password" name="confirmacao" id="confirmacao" autocomplete="new-password" minlength="12" maxlength="<?= e(\App\Services\PasswordHasher::maximumLength()) ?>" required>
            </div>
        </div>
        <button class="btn-primary" type="submit">Activar e definir senha</button>
    </form>
<?php else: ?>
    <div class="alert-danger" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= e($errorMessage !== '' ? $errorMessage : 'Este link é inválido, expirou ou já foi utilizado. Contacta o Superadmin para receber outro.') ?></span></div>
<?php endif; ?>
