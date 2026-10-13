<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$errorMessage = (string) ($errorMessage ?? '');
$successMessage = (string) ($successMessage ?? '');
$validToken = !empty($validToken);
$token = (string) ($token ?? '');
?>
<h1>Definir nova senha</h1>
<p class="lead">Escolhe uma senha pessoal para proteger a tua conta.</p>
<?php if ($successMessage !== ''): ?>
    <div class="success-box" role="status"><i class="fas fa-circle-check"></i><span><?= e($successMessage) ?></span></div>
    <div class="auth-aux"><a href="<?= e(url('login')) ?>">Ir para o login <i class="fas fa-arrow-right"></i></a></div>
<?php elseif ($validToken): ?>
    <?php if ($errorMessage !== ''): ?>
        <div class="alert-danger" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= e($errorMessage) ?></span></div>
    <?php endif; ?>
    <form method="POST" action="<?= e(url('resetar-senha')) ?>">
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
        <p class="lead" style="margin:0 0 20px">Usa <?= e(\App\Services\PasswordHasher::requirementsDescription()) ?>.</p>
        <button class="btn-primary" type="submit">Guardar nova senha</button>
    </form>
<?php else: ?>
    <div class="alert-danger" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= e($errorMessage !== '' ? $errorMessage : 'Este link é inválido, expirou ou já foi utilizado.') ?></span></div>
    <div class="auth-aux"><a href="<?= e(url('recuperar-senha')) ?>">Pedir um novo link <i class="fas fa-arrow-right"></i></a></div>
<?php endif; ?>
