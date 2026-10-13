<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$churches = is_array($churches ?? null) ? $churches : [];
$notice = (string) ($notice ?? '');
?>
<div class="page-head"><div><h2><i class="fas fa-church"></i>Igrejas</h2><div class="sub">Provisiona e gere o acesso das igrejas à plataforma.</div></div><a class="admin-btn primary" href="<?= e(url('superadmin/igrejas/nova')) ?>"><i class="fas fa-plus"></i>Criar igreja</a></div>
<?php if ($notice !== ''): ?><div class="success-box inline-notice" role="status"><i class="fas fa-circle-check"></i><span><?= e($notice) ?></span></div><?php endif; ?>
<div class="panel">
    <?php if ($churches === []): ?>
        <div class="empty-note">Ainda não foi criada nenhuma igreja.</div>
    <?php else: ?>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Igreja</th><th>Email admin</th><th>Plano</th><th>Estado</th><th>Utilizadores</th><th>Email de activação</th><th>Criada em</th><th>Acções</th></tr></thead><tbody>
        <?php foreach ($churches as $church): ?>
            <?php $state = (string) ($church['estado'] ?? ''); $stateClass = $state === 'activa' ? 'ok' : (in_array($state, ['suspensa', 'encerrada'], true) ? 'bad' : 'pending'); $emailState = (string) ($church['email_estado'] ?? 'sem registo'); $emailClass = $emailState === 'enviado' ? 'ok' : ($emailState === 'falhou' ? 'bad' : 'pending'); ?>
            <tr>
                <td><strong><?= e($church['nome'] ?? '') ?></strong><br><span style="font-size:11px;color:var(--brown-400)"><?= e($church['cidade'] ?? '') ?> · <?= e($church['slug'] ?? '') ?></span></td>
                <td><?= e($church['email_admin'] ?? '') ?></td>
                <td><?= e($church['plano_nome'] ?? '') ?></td>
                <td><span class="status-pill <?= e($stateClass) ?>"><?= e($state) ?></span></td>
                <td><?= e((int) ($church['total_utilizadores'] ?? 0)) ?></td>
                <td><span class="status-pill <?= e($emailClass) ?>"><?= e($emailState) ?></span></td>
                <td><?= e(isset($church['criada_em']) ? date('d/m/Y', strtotime((string) $church['criada_em'])) : '') ?></td>
                <td><a class="admin-btn small" href="<?= e(url('superadmin/igrejas/' . (int) $church['id'] . '/editar')) ?>"><i class="fas fa-gear"></i>Gerir</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
