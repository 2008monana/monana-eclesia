<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$footerChurch = $churchShortName !== '' ? $churchShortName : $churchName;
?>
<footer class="pdf-footer">
    MonanaEclésia &middot; <?= e($footerChurch) ?> &middot; Emitido em <?= e($generatedAt) ?>
</footer>
