<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#c99a2e">
    <title>Recuperar senha — MonanaEclésia</title>
    <link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= url('assets/img/favicon-32.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= url('assets/img/favicon-180.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style><?php readfile(dirname(__DIR__, 2) . '/assets/css/monana-theme.css'); ?></style>
</head>
<body>
    <div class="auth">
        <!-- Marca -->
        <aside class="auth-brand">
            <img class="logo" src="<?= url('assets/img/logo.png') ?>" alt="MonanaEclésia — Plataforma Integrada de Gestão de Igrejas">
            <p class="tagline">Organizar para servir melhor.</p>
            <div class="legal">
                &copy; 2026 Igreja Pentecostal Fonte da Vida Angola<br>
                Calemba 2 &middot; Todos os direitos reservados.
            </div>
        </aside>

        <main class="auth-panel">
            <div class="auth-card">
                <img class="mark-sm" src="<?= url('assets/img/logo.png') ?>" alt="MonanaEclésia">
                <h1>Recuperar senha</h1>
                <p class="lead">Insira o seu email e enviaremos um link para redefinir a sua senha.</p>

                <?php if ($error): ?>
                <div class="alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error) ?></span></div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="success-box"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
                <?php else: ?>
                <form method="POST" action="">
                    <div class="field-group">
                        <label class="field-label" for="email">Email</label>
                        <div class="field">
                            <span class="ico"><i class="far fa-envelope"></i></span>
                            <input type="email" name="email" id="email" placeholder="Digite o seu email" required>
                        </div>
                    </div>
                    <button class="btn-primary" type="submit">Enviar link</button>
                </form>
                <?php endif; ?>

                <div class="auth-aux">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Voltar ao login</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
