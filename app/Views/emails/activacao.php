<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$name = (string) ($name ?? 'Irmão/irmã');
$churchName = (string) ($churchName ?? 'a tua igreja');
$activationLink = (string) ($activationLink ?? '{{LINK}}');
ob_start();
?>
<tr>
    <td style="padding:34px 36px 28px">
        <p style="margin:0 0 8px;color:#8a6317;font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase">A tua conta está quase pronta</p>
        <h1 style="margin:0 0 18px;font-family:Georgia,serif;font-size:30px;line-height:1.15;color:#2a1b12">Bem-vindo(a), <?= e($name) ?>!</h1>
        <p style="font-size:15px;line-height:1.7;color:#624f41">A conta de administração de <strong><?= e($churchName) ?></strong> foi criada. Para confirmar o teu email e definir uma senha pessoal, usa o botão abaixo.</p>
        <p style="margin:28px 0;text-align:center"><a href="<?= e($activationLink) ?>" style="display:inline-block;padding:14px 24px;border-radius:8px;background:linear-gradient(90deg,#e3b94f,#b08020);color:#2a1b12;font-weight:bold;text-decoration:none">Activar conta e definir senha</a></p>
        <p style="font-size:13px;line-height:1.6;color:#7d6a5b">Este link é de uso único e expira em 24 horas. Se não reconheces este pedido, ignora esta mensagem.</p>
        <p style="font-size:12px;line-height:1.6;color:#7d6a5b;word-break:break-all">Se o botão não funcionar, copia este endereço:<br><?= e($activationLink) ?></p>
    </td>
</tr>
<?php
$content = (string) ob_get_clean();
$emailTitle = 'Activação da conta';
$previewText = 'Confirma o teu email e define uma senha pessoal para concluir a activação da conta.';
include BASE_PATH . '/app/Views/emails/layout.php';
