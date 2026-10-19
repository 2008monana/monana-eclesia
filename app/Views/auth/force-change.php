<div class="login">
    <section class="login-art">
        <div class="content">
            <div class="display brand-title">MONANA <span style="color:var(--gold-600)">ECLESIA</span></div>
            <div class="divider">◆</div>
            <div class="quote">Bem-vindo(a)!<br>Por segurança,<br>defina a sua senha.</div>
        </div>
    </section>

    <section class="login-form-wrap">
        <form class="login-form" method="post" action="<?= esc(url('force-change')) ?>">
            <?= csrf_field() ?>
            <h1>Definir palavra-passe</h1>
            <p class="sub">Esta é a sua primeira entrada.<br>Escolha uma palavra-passe definitiva.</p>

            <?php if ($err = get_flash('error')): ?>
                <div class="alert alert-error"><?= esc($err) ?></div>
            <?php endif; ?>

            <label class="lb" for="pass">Nova palavra-passe</label>
            <div class="field">
                <input id="pass" name="password" class="input" type="password" required minlength="8" autofocus>
            </div>

            <label class="lb" for="conf" style="margin-top:22px">Confirmar</label>
            <div class="field">
                <input id="conf" name="password_confirm" class="input" type="password" required minlength="8">
            </div>

            <button class="btn btn-gold btn-lg" type="submit" style="margin-top:24px">Guardar e entrar</button>
        </form>
    </section>
</div>