<div class="login">
    <section class="login-art">
        <div class="content">
            <div class="display brand-title">
                MONANA <span style="color:var(--gold-600)">ECLESIA</span>
            </div>
            <div class="divider">◆</div>
            <div class="quote">“Pedi e dar-se-vos-á.”</div>
        </div>
    </section>

    <section class="login-form-wrap">
        <form class="login-form" method="post" action="<?= esc(url('forgot')) ?>">
            <?= csrf_field() ?>
            <h1>Recuperar acesso</h1>
            <p class="sub">Indique o seu e-mail e enviaremos<br>um link para redefinir a palavra-passe.</p>

            <?php if ($ok = get_flash('success')): ?>
                <div class="alert alert-success"><?= esc($ok) ?></div>
            <?php endif; ?>
            <?php if ($err = get_flash('error')): ?>
                <div class="alert alert-error"><?= esc($err) ?></div>
            <?php endif; ?>

            <label class="lb" for="email">E-mail</label>
            <div class="field">
                <input id="email" name="email" class="input" type="email" placeholder="seu@email.com" required autofocus>
            </div>

            <button class="btn btn-gold btn-lg" type="submit" style="margin-top:24px">Enviar link</button>

            <p style="text-align:center;margin-top:24px;font-size:12.5px;color:var(--text-muted)">
                <a href="<?= esc(url('login')) ?>">← Voltar ao login</a>
            </p>
        </form>
    </section>
</div>