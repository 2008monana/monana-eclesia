<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$counts = is_array($counts ?? null) ? $counts : [];
$churches = is_array($churches ?? null) ? $churches : [];
$emails = is_array($emails ?? null) ? $emails : [];
$plans = is_array($plans ?? null) ? $plans : [];
?>
<div class="page-head">
    <div><h2><i class="fas fa-chart-line"></i>Plataforma</h2><div class="sub">Visão geral da MonanaEclésia e estado dos provisionamentos.</div></div>
    <a class="admin-btn primary" href="<?= e(url('superadmin/igrejas/nova')) ?>"><i class="fas fa-plus"></i>Criar igreja</a>
</div>
<div class="grid-stats">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-church"></i></div><div class="stat-num"><?= e((int) ($counts['total'] ?? 0)) ?></div><div class="stat-label">Igrejas registadas</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-circle-check"></i></div><div class="stat-num"><?= e((int) ($counts['activas'] ?? 0)) ?></div><div class="stat-label">Igrejas activas</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-hourglass-half"></i></div><div class="stat-num"><?= e((int) ($counts['pendentes'] ?? 0)) ?></div><div class="stat-label">A aguardar activação</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-ban"></i></div><div class="stat-num"><?= e((int) ($counts['suspensas'] ?? 0)) ?></div><div class="stat-label">Suspensas</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-box-archive"></i></div><div class="stat-num"><?= e((int) ($counts['encerradas'] ?? 0)) ?></div><div class="stat-label">Encerradas</div></div>
</div>
<div class="grid-lower" style="grid-template-columns:1.5fr 1fr;align-items:start">
    <div class="panel">
        <div class="panel-head"><h3><i class="fas fa-church"></i>Igrejas recentes</h3><a class="admin-btn small" href="<?= e(url('superadmin/igrejas')) ?>">Ver todas</a></div>
        <?php if ($churches === []): ?>
            <div class="empty-note">Ainda não há igrejas. Cria a primeira quando os planos estiverem configurados.</div>
        <?php else: ?>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Igreja</th><th>Plano</th><th>Estado</th><th>Email</th><th></th></tr></thead><tbody>
            <?php foreach ($churches as $church): ?>
                <?php $state = (string) ($church['estado'] ?? ''); $stateClass = $state === 'activa' ? 'ok' : (in_array($state, ['suspensa', 'encerrada'], true) ? 'bad' : 'pending'); ?>
                <tr><td><strong><?= e($church['nome'] ?? '') ?></strong><br><span style="font-size:11px;color:var(--brown-400)"><?= e($church['cidade'] ?? '') ?></span></td>
                    <td><?= e($church['plano_nome'] ?? '') ?></td><td><span class="status-pill <?= e($stateClass) ?>"><?= e($state) ?></span></td>
                    <td><?= e($church['email_estado'] ?? 'sem registo') ?></td><td><a href="<?= e(url('superadmin/igrejas/' . (int) $church['id'] . '/editar')) ?>">Gerir</a></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
    <div class="panel">
        <div class="panel-head"><h3><i class="fas fa-envelope"></i>Fila de emails</h3></div>
        <?php if ($emails === []): ?>
            <div class="empty-note">A fila está vazia.</div>
        <?php else: ?>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Email</th><th>Estado</th></tr></thead><tbody>
            <?php foreach ($emails as $email): ?>
                <?php $state = (string) ($email['estado'] ?? ''); $stateClass = $state === 'enviado' ? 'ok' : ($state === 'falhou' ? 'bad' : 'pending'); ?>
                <tr><td><strong><?= e($email['assunto'] ?? '') ?></strong><br><span style="font-size:11px;color:var(--brown-400)"><?= e($email['destinatario'] ?? '') ?></span></td>
                    <td><span class="status-pill <?= e($stateClass) ?>"><?= e($state) ?></span><?php if (!empty($email['igreja_id'])): ?><br><a href="<?= e(url('superadmin/igrejas/' . (int) $email['igreja_id'] . '/editar')) ?>">Igreja #<?= e((int) $email['igreja_id']) ?></a><?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</div>
<div class="panel">
    <div class="panel-head"><h3><i class="fas fa-layer-group"></i>Planos configurados</h3><a class="admin-btn small" href="<?= e(url('superadmin/planos')) ?>">Gerir planos</a></div>
    <?php if ($plans === []): ?><div class="empty-note">Sem planos. Cria e configura os valores comerciais antes de provisionar igrejas.</div>
    <?php else: ?>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Plano</th><th>Estado</th><th>Limite de membros</th><th>Limite de utilizadores</th><th>Preço</th><th>Igrejas</th></tr></thead><tbody>
        <?php foreach ($plans as $plan): ?>
            <tr><td><?= e($plan['nome'] ?? '') ?></td><td><span class="status-pill <?= (int) ($plan['ativo'] ?? 0) === 1 ? 'ok' : 'pending' ?>"><?= (int) ($plan['ativo'] ?? 0) === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                <td><?= (int) ($plan['limite_membros'] ?? 0) === 0 ? 'Não definido' : e((int) $plan['limite_membros']) ?></td>
                <td><?= (int) ($plan['limite_utilizadores'] ?? 0) === 0 ? 'Não definido' : e((int) $plan['limite_utilizadores']) ?></td>
                <td><?= e(number_format((float) ($plan['preco_kz'] ?? 0), 2, ',', '.')) ?> Kz</td><td><?= e((int) ($plan['igrejas_associadas'] ?? 0)) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
