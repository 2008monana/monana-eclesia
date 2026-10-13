<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$errorMessage = (string) ($errorMessage ?? '');
$successMessage = (string) ($successMessage ?? '');
$email = (string) ($email ?? '');
?>
<h1>Bem-vindo de volta!</h1>
<p class="lead">Entre na sua conta para continuar.</p>
<?php if ($successMessage !== ''): ?>
    <div class="success-box" role="status"><i class="fas fa-circle-check"></i><span><?= e($successMessage) ?></span></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert-danger" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= e($errorMessage) ?></span></div>
<?php endif; ?>
<form method="POST" action="<?= e(url('login')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <div class="field-group">
        <label class="field-label" for="loginEmail">Email</label>
        <div class="field"><span class="ico"><i class="far fa-envelope"></i></span>
            <input type="email" name="email" id="loginEmail" placeholder="Digite o seu email" autocomplete="username" value="<?= e($email) ?>" maxlength="191" required>
        </div>
    </div>
    <div class="field-group">
        <label class="field-label" for="loginPassword">Senha</label>
        <div class="field"><span class="ico"><i class="fas fa-lock"></i></span>
            <input type="password" name="senha" id="loginPassword" placeholder="Digite a sua senha" autocomplete="current-password" maxlength="200" required>
        </div>
    </div>
    <div class="auth-row"><span></span><a href="<?= e(url('recuperar-senha')) ?>">Esqueceu a senha?</a></div>
    <button class="btn-primary" type="submit">Entrar</button>
</form>
