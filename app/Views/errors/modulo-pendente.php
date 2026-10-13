<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
?>
<!doctype html>
<html lang="pt-AO"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Módulo em migração — MonanaEclésia</title>
<style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#fbf8f1;color:#3a271a;font-family:Inter,Arial,sans-serif}main{width:min(100%,620px);padding:42px 34px;background:#fff;border:1px solid #ece4d2;border-radius:18px;box-shadow:0 18px 48px rgba(58,39,26,.1)}.brand{color:#b08020;font-family:Georgia,serif;font-size:19px;font-weight:700;letter-spacing:.08em}h1{margin:26px 0 12px;font-size:24px}p{margin:0 0 22px;color:#6b5b4d;line-height:1.7}.notice{padding:14px 16px;border-left:3px solid #c99a2e;background:#fcf8ec;line-height:1.6;font-size:13px}a{display:inline-block;margin-top:24px;padding:11px 18px;border-radius:9px;background:linear-gradient(90deg,#e3b94f,#b08020);color:#2a1b12;font-weight:700;text-decoration:none}</style></head>
<body><main><div class="brand">MONANA<span style="color:#3a271a">ECLÉSIA</span></div><h1>Este módulo está em migração</h1><p>Para proteger os dados de cada igreja, esta funcionalidade só será reactivada quando estiver ligada ao tenant autenticado.</p><div class="notice">Os módulos antigos ainda não aplicam o isolamento multi-igreja. Nenhuma consulta legada será executada nesta conta. A migração funcional está prevista na Fase 5.</div><a href="<?= e(url('painel')) ?>">Voltar ao painel</a></main></body></html>
