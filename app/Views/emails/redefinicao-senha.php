<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$name = (string) ($name ?? 'Irmão/irmã');
$resetLink = (string) ($resetLink ?? '{{LINK}}');
?>
<!doctype html>
<html lang="pt-AO">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Redefinição de senha — MonanaEclésia</title></head>
<body style="margin:0;padding:28px 12px;background:#fbf8f1;color:#3a271a;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #ece4d2;border-radius:14px;overflow:hidden">
    <tr><td style="padding:28px 32px;background:#2a1b12;color:#fbf8f1;text-align:center">
        <div style="font-family:Georgia,serif;font-size:23px;font-weight:bold;letter-spacing:2px;color:#e3b94f">MONANA<span style="color:#fbf8f1">ECLÉSIA</span></div>
        <div style="margin-top:5px;font-size:12px;color:#eee4d0">Plataforma Integrada de Gestão de Igrejas</div>
    </td></tr>
    <tr><td style="padding:34px 36px 28px">
        <h1 style="margin:0 0 18px;font-family:Georgia,serif;font-size:30px;line-height:1.15;color:#2a1b12">Olá, <?= e($name) ?>.</h1>
        <p style="font-size:15px;line-height:1.7;color:#624f41">Recebemos um pedido para redefinir a senha da tua conta. Se foste tu, usa o botão abaixo para escolher uma nova senha.</p>
        <p style="margin:28px 0;text-align:center"><a href="<?= e($resetLink) ?>" style="display:inline-block;padding:14px 24px;border-radius:8px;background:linear-gradient(90deg,#e3b94f,#b08020);color:#2a1b12;font-weight:bold;text-decoration:none">Redefinir senha</a></p>
        <p style="font-size:13px;line-height:1.6;color:#7d6a5b">O link é de uso único e expira em 60 minutos. Se não pediste esta alteração, ignora a mensagem; a senha actual não será modificada.</p>
        <p style="font-size:12px;line-height:1.6;color:#7d6a5b;word-break:break-all">Se o botão não funcionar, copia este endereço:<br><?= e($resetLink) ?></p>
    </td></tr>
    <tr><td style="padding:18px 30px;border-top:1px solid #ece4d2;text-align:center;color:#a39485;font-size:11px">Organizar para servir melhor. &middot; MonanaEclésia</td></tr>
</table>
</body>
</html>
