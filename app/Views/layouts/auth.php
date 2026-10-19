<?php
/** @var string $content */
/** @var string|null $title */
$title = $title ?? 'Monana Eclesia';

// Importa o helper emblem()
require_once VIEWS_PATH . '/partials/emblem.php';

// Favicon SVG inline (data URI)
$faviconSvg = rawurlencode(emblem(64));
?>
<!doctype html>
<html lang="pt-AO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?></title>

    <!-- Favicon: SVG inline (o mesmo emblema do protótipo) -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;charset=utf-8,<?= $faviconSvg ?>">
    <!-- Fallback PNG (opcional — se existir public/img/favicon.png) -->
    <link rel="alternate icon" type="image/png" href="<?= esc(asset('img/favicon.png')) ?>">

    <!-- Fontes do protótipo -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= esc(asset('css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= esc(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= esc(asset('css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= esc(asset('css/components.css')) ?>">
</head>
<body>
    <?= $content ?>
</body>
</html>