<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
?>
<header class="pdf-header">
    <table class="pdf-brand" role="presentation">
        <tr>
            <?php if ($logoDataUri !== ''): ?>
                <td class="pdf-logo-cell"><img class="pdf-logo" src="<?= e($logoDataUri) ?>" alt=""></td>
            <?php endif; ?>
            <td>
                <div class="pdf-church"><?= e($churchName) ?></div>
                <?php if ($churchShortName !== ''): ?>
                    <div class="pdf-church-short"><?= e($churchShortName) ?></div>
                <?php endif; ?>
            </td>
            <td class="pdf-platform">MONANA<span>ECLÉSIA</span></td>
        </tr>
    </table>
</header>
