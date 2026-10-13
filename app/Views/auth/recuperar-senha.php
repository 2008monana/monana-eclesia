<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$successMessage = (string) ($successMessage ?? '');
$errorMessage = (string) ($errorMessage ?? '');
?>
<h1>Recuperar senha</h1>
<p class="lead">Indica o email associado à tua conta. Se estiver registado, receberás instruções para redefinir a senha.</p>
<?php if ($successMessage !== ''): ?>
    <div class="success-box" role="status"><i class="fas fa-circle-check"></i><span><?= e($successMessage) ?></span></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert-danger" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= e($errorMessage) ?></span></div>
<?php endif; ?>
<form method="POST" action="<?= e(url('recuperar-senha')) ?>">
    <?= csrf_field() ?>
    <div class="field-group">
        <label class="field-label" for="email">Email</label>
        <div class="field"><span class="ico"><i class="far fa-envelope"></i></span>
            <input type="email" name="email" id="email" placeholder="Digite o seu email" autocomplete="email" maxlength="191" required>
        </div>
    </div>
    <button class="btn-primary" type="submit">Enviar instruções</button>
</form>
<div class="auth-aux"><a href="<?= e(url('login')) ?>"><i class="fas fa-arrow-left"></i> Voltar ao login</a></div>
