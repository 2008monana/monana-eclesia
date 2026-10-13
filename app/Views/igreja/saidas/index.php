<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$saidas = is_array($saidas ?? null) ? $saidas : [];
$saldos = is_array($saldos ?? null) ? $saldos : [];
$totais = is_array($totais ?? null) ? $totais : [];
$mensagem = (string) ($mensagem ?? '');
$podeGerir = !empty($podeGerir);
$fundos = ['cotas' => 'Cotas', 'contribuicoes' => 'Contribuições de fim de ano'];
?>
<div class="page-head">
    <div><h2><i class="fas fa-arrow-trend-down" aria-hidden="true"></i>Saídas</h2><div class="sub">As saídas e os saldos permanecem separados por fundo.</div></div>
    <?php if ($podeGerir): ?><a class="admin-btn primary" href="<?= e(url('saidas/nova')) ?>"><i class="fas fa-plus" aria-hidden="true"></i>Registar saída</a><?php endif; ?>
</div>
<?php if ($mensagem !== ''): ?><div class="inline-notice status-pill ok" role="status"><?= e($mensagem) ?></div><?php endif; ?>
<div class="grid-stats" style="margin-bottom:20px">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-coins" aria-hidden="true"></i></div><div class="stat-num"><?= e(number_format((float) ($saldos['cotas'] ?? 0), 2, ',', '.')) ?> Kz</div><div class="stat-label">Saldo do fundo de cotas</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-gift" aria-hidden="true"></i></div><div class="stat-num"><?= e(number_format((float) ($saldos['contribuicoes'] ?? 0), 2, ',', '.')) ?> Kz</div><div class="stat-label">Saldo de contribuições</div></div>
</div>
<div class="panel">
    <div class="panel-head"><h3><i class="fas fa-list" aria-hidden="true"></i>Movimentos de saída</h3><span class="empty-note" style="padding:0"><?= e(count($saidas)) ?> registo(s)</span></div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Beneficiário</th><th>Fundo</th><th>Valor</th><th>Registado por</th><th>Acções</th></tr></thead>
            <tbody>
            <?php if ($saidas === []): ?><tr><td colspan="8" class="empty-note">Ainda não há saídas registadas.</td></tr>
            <?php else: ?>
                <?php foreach ($saidas as $saida): ?>
                    <?php $id = (int) ($saida['id'] ?? 0); $date = strtotime((string) ($saida['data_saida'] ?? '')); ?>
                    <tr>
                        <td><?= e($date === false ? '—' : date('d/m/Y', $date)) ?></td>
                        <td><?= e($saida['descricao'] ?? '') ?></td>
                        <td><?= e($saida['categoria'] ?? '') ?></td>
                        <td><?= e($saida['beneficiario'] ?? '—') ?></td>
                        <td><?= e($fundos[$saida['origem_fundo'] ?? ''] ?? '—') ?></td>
                        <td><?= e(number_format((float) ($saida['valor'] ?? 0), 2, ',', '.')) ?> Kz</td>
                        <td><?= e($saida['registrado_nome'] ?? '—') ?></td>
                        <td><?php if ($podeGerir): ?><div class="admin-actions"><a class="admin-btn small" href="<?= e(url('saidas/' . $id . '/editar')) ?>" aria-label="Editar saída"><i class="fas fa-pen" aria-hidden="true"></i></a><form method="POST" action="<?= e(url('saidas/' . $id . '/apagar')) ?>" onsubmit="return confirm('Remover esta saída?');"><?= csrf_field() ?><button class="admin-btn small danger" type="submit" aria-label="Remover saída"><i class="fas fa-trash" aria-hidden="true"></i></button></form></div><?php else: ?>—<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
