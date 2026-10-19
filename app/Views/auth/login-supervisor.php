<?php
/** @var string $title */
require_once VIEWS_PATH . '/partials/emblem.php';
?>
<div class="login">
    <section class="login-art">
        <div class="content">
            <div class="emblem">
                <?= emblem(190) ?>
            </div>
            <div class="display brand-title">
                MONANA <span style="color:var(--gold-600)">ECLESIA</span>
            </div>
            <div class="tag-line">PAINEL DA PLATAFORMA<br>SUPERADMIN</div>
            <div class="divider">◆</div>
            <div class="quote">Servir com integridade<br>e visão.</div>
        </div>

        <svg class="hills" viewBox="0 0 400 200" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 120 C80 90 160 110 240 95 S360 80 400 90 V200 H0 Z" fill="#ecd29a" opacity=".55" />
            <path d="M0 150 C100 120 200 150 300 130 S380 125 400 128 V200 H0 Z" fill="#e2bc6f" opacity=".55" />
            <path d="M0 175 C120 160 240 185 400 165 V200 H0 Z" fill="#d7aa52" opacity=".5" />
            <g fill="#c9a058" opacity=".55" transform="translate(300 52)">
                <rect x="18" y="0" width="3" height="16" />
                <rect x="13" y="5" width="13" height="3" />
                <path d="M8 40 L19.5 16 L31 40 Z" />
                <rect x="10" y="40" width="19" height="40" />
                <path d="M-30 58 L-8 44 L14 58 Z" />
                <rect x="-26" y="58" width="36" height="22" />
            </g>
        </svg>
    </section>

    <section class="login-form-wrap">
        <form class="login-form" method="post" action="<?= esc(url('superadmin/login')) ?>">
            <?= csrf_field() ?>
            <h1>Superadmin</h1>
            <p class="sub">Acesso restrito à equipa de<br>operação da plataforma.</p>

            <?php if ($err = get_flash('error')): ?>
                <div class="alert alert-error"><?= esc($err) ?></div>
            <?php endif; ?>
            <?php if ($ok = get_flash('success')): ?>
                <div class="alert alert-success"><?= esc($ok) ?></div>
            <?php endif; ?>

            <label class="lb" for="email">E-mail</label>
            <div class="field">
                <input id="email" name="email" class="input" type="email" placeholder="superadmin@monana.app" required autofocus value="<?= esc(old('email')) ?>">
            </div>

            <label class="lb" for="pass" style="margin-top:22px">Palavra-passe</label>
            <div class="field">
                <input id="pass" name="password" class="input" type="password" placeholder="Digite a sua palavra-passe" required>
            </div>

            <button class="btn btn-gold btn-lg" type="submit" style="margin-top:24px">Entrar</button>
        </form>
    </section>
</div>