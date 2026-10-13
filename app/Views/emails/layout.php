<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$emailTitle = (string) ($emailTitle ?? 'Mensagem MonanaEclésia');
$previewText = (string) ($previewText ?? 'Mensagem da plataforma MonanaEclésia.');
$content = (string) ($content ?? '');
?>
<!doctype html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($emailTitle) ?> — MonanaEclésia</title>
</head>
<body style="margin:0;padding:28px 12px;background:#fbf8f1;color:#3a271a;font-family:Arial,Helvetica,sans-serif">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent"><?= e($previewText) ?></div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #ece4d2;border-radius:14px;overflow:hidden">
    <tr>
        <td style="padding:28px 32px;background:#2a1b12;color:#fbf8f1;text-align:center">
            <div style="font-family:Georgia,serif;font-size:23px;font-weight:bold;letter-spacing:2px;color:#e3b94f">MONANA<span style="color:#fbf8f1">ECLÉSIA</span></div>
            <div style="margin-top:5px;font-size:12px;color:#eee4d0">Plataforma Integrada de Gestão de Igrejas</div>
        </td>
    </tr>
    <?= $content ?>
    <tr>
        <td style="padding:18px 30px;border-top:1px solid #ece4d2;text-align:center;color:#a39485;font-size:11px">Organizar para servir melhor. &middot; MonanaEclésia</td>
    </tr>
</table>
</body>
</html>
