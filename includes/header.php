<?php
// includes/header.php
// <head> partilhado por todas as páginas internas. O visual vive em assets/css/monana-theme.css
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#c99a2e">
    <title><?= $page_title ?? 'MonanaEclésia — I.P.F.V.A Calemba 2' ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= url('assets/img/favicon-32.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= url('assets/img/favicon-180.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style><?php readfile(dirname(__DIR__, 1) . '/assets/css/monana-theme.css'); ?></style>
</head>
<body>
    <div class="app">
        <!-- Overlay para mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>