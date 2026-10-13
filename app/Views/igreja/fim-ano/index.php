<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$membros = is_array($membros ?? null) ? $membros : [];
$ano = (int) ($ano ?? date('Y'));
$categoria = (string) ($categoria ?? 'todos');
$metaAnual = (float) ($metaAnual ?? 5000);
$mensagem = (string) ($mensagem ?? '');
$podeGerir = !empty($podeGerir);
$categorias = ['todos' => 'Todos', 'mama' => 'Mamãs', 'papa' => 'Papás', 'jovem' => 'Jovens'];
?>
<div class="page-head"><div><h2><i class="fas fa-gift" aria-hidden="true"></i>Contribuições de fim de ano</h2><div class="sub">Meta por membro: <?= e(number_format($metaAnual, 2, ',', '.')) ?> Kz · fundo separado das cotas.</div></div></div>
<?php if ($mensagem !== ''): ?><div class="inline-notice" role="status"><?= e($mensagem) ?></div><?php endif; ?>
<div class="grid-stats" style="margin-bottom:20px">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-users" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($totalMembros ?? 0)) ?></div><div class="stat-label">Membros activos</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-circle-check" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($membrosQuitados ?? 0)) ?></div><div class="stat-label">Quitados</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-arrow-down-to-line" aria-hidden="true"></i></div><div class="stat-num"><?= e(number_format((float) ($totalArrecadado ?? 0), 2, ',', '.')) ?> Kz</div><div class="stat-label">Arrecadado em <?= e($ano) ?></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-wallet" aria-hidden="true"></i></div><div class="stat-num"><?= e(number_format((float) ($saldoFundo ?? 0), 2, ',', '.')) ?> Kz</div><div class="stat-label">Saldo do fundo no ano</div></div>
</div>
<div class="panel" style="margin-bottom:20px">
    <form method="GET" action="<?= e(url('contribuicoes-fim-de-ano')) ?>" class="admin-form" style="max-width:none;display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap">
        <div class="field-group" style="min-width:130px;margin:0"><label for="fim-ano">Ano</label><input id="fim-ano" name="ano" type="number" min="2000" max="2100" value="<?= e($ano) ?>"></div>
        <div class="field-group" style="min-width:150px;margin:0"><label for="fim-categoria">Categoria</label><select id="fim-categoria" name="categoria"><?php foreach ($categorias as $value => $label): ?><option value="<?= e($value) ?>"<?= $categoria === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <button class="admin-btn" type="submit"><i class="fas fa-filter" aria-hidden="true"></i>Aplicar</button>
    </form>
</div>
<?php if (empty($configurada)): ?>
    <div class="inline-notice" role="status">Define o início das contribuições nas configurações da igreja antes de registar valores.</div>
<?php endif; ?>
<div class="panel">
    <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Membro</th><th>Categoria</th><th>Pagamentos</th><th>Total pago</th><th>Saldo restante</th><th>Estado</th><th>Registar contribuição</th><th>Acções</th></tr></thead>
        <tbody>
        <?php if ($membros === []): ?><tr><td colspan="8" class="empty-note">Não há membros activos nesta selecção.</td></tr>
        <?php else: foreach ($membros as $membro): ?>
            <?php
            $memberId = (int) ($membro['id'] ?? 0);
            $paid = (float) ($membro['total_pago'] ?? 0);
            $remaining = (float) ($membro['saldo_restante'] ?? 0);
            $payments = is_array($membro['pagamentos'] ?? null) ? $membro['pagamentos'] : [];
            $isSettled = !empty($membro['esta_quitado']);
            ?>
            <tr>
                <td><strong><?= e($membro['nome_completo'] ?? '') ?></strong><?php if (!empty($membro['telefone'])): ?><div class="hint"><?= e($membro['telefone']) ?></div><?php endif; ?></td>
                <td><?= e($categorias[$membro['categoria'] ?? ''] ?? '—') ?></td>
                <td><?php if ($payments === []): ?>—<?php else: ?><details><summary><?= e(count($payments)) ?> pagamento(s)</summary><?php foreach ($payments as $payment): ?><?= e(date('d/m/Y', strtotime((string) ($payment['data_pagamento'] ?? '')))) ?> — <?= e(number_format((float) ($payment['valor'] ?? 0), 2, ',', '.')) ?> Kz<br><?php endforeach; ?></details><?php endif; ?></td>
                <td><?= e(number_format($paid, 2, ',', '.')) ?> Kz</td>
                <td><?= e(number_format($remaining, 2, ',', '.')) ?> Kz</td>
                <td><span class="status-pill<?= $isSettled ? ' ok' : ($paid > 0 ? '' : ' bad') ?>"><?= $isSettled ? 'Quitado' : ($paid > 0 ? 'Parcial' : 'Em falta') ?></span></td>
                <td>
                    <?php if ($podeGerir && !empty($configurada) && $remaining > 0): ?>
                        <form method="POST" action="<?= e(url('contribuicoes-fim-de-ano/registar')) ?>" class="admin-actions" style="flex-wrap:nowrap">
                            <?= csrf_field() ?><input type="hidden" name="membro_id" value="<?= e($memberId) ?>"><input type="hidden" name="ano" value="<?= e($ano) ?>">
                            <input aria-label="Valor da contribuição" name="valor" type="text" inputmode="decimal" value="<?= e(number_format($remaining, 2, ',', '.')) ?>" style="width:110px" required>
                            <button class="admin-btn small primary" type="submit">Registar</button>
                        </form>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <?php if ($podeGerir && $paid > 0): ?>
                        <form method="POST" action="<?= e(url('contribuicoes-fim-de-ano/remover')) ?>" onsubmit="return confirm('Remover todos os pagamentos deste membro no ano seleccionado?');">
                            <?= csrf_field() ?><input type="hidden" name="membro_id" value="<?= e($memberId) ?>"><input type="hidden" name="ano" value="<?= e($ano) ?>"><button class="admin-btn small danger" type="submit" aria-label="Remover contribuições"><i class="fas fa-trash" aria-hidden="true"></i></button>
                        </form>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
