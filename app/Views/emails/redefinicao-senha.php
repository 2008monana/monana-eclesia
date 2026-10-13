<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$name = (string) ($name ?? 'Irmão/irmã');
$resetLink = (string) ($resetLink ?? '{{LINK}}');
ob_start();
?>
<tr>
    <td style="padding:34px 36px 28px">
        <h1 style="margin:0 0 18px;font-family:Georgia,serif;font-size:30px;line-height:1.15;color:#2a1b12">Olá, <?= e($name) ?>.</h1>
        <p style="font-size:15px;line-height:1.7;color:#624f41">Recebemos um pedido para redefinir a senha da tua conta. Se foste tu, usa o botão abaixo para escolher uma nova senha.</p>
        <p style="margin:28px 0;text-align:center"><a href="<?= e($resetLink) ?>" style="display:inline-block;padding:14px 24px;border-radius:8px;background:linear-gradient(90deg,#e3b94f,#b08020);color:#2a1b12;font-weight:bold;text-decoration:none">Redefinir senha</a></p>
        <p style="font-size:13px;line-height:1.6;color:#7d6a5b">O link é de uso único e expira em 60 minutos. Se não pediste esta alteração, ignora a mensagem; a senha actual não será modificada.</p>
        <p style="font-size:12px;line-height:1.6;color:#7d6a5b;word-break:break-all">Se o botão não funcionar, copia este endereço:<br><?= e($resetLink) ?></p>
    </td>
</tr>
<?php
$content = (string) ob_get_clean();
$emailTitle = 'Redefinição de senha';
$previewText = 'Usa este link de uso único para redefinir a senha da tua conta MonanaEclésia.';
include BASE_PATH . '/app/Views/emails/layout.php';
