<?php
/** @var array $supervisor */
/** @var array $pagamentos */
/** @var string $estado */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var int $perPage */

$estadoLabel = [
    'pendente' => 'Pendente', 'confirmado' => 'Confirmado',
    'rejeitado' => 'Rejeitado', 'reembolsado' => 'Reembolsado',
];
$estadoClass = [
    'pendente' => 'off', 'confirmado' => 'ok',
    'rejeitado' => 'off', 'reembolsado' => 'off',
];
?>
<div class="page-head">
    <h1 class="page-title">Pagamentos</h1>
</div>

<div class="card">
    <form method="get" action="<?= esc(url('superadmin/pagamentos')) ?>">
        <div class="filters" style="display:grid;grid-template-columns:1fr auto;gap:12px">
            <select class="select" name="estado" onchange="this.form.submit()">
                <option value="">Todos os estados</option>
                <?php foreach ($estadoLabel as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $estado === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline">
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
            </button>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:14px">
        <table class="table">
            <thead>
                <tr>
                    <th>Igreja</th>
                    <th>Plano</th>
                    <th>Valor</th>
                    <th>Método</th>
                    <th>Referência</th>
                    <th>Data</th>
                    <th>Estado</th>
                    <th style="text-align:right"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pagamentos)): ?>
                    <tr><td colspan="8" class="muted" style="text-align:center;padding:30px">
                        Nenhum pagamento encontrado.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($pagamentos as $p): ?>
                        <tr>
                            <td>
                                <a href="<?= esc(url('superadmin/igrejas/' . $p['igreja_id'])) ?>"
                                   style="color:var(--brown-900);text-decoration:none;font-weight:500">
                                    <i class="fa-solid fa-church" aria-hidden="true" style="color:var(--gold-600);margin-right:6px"></i>
                                    <?= esc($p['igreja_nome']) ?>
                                </a>
                            </td>
                            <td class="muted"><?= esc($p['plano_nome'] ?? '—') ?></td>
                            <td><strong><?= fmtKz($p['valor_kz']) ?></strong></td>
                            <td class="muted"><?= esc(ucfirst($p['metodo'] ?? '—')) ?></td>
                            <td class="muted" style="font-size:12px"><?= esc($p['referencia'] ?? '—') ?></td>
                            <td class="muted" style="font-size:12px"><?= esc(date('d/m/Y', strtotime($p['criado_em']))) ?></td>
                            <td>
                                <span class="status <?= $estadoClass[$p['estado']] ?? 'off' ?>">
                                    <?= esc($estadoLabel[$p['estado']] ?? $p['estado']) ?>
                                </span>
                            </td>
                            <td style="text-align:right">
                                <a href="<?= esc(url('superadmin/pagamentos/' . $p['id'])) ?>"
                                   class="icon-btn" title="Ver" style="display:inline-grid">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="pager-row" style="display:flex;justify-content:space-between;align-items:center;padding-top:16px;font-size:13px;color:var(--text-muted)">
            <span>Mostrando <?= $total ? (($page - 1) * $perPage + 1) : 0 ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?></span>
            <div style="display:flex;gap:4px">
                <?php $start = max(1, $page - 2); $end = min($pages, $page + 2); ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?<?= http_build_query(array_filter(['estado' => $estado, 'page' => $i])) ?>"
                       style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;<?= $i === $page ? 'background:var(--gold-500);color:#fff' : 'color:var(--brown-800)' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>