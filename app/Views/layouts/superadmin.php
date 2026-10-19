<?php
/** @var string $content */
/** @var string|null $title */
/** @var array|null $supervisor */

use App\Core\Auth;

$title      = $title ?? 'Superadmin — Monana Eclesia';
$supervisor = $supervisor ?? Auth::supervisor();

require_once VIEWS_PATH . '/partials/emblem.php';
?>
<!doctype html>
<html lang="pt-AO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?></title>

    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;charset=utf-8,<?= rawurlencode(emblem(64)) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">

    <link rel="stylesheet" href="<?= esc(asset('css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= esc(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= esc(asset('css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= esc(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= esc(asset('css/superadmin.css')) ?>">
</head>
<body class="sa-body">
    <div class="sa-app">
        <?php require VIEWS_PATH . '/partials/topbar-superadmin.php'; ?>
        <?php require VIEWS_PATH . '/partials/sidebar-superadmin.php'; ?>
        <div class="sa-overlay" id="overlay"></div>
        <main class="sa-main" id="main">
            <?= $content ?>
        </main>
    </div>

    <script>
        const sidebar = document.getElementById('sa-sidebar');
        const overlay = document.getElementById('overlay');
        const toggle  = document.getElementById('sa-menu-toggle');
        if (toggle) toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        });
        if (overlay) overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });
    </script>
</body>
</html>