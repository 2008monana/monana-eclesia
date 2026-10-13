<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$registos = is_array($registos ?? null) ? $registos : [];
$configuracao = is_array($configuracao ?? null) ? $configuracao : [];
$data = (string) ($data ?? date('Y-m-d'));
$hoje = (string) ($hoje ?? date('Y-m-d'));
$mensagem = (string) ($mensagem ?? '');
$podeGerir = !empty($podeGerir);
$periodicidade = (string) ($configuracao['periodicidade_cota'] ?? 'mensal');
$valorCota = number_format((float) ($configuracao['valor_cota'] ?? 0), 2, ',', '.');
$totalValor = 0.0;
foreach ($registos as $registo) {
    $totalValor += (float) ($registo['valor'] ?? 0);
}
?>
<div class="page-head">
    <div>
        <h2><i class="fas fa-book-open" aria-hidden="true"></i>Cotas</h2>
        <div class="sub">Registo diário · cota de <?= e($valorCota) ?> Kz · periodicidade <?= e($periodicidade === 'anual' ? 'anual (ciclo móvel de 12 meses)' : 'mensal') ?></div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="admin-btn" href="<?= e(url('cotas/pdf') . '?' . http_build_query(['data' => $data])) ?>"><i class="fas fa-file-pdf" aria-hidden="true"></i>PDF do dia</a>
        <a class="admin-btn primary" href="<?= e(url('cotas/novo')) ?>"><i class="fas fa-plus" aria-hidden="true"></i>Registar pagamento</a>
    </div>
</div>
<?php if ($mensagem !== ''): ?><div class="inline-notice status-pill ok" role="status"><?= e($mensagem) ?></div><?php endif; ?>
<div class="grid-stats" style="margin-bottom:20px">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-receipt" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($total ?? 0)) ?></div><div class="stat-label">Pagamentos no dia</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-coins" aria-hidden="true"></i></div><div class="stat-num"><?= e(number_format($totalValor, 2, ',', '.')) ?> Kz</div><div class="stat-label">Total do dia</div></div>
</div>
<div class="panel" style="margin-bottom:20px">
    <form method="GET" action="<?= e(url('cotas')) ?>" class="admin-form" style="max-width:none;display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap">
        <div class="field-group" style="min-width:190px;margin:0"><label for="cotas-data">Data do pagamento</label><input id="cotas-data" type="date" name="data" value="<?= e($data) ?>"></div>
        <button class="admin-btn" type="submit"><i class="fas fa-filter" aria-hidden="true"></i>Filtrar</button>
        <a class="admin-btn" href="<?= e(url('cotas')) ?>">Hoje</a>
    </form>
</div>
<div class="panel">
    <div class="panel-head"><h3><i class="fas fa-list" aria-hidden="true"></i>Pagamentos de <?= e(date('d/m/Y', strtotime($data))) ?></h3><span class="empty-note" style="padding:0"><?= e((int) ($total ?? 0)) ?> registo(s)</span></div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>#</th><th>Membro</th><th>Categoria</th><th>Referência</th><th>Valor</th><th>Registado por</th><th>Observação</th><th>Acções</th></tr></thead>
            <tbody>
            <?php if ($registos === []): ?>
                <tr><td colspan="8" class="empty-note">Não há pagamentos de cotas nesta data.</td></tr>
            <?php else: ?>
                <?php foreach ($registos as $registo): ?>
                    <?php
                    $id = (int) ($registo['id'] ?? 0);
                    $reference = (string) ($registo['mes_referencia'] ?? '');
                    $dateTime = strtotime($reference);
                    $referenceLabel = $dateTime === false
                        ? '—'
                        : ($periodicidade === 'anual' ? date('d/m/Y', $dateTime) : date('m/Y', $dateTime));
                    $categoryLabels = ['mama' => 'Mamã', 'papa' => 'Papá', 'jovem' => 'Jovem', 'crianca' => 'Criança'];
                    ?>
                    <tr>
                        <td><?= e($id) ?></td>
                        <td><?= e($registo['nome_completo'] ?? '') ?></td>
                        <td><?= e($categoryLabels[$registo['categoria'] ?? ''] ?? '—') ?></td>
                        <td><?= e($referenceLabel) ?></td>
                        <td><?= e(number_format((float) ($registo['valor'] ?? 0), 2, ',', '.')) ?> Kz</td>
                        <td><?= e($registo['registrado_nome'] ?? '—') ?></td>
                        <td><?= e($registo['observacao'] ?? '—') ?></td>
                        <td>
                            <?php if ($podeGerir && $data === $hoje): ?>
                                <div class="admin-actions">
                                    <a class="admin-btn small" href="<?= e(url('cotas/' . $id . '/editar')) ?>" aria-label="Editar pagamento"><i class="fas fa-pen" aria-hidden="true"></i></a>
                                    <form method="POST" action="<?= e(url('cotas/' . $id . '/apagar')) ?>" onsubmit="return confirm('Remover este pagamento de hoje?');">
                                        <?= csrf_field() ?>
                                        <button class="admin-btn small danger" type="submit" aria-label="Remover pagamento"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                    </form>
                                </div>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($data !== $hoje): ?><p class="hint" style="margin-top:14px">Registos de dias anteriores não podem ser editados nem removidos.</p><?php endif; ?>
</div>
