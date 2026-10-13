<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$plans = is_array($plans ?? null) ? $plans : [];
$notice = (string) ($notice ?? '');
?>
<div class="page-head"><div><h2><i class="fas fa-layer-group"></i>Planos</h2><div class="sub">Configura os valores e limites comerciais decididos pela plataforma.</div></div><a class="admin-btn primary" href="<?= e(url('superadmin/planos/novo')) ?>"><i class="fas fa-plus"></i>Novo plano</a></div>
<?php if ($notice !== ''): ?><div class="success-box inline-notice" role="status"><i class="fas fa-circle-check"></i><span><?= e($notice) ?></span></div><?php endif; ?>
<div class="panel">
    <?php if ($plans === []): ?><div class="empty-note">Ainda não há planos cadastrados.</div>
    <?php else: ?>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Plano</th><th>Estado</th><th>Limite de membros</th><th>Limite de utilizadores</th><th>Preço</th><th>Igrejas</th><th>Acções</th></tr></thead><tbody>
        <?php foreach ($plans as $plan): ?>
            <tr><td><strong><?= e($plan['nome'] ?? '') ?></strong></td><td><span class="status-pill <?= (int) ($plan['ativo'] ?? 0) === 1 ? 'ok' : 'pending' ?>"><?= (int) ($plan['ativo'] ?? 0) === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                <td><?= (int) ($plan['limite_membros'] ?? 0) === 0 ? 'Não definido' : e((int) $plan['limite_membros']) ?></td>
                <td><?= (int) ($plan['limite_utilizadores'] ?? 0) === 0 ? 'Não definido' : e((int) $plan['limite_utilizadores']) ?></td>
                <td><?= e(number_format((float) ($plan['preco_kz'] ?? 0), 2, ',', '.')) ?> Kz</td><td><?= e((int) ($plan['igrejas_associadas'] ?? 0)) ?></td>
                <td><div class="admin-actions"><a class="admin-btn small" href="<?= e(url('superadmin/planos/' . (int) $plan['id'] . '/editar')) ?>"><i class="fas fa-pen"></i>Editar</a>
                    <?php if ((int) ($plan['igrejas_associadas'] ?? 0) === 0): ?><form method="POST" action="<?= e(url('superadmin/planos/' . (int) $plan['id'] . '/apagar')) ?>" onsubmit="return confirm('Apagar este plano?');"><?= csrf_field() ?><button class="admin-btn small danger" type="submit"><i class="fas fa-trash"></i>Apagar</button></form><?php endif; ?>
                </div></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
<p class="empty-note">Os preços e limites são dados comerciais configuráveis. A plataforma não presume nem aplica valores até serem definidos pelo Superadmin.</p>
