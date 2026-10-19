<?php
/** @var string $content */
/** @var string|null $title */
/** @var array|null $user */
/** @var array|null $igreja */

use App\Core\Subscription;

$title  = $title ?? 'Monana Eclesia';
$user   = $user ?? \App\Core\Auth::user();
$igreja = $igreja ?? null;

require_once VIEWS_PATH . '/partials/emblem.php';

// Garante que o banner está calculado (caso a view seja chamada fora do fluxo normal)
$banner = $GLOBALS['__subscription_banner'] ?? Subscription::bannerInfo();
?>
<!doctype html>
<html lang="pt-AO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?></title>

    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;charset=utf-8,<?= rawurlencode(emblem(64)) ?>">
    <link rel="alternate icon" type="image/png" href="<?= esc(asset('img/favicon.png')) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">

    <link rel="stylesheet" href="<?= esc(asset('css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= esc(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= esc(asset('css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= esc(asset('css/components.css')) ?>">
</head>
<body>
    <div class="app">
        <?php require VIEWS_PATH . '/partials/topbar.php'; ?>

        <?php if (!empty($banner)):
            $bannerCores = [
                'warning' => ['bg' => '#fef3c7', 'border' => '#f59e0b', 'text' => '#78350f'],
                'danger'  => ['bg' => '#fee2e2', 'border' => '#dc2626', 'text' => '#7f1d1d'],
            ];
            $cores = $bannerCores[$banner['tipo']] ?? $bannerCores['warning'];
        ?>
            <div class="subscription-banner" style="grid-column:1 / -1;background:<?= $cores['bg'] ?>;border-bottom:2px solid <?= $cores['border'] ?>;color:<?= $cores['text'] ?>;padding:10px 24px;display:flex;align-items:center;gap:12px;font-size:13px">
                <i class="fa-solid <?= esc($banner['icone']) ?>" aria-hidden="true" style="font-size:16px"></i>
                <div style="flex:1">
                    <strong><?= esc($banner['titulo']) ?></strong>
                    <span style="margin-left:8px"><?= esc($banner['msg']) ?></span>
                </div>
                <a href="<?= esc(url($banner['link'])) ?>" style="color:<?= $cores['text'] ?>;text-decoration:underline;font-weight:600;white-space:nowrap">
                    <?= esc($banner['linktxt']) ?>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true" style="font-size:10px"></i>
                </a>
            </div>
        <?php endif; ?>

        <?php require VIEWS_PATH . '/partials/sidebar.php'; ?>
        <div class="overlay" id="overlay"></div>
        <main class="main" id="main">
            <?= $content ?>
        </main>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const toggle  = document.getElementById('menu-toggle');
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