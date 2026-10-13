<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
if (!isset($footerText) || !is_string($footerText) || trim($footerText) === '') {
    $footerText = $area === 'superadmin'
        ? 'MonanaEclésia · Plataforma integrada de gestão de igrejas · Lei n.º 22/11 de Angola'
        : 'MonanaEclésia · ' . $tenantName . ' · Protecção de dados pessoais — Lei n.º 22/11 de Angola';
}
?>
<footer class="footer-note"><?= e($footerText) ?></footer>
