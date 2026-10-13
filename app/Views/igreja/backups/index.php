<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$counts = is_array($quantidades ?? null) ? $quantidades : [];
$total = (int) ($total ?? 0);
$eligible = !empty($elegivel);
$labels = [
    'utilizadores' => 'Utilizadores (sem hashes de senha)',
    'permissoes_modulo' => 'Permissões de módulos',
    'membros' => 'Membros',
    'cotas_diarias' => 'Pagamentos de cotas',
    'contribuicao_fim_ano' => 'Contribuições de fim de ano',
    'saidas' => 'Saídas financeiras',
    'logs_auditoria' => 'Auditoria da igreja',
];
?>
<div class="page-head"><div><h2><i class="fas fa-box-archive" aria-hidden="true"></i>Cópias de segurança</h2><div class="sub">Exporta uma cópia JSON dos dados desta igreja para um dispositivo seguro.</div></div></div>
<div class="grid-stats" style="margin-bottom:20px"><div class="stat-card"><div class="stat-icon"><i class="fas fa-database" aria-hidden="true"></i></div><div class="stat-num"><?= e($total) ?></div><div class="stat-label">Registos incluídos</div></div><div class="stat-card"><div class="stat-icon"><i class="fas fa-lock" aria-hidden="true"></i></div><div class="stat-num">Tenant</div><div class="stat-label">Escopo restrito à igreja autenticada</div></div></div>
<div class="panel" style="margin-bottom:20px"><h3>Conteúdo da cópia</h3><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Conjunto de dados</th><th>Registos</th></tr></thead><tbody><?php foreach ($labels as $key => $label): ?><tr><td><?= e($label) ?></td><td><?= e((int) ($counts[$key] ?? 0)) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="phase-notice"><strong>Cuidados:</strong> a cópia pode conter dados pessoais e financeiros. Guarda-a encriptada num local controlado e não a partilhes por email. A exportação não inclui senhas, hashes, tokens, sessões, payloads de email, dados de outras igrejas nem SQL executável. A restauração assistida ainda não está disponível.</div>
<div class="panel" style="margin-top:20px"><h3>Exportar cópia tenant-scoped</h3><p>O ficheiro JSON contém as configurações, contas e permissões locais, membros, movimentos financeiros e auditoria associados a esta igreja.</p><?php if (!$eligible): ?><div class="inline-notice" role="alert">O volume ultrapassa o limite seguro de exportação desta interface. Contacta o suporte para uma cópia assistida.</div><?php else: ?><form method="POST" action="<?= e(url('copias-de-seguranca/exportar')) ?>" onsubmit="return confirm('Descarregar uma cópia com dados pessoais da igreja para este dispositivo?');"><?= csrf_field() ?><button class="admin-btn primary" type="submit"><i class="fas fa-download" aria-hidden="true"></i>Descarregar JSON</button></form><?php endif; ?></div>
