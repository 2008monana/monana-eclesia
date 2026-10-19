<?php
/** @var array $user */
/** @var array $notificacoes */

$tipoLabel = [
    '48h_antes'  => 'Aviso 48h',
    'expirada'   => 'Expirada',
    'tolerancia' => 'Em tolerância',
    'suspensa'   => 'Suspensa',
    'reativada'  => 'Reativada',
];
$tipoIcone = [
    '48h_antes'  => 'fa-hourglass-half',
    'expirada'   => 'fa-ban',
    'tolerancia' => 'fa-triangle-exclamation',
    'suspensa'   => 'fa-circle-pause',
    'reativada'  => 'fa-circle-check',
];
$tipoCor = [
    '48h_antes'  => 'var(--gold-700)',
    'expirada'   => 'var(--danger)',
    'tolerancia' => 'var(--gold-700)',
    'suspensa'   => 'var(--danger)',
    'reativada'  => 'var(--success)',
];
$statusClass = [
    'enviado' => 'ok',
    'falhou'  => 'off',
    'lido'    => 'ok',
];
?>
<div class="page-head">
    <h1 class="page-title">Notificações de Assinatura</h1>
    <span class="muted" style="font-size:13px"><?= count($notificacoes) ?> registo(s)</span>
</div>

<div class="card">
    <?php if (empty($notificacoes)): ?>
        <div style="text-align:center;padding:40px">
            <i class="fa-solid fa-bell-slash" aria-hidden="true" style="font-size:48px;color:var(--gold-300)"></i>
            <p class="muted" style="margin:12px 0 0">Nenhuma notificação enviada ainda.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Canal</th>
                        <th>Status</th>
                        <th>Enviado em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notificacoes as $n): ?>
                        <tr>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:8px;font-weight:500;color:<?= $tipoCor[$n['tipo']] ?? 'var(--brown-900)' ?>">
                                    <i class="fa-solid <?= $tipoIcone[$n['tipo']] ?? 'fa-bell' ?>" aria-hidden="true"></i>
                                    <?= esc($tipoLabel[$n['tipo']] ?? $n['tipo']) ?>
                                </span>
                            </td>
                            <td class="muted"><?= esc(ucfirst($n['canal'])) ?></td>
                            <td>
                                <span class="status <?= $statusClass[$n['status']] ?? 'off' ?>">
                                    <?= esc(ucfirst($n['status'])) ?>
                                </span>
                            </td>
                            <td class="muted" style="font-size:12.5px">
                                <?= esc(date('d/m/Y H:i', strtotime($n['enviado_em']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>