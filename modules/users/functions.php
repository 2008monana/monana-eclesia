<?php
// modules/users/functions.php

function getPerfilLabel($perfil) {
    $labels = [
        'admin' => ['label' => 'Administrador', 'color' => '#b5412f', 'icon' => 'fa-crown'],
        'gestor' => ['label' => 'Gestor', 'color' => '#b9770e', 'icon' => 'fa-user-cog']
    ];
    return $labels[$perfil] ?? ['label' => ucfirst($perfil), 'color' => '#gray-500', 'icon' => 'fa-user'];
}

function getStatusBadge($ativo) {
    if ($ativo) {
        return '<span style="display:inline-block;padding:2px 12px;border-radius:20px;background:#eaf3ec;color:#3f7d4e;font-size:11px;font-weight:600;">
                    <i class="fas fa-circle" style="font-size:6px;margin-right:4px;"></i> Ativo
                </span>';
    } else {
        return '<span style="display:inline-block;padding:2px 12px;border-radius:20px;background:#f9e9e5;color:#b5412f;font-size:11px;font-weight:600;">
                    <i class="fas fa-circle" style="font-size:6px;margin-right:4px;"></i> Inativo
                </span>';
    }
}
?>