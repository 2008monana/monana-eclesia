<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$membros = is_array($membros ?? null) ? $membros : [];
$domingos = is_array($domingos ?? null) ? $domingos : [];
$mes = (int) ($mes ?? (int) date('n'));
$ano = (int) ($ano ?? (int) date('Y'));
$categoria = (string) ($categoria ?? 'todos');
$periodicidade = (string) ($periodicidade ?? 'mensal');
$cotaConfigurada = !empty($cotaConfigurada);
$periodosDevidos = (int) ($periodosDevidos ?? 0);
$nomesMeses = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
$categorias = ['todos' => 'Todos', 'mama' => 'Mamãs', 'papa' => 'Papás', 'jovem' => 'Jovens', 'crianca' => 'Crianças'];
$selected = $categorias[$categoria] ?? 'Todos';
?>
<div class="page-head">
    <div><h2><i class="fas fa-table" aria-hidden="true"></i>Listas de pagamento</h2><div class="sub">Grelha dos domingos · <?= e($nomesMeses[$mes] ?? '') ?> de <?= e($ano) ?> · <?= e($selected) ?></div></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="admin-btn" href="<?= e(url('listas/pdf') . '?' . http_build_query(['mes' => $mes, 'ano' => $ano, 'categoria' => $categoria])) ?>"><i class="fas fa-file-pdf" aria-hidden="true"></i>Exportar PDF</a>
        <a class="admin-btn" href="<?= e(url('cotas')) ?>"><i class="fas fa-book-open" aria-hidden="true"></i>Registo diário</a>
    </div>
</div>
<div class="grid-stats" style="margin-bottom:20px">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-users" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($totalMembros ?? 0)) ?></div><div class="stat-label">Membros activos</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-circle-check" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($totalQuitados ?? 0)) ?></div><div class="stat-label">Período quitado</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-circle-exclamation" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($totalEmAtraso ?? 0)) ?></div><div class="stat-label">Com períodos em falta</div></div>
</div>
<div class="panel" style="margin-bottom:20px">
    <form method="GET" action="<?= e(url('listas')) ?>" class="admin-form" style="max-width:none;display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap">
        <div class="field-group" style="min-width:150px;margin:0"><label for="lista-mes">Mês</label><select id="lista-mes" name="mes"><?php foreach ($nomesMeses as $number => $name): ?><option value="<?= e($number) ?>"<?= $mes === $number ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
        <div class="field-group" style="min-width:120px;margin:0"><label for="lista-ano">Ano</label><input id="lista-ano" type="number" name="ano" min="2000" max="2100" value="<?= e($ano) ?>"></div>
        <div class="field-group" style="min-width:160px;margin:0"><label for="lista-categoria">Categoria</label><select id="lista-categoria" name="categoria"><?php foreach ($categorias as $value => $label): ?><option value="<?= e($value) ?>"<?= $categoria === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <button class="admin-btn" type="submit"><i class="fas fa-filter" aria-hidden="true"></i>Aplicar</button>
    </form>
</div>
<?php if (!$cotaConfigurada): ?>
    <div class="inline-notice" role="status">O valor, a periodicidade ou o início das cotas ainda não está configurado. Os pagamentos continuam bloqueados até essa configuração ser concluída.</div>
<?php else: ?>
    <div class="inline-notice" style="margin-bottom:16px">
        Regra de quitação: saldo acumulado dos pagamentos dividido pela cota configurada. Pagamentos contam na data real em que entraram; a referência não os divide.
        <?php if ($periodicidade === 'anual'): ?>O período anual é um ciclo móvel de 12 meses, contado da data de início definida pela igreja.<?php else: ?>A dívida mensal reinicia em Janeiro, preservando o mês inicial definido no primeiro ano.<?php endif; ?>
        <?= $periodosDevidos > 0 ? 'Períodos devidos até esta data: ' . e($periodosDevidos) . '.' : 'Ainda não há períodos devidos para esta data.' ?>
    </div>
<?php endif; ?>
<div class="panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>#</th><th>Membro</th><?php foreach ($domingos as $domingo): ?><th><?= e(date('d/m', strtotime((string) $domingo))) ?></th><?php endforeach; ?><th>Períodos quitados</th><th>Estado</th></tr></thead>
            <tbody>
            <?php if ($membros === []): ?>
                <tr><td colspan="<?= e(count($domingos) + 4) ?>" class="empty-note">Não há membros activos nesta categoria.</td></tr>
            <?php else: ?>
                <?php foreach ($membros as $membro): ?>
                    <?php
                    $id = (int) ($membro['id'] ?? 0);
                    $state = (string) ($membro['estado_cota'] ?? 'nao_iniciado');
                    $stateLabel = $state === 'quitado' ? 'Quitado' : ($state === 'pendente' ? 'Em falta' : 'Não iniciado');
                    $stateClass = $state === 'quitado' ? ' ok' : ($state === 'pendente' ? ' bad' : '');
                    ?>
                    <tr>
                        <td><?= e($id) ?></td>
                        <td><strong><?= e($membro['nome_completo'] ?? '') ?></strong><div class="hint"><?= e($categorias[$membro['categoria'] ?? ''] ?? '') ?><?php if (!empty($membro['telefone'])): ?> · <?= e($membro['telefone']) ?><?php endif; ?></div></td>
                        <?php foreach ($domingos as $domingo): ?>
                            <?php $value = (float) (($membro['pagamentos_mes'] ?? [])[$domingo] ?? 0); ?>
                            <td><?= $value > 0 ? e(number_format($value, 2, ',', '.') . ' Kz') : '—' ?></td>
                        <?php endforeach; ?>
                        <td><?= e((int) ($membro['periodos_quitados'] ?? 0)) ?> / <?= e((int) ($membro['periodos_devidos'] ?? 0)) ?></td>
                        <td><span class="status-pill<?= e($stateClass) ?>"><?= e($stateLabel) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
