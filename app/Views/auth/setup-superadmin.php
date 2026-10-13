<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$errorMessage = (string) ($errorMessage ?? '');
$successMessage = (string) ($successMessage ?? '');
?>
<h1>Configurar plataforma</h1>
<p class="lead">Cria a primeira conta Superadmin. Esta página só fica disponível uma vez e requer a chave definida no ficheiro <code>.env</code>.</p>
<?php if ($successMessage !== ''): ?>
    <div class="success-box" role="status"><i class="fas fa-circle-check"></i><span><?= e($successMessage) ?></span></div>
    <div class="auth-aux"><a href="<?= e(url('login')) ?>">Ir para o login <i class="fas fa-arrow-right"></i></a></div>
<?php else: ?>
    <?php if ($errorMessage !== ''): ?>
        <div class="alert-danger" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= e($errorMessage) ?></span></div>
    <?php endif; ?>
    <form method="POST" action="<?= e(url('instalacao/superadmin')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <div class="field-group">
            <label class="field-label" for="nome">Nome completo</label>
            <div class="field"><span class="ico"><i class="far fa-user"></i></span>
                <input type="text" name="nome" id="nome" maxlength="180" autocomplete="name" required>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label" for="email">Email</label>
            <div class="field"><span class="ico"><i class="far fa-envelope"></i></span>
                <input type="email" name="email" id="email" maxlength="191" autocomplete="email" required>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label" for="senha">Senha (<?= e(\App\Services\PasswordHasher::requirementsDescription()) ?>)</label>
            <div class="field"><span class="ico"><i class="fas fa-lock"></i></span>
                <input type="password" name="senha" id="senha" minlength="12" maxlength="<?= e(\App\Services\PasswordHasher::maximumLength()) ?>" autocomplete="new-password" required>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label" for="confirmacao">Confirmar senha</label>
            <div class="field"><span class="ico"><i class="fas fa-shield-halved"></i></span>
                <input type="password" name="confirmacao" id="confirmacao" minlength="12" maxlength="<?= e(\App\Services\PasswordHasher::maximumLength()) ?>" autocomplete="new-password" required>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label" for="chave">Chave de configuração do .env</label>
            <div class="field"><span class="ico"><i class="fas fa-key"></i></span>
                <input type="password" name="chave" id="chave" minlength="32" maxlength="200" autocomplete="off" required>
            </div>
        </div>
        <button class="btn-primary" type="submit">Criar Superadmin</button>
    </form>
<?php endif; ?>
