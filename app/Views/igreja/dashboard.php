<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$churchName = (string) ($churchName ?? 'A tua igreja');
$metrics = is_array($metricas ?? null) ? $metricas : [];
$actions = is_array($atalhos ?? null) ? $atalhos : [];
$activity = is_array($actividade ?? null) ? $actividade : [];
$enabledLabels = is_array($activadosLabels ?? null) ? $activadosLabels : [];
?>
<div class="page-head"><div><h2><i class="fas fa-church" aria-hidden="true"></i>Bem-vindo(a), <?= e($churchName) ?></h2><div class="sub">Painel de gestão da igreja · dados sempre limitados à sessão autenticada.</div></div></div>
<?php if ($metrics !== []): ?><div class="grid-stats" style="margin-bottom:20px"><?php foreach ($metrics as $metric): ?><div class="stat-card"><div class="stat-icon"><i class="fas <?= e($metric['icone'] ?? 'fa-chart-line') ?>" aria-hidden="true"></i></div><div class="stat-num"><?= e($metric['valor'] ?? '—') ?></div><div class="stat-label"><?= e($metric['label'] ?? '') ?></div></div><?php endforeach; ?></div><?php endif; ?>
<div class="panel" style="margin-bottom:20px"><h3><i class="fas fa-shield-halved" aria-hidden="true"></i>Área segura da igreja</h3>
    <?php if (empty($hasMigratedModules)): ?><div class="phase-notice" role="status"><strong>Os módulos continuam fechados até à migração tenant da Fase 6.</strong><br>O painel não lê dados legados. Os dados só serão consultados quando a igreja confirmar o esquema real, as colunas `igreja_id` e os testes de isolamento; nenhum tenant é aceite por formulário ou query string.</div>
    <?php else: ?><div class="inline-notice" role="status">Módulos disponíveis nesta sessão: <?= e(implode(', ', $enabledLabels)) ?>. Os restantes continuam fechados até à validação segura.</div><?php endif; ?>
    <?php if ($actions !== []): ?><div class="dashboard-actions admin-actions"><?php foreach ($actions as $action): ?><a class="admin-btn" href="<?= e(url($action['url'] ?? 'painel')) ?>"><i class="fas <?= e($action['icone'] ?? 'fa-arrow-right') ?>" aria-hidden="true"></i><?= e($action['label'] ?? 'Abrir') ?></a><?php endforeach; ?><a class="admin-btn" href="<?= e(url('trocar-senha')) ?>"><i class="fas fa-key" aria-hidden="true"></i>Alterar senha</a></div>
    <?php else: ?><div class="admin-actions dashboard-actions"><a class="admin-btn" href="<?= e(url('trocar-senha')) ?>"><i class="fas fa-key" aria-hidden="true"></i>Alterar senha</a><span class="status-pill ok">Conta autenticada</span></div><?php endif; ?>
</div>
<?php if ($activity !== []): ?><div class="panel"><h3><i class="fas fa-clock-rotate-left" aria-hidden="true"></i>Actividade recente</h3><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Data</th><th>Utilizador</th><th>Acção</th><th>Descrição</th></tr></thead><tbody><?php foreach ($activity as $event): ?><tr><td><?= !empty($event['criado_em']) ? e(date('d/m/Y H:i', strtotime((string) $event['criado_em']))) : '—' ?></td><td><?= e($event['usuario_nome'] ?? 'Sistema') ?></td><td><?= e($event['acao'] ?? '') ?> · <?= e($event['modulo'] ?? '') ?></td><td><?= e($event['descricao'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
