<?php /** @var string $token */ ?>
<div class="login">
    <section class="login-art">
        <div class="content">
            <div class="display brand-title">MONANA <span style="color:var(--gold-600)">ECLESIA</span></div>
            <div class="divider">◆</div>
            <div class="quote">Defina uma nova<br>palavra-passe.</div>
        </div>
    </section>

    <section class="login-form-wrap">
        <form class="login-form" method="post" action="<?= esc(url('reset')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= esc($token) ?>">
            <h1>Nova palavra-passe</h1>
            <p class="sub">Escolha uma palavra-passe forte com<br>pelo menos 8 caracteres.</p>

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

            <button class="btn btn-gold btn-lg" type="submit" style="margin-top:24px">Guardar palavra-passe</button>
        </form>
    </section>
</div>