<?php
/** @var array $user */
/** @var array $transacoes */
/** @var array $categorias */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var int $perPage */
/** @var string $tipo */
/** @var int $categoriaId */
/** @var string $de */
/** @var string $ate */
/** @var string $q */
/** @var array $totaisFiltro */

use App\Core\Rbac;

$podeCriar = Rbac::can('financeiro.criar');
$podeEditar = Rbac::can('financeiro.editar');
$podeElim  = Rbac::can('financeiro.eliminar');

$metodoIcones = [
    'dinheiro'      => 'fa-money-bill',
    'transferencia' => 'fa-building-columns',
    'multicaixa'    => 'fa-credit-card',
    'deposito'      => 'fa-vault',
    'outro'         => 'fa-circle-dot',
];
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('financeiro')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title">Transações</h1>
    </div>
    <?php if ($podeCriar): ?>
        <a href="<?= esc(url('financeiro/transacoes/criar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Nova Transação
        </a>
    <?php endif; ?>
</div>

<!-- Totais filtrados -->
<div class="grid kpis-3" style="margin-bottom:16px">
    <div class="card kpi-simple" style="padding:14px 18px">
        <div class="lbl muted" style="font-size:12px">Entradas (filtro)</div>
        <div class="val" style="color:var(--success);font-size:18px"><?= fmtKz($totaisFiltro['entradas'] ?? 0) ?></div>
    </div>
    <div class="card kpi-simple" style="padding:14px 18px">
        <div class="lbl muted" style="font-size:12px">Despesas (filtro)</div>
        <div class="val" style="color:var(--danger);font-size:18px"><?= fmtKz($totaisFiltro['despesas'] ?? 0) ?></div>
    </div>
    <div class="card kpi-simple" style="padding:14px 18px">
        <div class="lbl muted" style="font-size:12px">Saldo (filtro)</div>
        <div class="val" style="font-size:18px;color:<?= ((float) ($totaisFiltro['entradas'] ?? 0) - (float) ($totaisFiltro['despesas'] ?? 0)) >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
            <?= fmtKz(((float) ($totaisFiltro['entradas'] ?? 0)) - ((float) ($totaisFiltro['despesas'] ?? 0))) ?>
        </div>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= esc(url('financeiro/transacoes')) ?>">
        <div class="field" style="position:relative;margin-bottom:12px">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"
               style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-soft);font-size:13px"></i>
            <input class="input" name="q" placeholder="Pesquisar descrição..." value="<?= esc($q) ?>"
                   style="padding-left:38px">
        </div>

        <div class="filters" style="display:grid;grid-template-columns:repeat(4, 1fr) auto;gap:10px">
            <select class="select" name="tipo">
                <option value="">Todos os tipos</option>
                <option value="entrada" <?= $tipo === 'entrada' ? 'selected' : '' ?>>Entradas</option>
                <option value="despesa" <?= $tipo === 'despesa' ? 'selected' : '' ?>>Despesas</option>
            </select>

            <select class="select" name="categoria">
                <option value="">Todas as categorias</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $categoriaId === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= esc($c['nome']) ?> (<?= esc($c['tipo']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <input class="input" type="date" name="de" value="<?= esc($de) ?>" title="De">
            <input class="input" type="date" name="ate" value="<?= esc($ate) ?>" title="Até">

            <button type="submit" class="btn btn-outline">
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
            </button>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:14px">
        <table class="table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Membro</th>
                    <th>Método</th>
                    <th style="text-align:right">Valor</th>
                    <th style="text-align:right">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transacoes)): ?>
                    <tr><td colspan="7" class="muted" style="text-align:center;padding:30px">Nenhuma transação encontrada.</td></tr>
                <?php else: ?>
                    <?php foreach ($transacoes as $t):
                        $isEntrada = $t['tipo'] === 'entrada';
                    ?>
                        <tr>
                            <td class="muted" style="font-size:12.5px"><?= esc(date('d/m/Y', strtotime($t['data']))) ?></td>
                            <td><?= esc($t['descricao']) ?></td>
                            <td>
                                <?php if ($t['categoria_nome']): ?>
                                    <span style="display:inline-flex;align-items:center;gap:6px">
                                        <i style="width:8px;height:8px;border-radius:50%;background:<?= esc($t['categoria_cor'] ?: '#b8862b') ?>"></i>
                                        <?= esc($t['categoria_nome']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="muted"><?= esc($t['membro_nome'] ?? '—') ?></td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;color:var(--text-muted)">
                                    <i class="fa-solid <?= $metodoIcones[$t['metodo'] ?? 'outro'] ?? 'fa-circle-dot' ?>" aria-hidden="true" style="font-size:11px"></i>
                                    <?= esc(ucfirst($t['metodo'] ?? '—')) ?>
                                </span>
                            </td>
                            <td style="text-align:right;font-weight:600;color:<?= $isEntrada ? 'var(--success)' : 'var(--danger)' ?>">
                                <?= $isEntrada ? '+' : '−' ?><?= fmtKz($t['valor_kz']) ?>
                            </td>
                            <td style="text-align:right;white-space:nowrap">
                                <?php if ($podeEditar): ?>
                                    <a href="<?= esc(url('financeiro/transacoes/' . $t['id'] . '/editar')) ?>"
                                       class="icon-btn" title="Editar" style="display:inline-grid">
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($podeElim): ?>
                                    <form method="post"
                                          action="<?= esc(url('financeiro/transacoes/' . $t['id'] . '/eliminar')) ?>"
                                          style="margin:0;display:inline-grid" onsubmit="return confirm('Eliminar transação?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn" title="Eliminar">
                                            <i class="fa-solid fa-trash" aria-hidden="true" style="color:var(--danger)"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pager-row" style="display:flex;justify-content:space-between;align-items:center;padding-top:16px;font-size:13px;color:var(--text-muted);flex-wrap:wrap;gap:10px">
        <span>
            Mostrando <?= $total ? (($page - 1) * $perPage + 1) : 0 ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?>
        </span>
        <?php if ($pages > 1): ?>
            <div class="pager" style="display:flex;gap:4px">
                <?php $start = max(1, $page - 2); $end = min($pages, $page + 2); ?>
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_filter(['tipo' => $tipo, 'categoria' => $categoriaId ?: '', 'de' => $de, 'ate' => $ate, 'q' => $q, 'page' => $page - 1])) ?>"
                       style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;color:var(--brown-800)">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true" style="font-size:11px"></i>
                    </a>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?<?= http_build_query(array_filter(['tipo' => $tipo, 'categoria' => $categoriaId ?: '', 'de' => $de, 'ate' => $ate, 'q' => $q, 'page' => $i])) ?>"
                       style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;<?= $i === $page ? 'background:var(--gold-500);color:#fff' : 'color:var(--brown-800)' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                    <a href="?<?= http_build_query(array_filter(['tipo' => $tipo, 'categoria' => $categoriaId ?: '', 'de' => $de, 'ate' => $ate, 'q' => $q, 'page' => $page + 1])) ?>"
                       style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;color:var(--brown-800)">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true" style="font-size:11px"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>