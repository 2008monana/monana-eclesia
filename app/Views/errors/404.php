<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
?>
<!doctype html>
<html lang="pt-AO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#c99a2e">
    <title>Página não encontrada — MonanaEclésia</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#fbf8f1;color:#3a271a;font-family:Inter,Arial,sans-serif}
        main{width:min(100%,560px);padding:42px 32px;text-align:center;background:#fff;border:1px solid #eee5d4;border-radius:18px;box-shadow:0 18px 48px rgba(58,39,26,.1)}
        .brand{color:#b08020;font-family:'Cormorant Garamond',Georgia,serif;font-size:19px;font-weight:700;letter-spacing:.08em}.code{margin:24px 0 8px;color:#c99a2e;font-family:'Cormorant Garamond',Georgia,serif;font-size:76px;line-height:1}
        h1{margin:0 0 12px;font-size:21px}p{margin:0 auto 24px;max-width:390px;color:#6b5b4d;line-height:1.6}
        a{display:inline-block;padding:11px 20px;border-radius:9px;background:linear-gradient(90deg,#e3b94f,#b08020);color:#2a1b12;font-weight:700;text-decoration:none}
    </style>
</head>
<body>
<main>
    <div class="brand">MONANA<span style="color:#3a271a">ECLÉSIA</span></div>
    <div class="code">404</div>
    <h1>Página não encontrada</h1>
    <p>O endereço pode ter mudado ou já não estar disponível.</p>
    <a href="<?= e(url('login')) ?>">Voltar ao início</a>
</main>
</body>
</html>
