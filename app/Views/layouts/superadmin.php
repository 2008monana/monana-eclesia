<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
// Compatibilidade do nome da vista da Fase 3: partilha o shell comum da Fase 4.
$title = (string) ($title ?? 'Plataforma');
$active = (string) ($active ?? 'dashboard');
$actorName = (string) ($actorName ?? 'Superadmin');
$actorRole = 'superadmin';
$area = 'superadmin';
$tenantName = 'Plataforma MonanaEclésia';
include BASE_PATH . '/app/Views/layouts/app.php';
