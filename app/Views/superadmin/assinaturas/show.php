<?php
/** @var array $supervisor */
/** @var array $assinatura */
/** @var array $pagamentos */
/** @var array $notificacoes */

$statusLabel = [
    'trial' => 'Trial', 'activa' => 'Ativa', 'expirando' => 'A expirar',
    'expirada' => 'Expirada', 'em_tolerancia' => 'Em tolerância',
    'suspensa' => 'Suspensa', 'cancelada' => 'Cancelada',
];
$statusClass = [
    'activa' => 'ok', 'trial' => 'ok', 'expirando' => 'off',
    'expirada' => 'off', 'em_tolerancia' => 'off', 'suspensa' => 'off', 'cancelada' => 'off',
];
$tipoNotifLabel = [
    '48h_antes'  => 'Aviso 48h',
    'expirada'   => 'Expirada',
    'tolerancia' => 'Em tolerância',
    'suspensa'   => 'Suspensa',
    'reativada'  => 'Reativada',
];
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('superadmin/assinaturas')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title">Assinatura #<?= (int) $assinatura['id'] ?></h1>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form method="post" action="<?= esc(url('superadmin/assinaturas/' . $assinatura['id'] . '/renovar')) ?>" style="margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                Renovar
            </button>
        </form>

        <?php if ($assinatura['status'] === 'suspensa'): ?>
            <form method="post" action="<?= esc(url('superadmin/assinaturas/' . $assinatura['id'] . '/reativar')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline" style="color:var(--success)">
                    <i class="fa-solid fa-play" aria-hidden="true"></i> Reativar
                </button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= esc(url('superadmin/assinaturas/' . $assinatura['id'] . '/suspender')) ?>" style="margin:0" onsubmit="return confirm('Suspender esta assinatura?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline" style="color:var(--danger)">
                    <i class="fa-solid fa-pause" aria-hidden="true"></i> Suspender
                </button>
            </form>
        <?php endif; ?>

        <?php if ($assinatura['status'] !== 'cancelada'): ?>
            <form method="post" action="<?= esc(url('superadmin/assinaturas/' . $assinatura['id'] . '/cancelar')) ?>" style="margin:0" onsubmit="return confirm('Cancelar definitivamente?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline" style="color:var(--danger)">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancelar
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<section class="card" style="margin-bottom:16px;padding:22px 24px">
    <div style="display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap">
        <div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Igreja</div>
            <div style="font-size:18px;color:var(--brown-900);margin-top:4px">
                <a href="<?= esc(url('superadmin/igrejas/' . $assinatura['igreja_id'])) ?>"
                   style="color:inherit;text-decoration:none">
                    <i class="fa-solid fa-church" aria-hidden="true" style="color:var(--gold-600);margin-right:6px"></i>
                    <?= esc($assinatura['igreja_nome']) ?>
                </a>
            </div>
            <div class="muted" style="font-size:12px;margin-top:2px"><?= esc($assinatura['igreja_slug']) ?></div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Plano</div>
            <div style="font-size:18px;color:var(--brown-900);margin-top:4px"><?= esc($assinatura['plano_nome']) ?></div>
            <div class="muted" style="font-size:12px;margin-top:2px"><?= fmtKz($assinatura['preco_kz']) ?></div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Período</div>
            <div style="font-size:14px;color:var(--brown-900);margin-top:4px">
                <?= esc(date('d/m/Y', strtotime($assinatura['inicio']))) ?>
                → 
                <?= $assinatura['expiracao'] ? esc(date('d/m/Y', strtotime($assinatura['expiracao']))) : '—' ?>
            </div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Estado</div>
            <div style="margin-top:6px">
                <span class="status <?= $statusClass[$assinatura['status']] ?? 'off' ?>">
                    <?= esc($statusLabel[$assinatura['status']] ?? $assinatura['status']) ?>
                </span>
            </div>
        </div>
    </div>
</section>

<div class="grid row-2" style="gap:16px">
    <!-- Pagamentos -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-money-bill" aria-hidden="true"></i>
                Pagamentos (<?= count($pagamentos) ?>)
            </div>
            <a href="<?= esc(url('superadmin/pagamentos?assinatura=' . $assinatura['id'])) ?>"
               class="link-more" style="font-size:12px;color:var(--gold-700);text-decoration:none">
                Ver todos
            </a>
        </div>
        <?php if (empty($pagamentos)): ?>
            <p class="muted">Sem pagamentos.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Data</th><th>Valor</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($pagamentos, 0, 5) as $p):
                            $estadoClass = $p['estado'] === 'confirmado' ? 'ok' : 'off';
                        ?>
                            <tr>
                                <td class="muted" style="font-size:12px"><?= esc(date('d/m/Y', strtotime($p['criado_em']))) ?></td>
                                <td><strong><?= fmtKz($p['valor_kz']) ?></strong></td>
                                <td><span class="status <?= $estadoClass ?>"><?= esc(ucfirst($p['estado'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Notificações -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-bell" aria-hidden="true"></i>
                Notificações (<?= count($notificacoes) ?>)
            </div>
        </div>
        <?php if (empty($notificacoes)): ?>
            <p class="muted">Sem notificações.</p>
        <?php else: ?>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px;max-height:260px;overflow-y:auto">
                <?php foreach (array_slice($notificacoes, 0, 8) as $n): ?>
                    <li style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--gold-50);border-radius:8px;font-size:12.5px">
                        <i class="fa-solid fa-bell" aria-hidden="true" style="color:var(--gold-600)"></i>
                        <span style="flex:1"><?= esc($tipoNotifLabel[$n['tipo']] ?? $n['tipo']) ?></span>
                        <span class="muted"><?= esc(date('d/m/Y', strtotime($n['enviado_em']))) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>