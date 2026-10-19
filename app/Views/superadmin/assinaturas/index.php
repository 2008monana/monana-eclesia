<?php
/** @var array $supervisor */
/** @var array $assinaturas */
/** @var string $q */
/** @var string $status */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var int $perPage */

$statusLabel = [
    'trial' => 'Trial', 'activa' => 'Ativa', 'expirando' => 'A expirar',
    'expirada' => 'Expirada', 'em_tolerancia' => 'Em tolerância',
    'suspensa' => 'Suspensa', 'cancelada' => 'Cancelada',
];
$statusClass = [
    'trial' => 'off', 'activa' => 'ok', 'expirando' => 'off',
    'expirada' => 'off', 'em_tolerancia' => 'off', 'suspensa' => 'off', 'cancelada' => 'off',
];
?>
<div class="page-head">
    <h1 class="page-title">Assinaturas</h1>
    <a href="<?= esc(url('superadmin/assinaturas/criar')) ?>" class="btn btn-gold">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        Nova Assinatura
    </a>
</div>

<div class="card">
    <form method="get" action="<?= esc(url('superadmin/assinaturas')) ?>">
        <div class="field" style="position:relative;margin-bottom:12px">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"
               style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-soft);font-size:13px"></i>
            <input class="input" name="q" placeholder="Pesquisar igreja..." value="<?= esc($q) ?>"
                   style="padding-left:38px">
        </div>

        <div class="filters" style="display:grid;grid-template-columns:1fr auto;gap:12px">
            <select class="select" name="status">
                <option value="">Todos os estados</option>
                <?php foreach ($statusLabel as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
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
                    <th>Início</th>
                    <th>Expira</th>
                    <th>Estado</th>
                    <th style="text-align:right">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assinaturas)): ?>
                    <tr><td colspan="6" class="muted" style="text-align:center;padding:30px">
                        Nenhuma assinatura encontrada.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($assinaturas as $s): ?>
                        <tr>
                            <td>
                                <a href="<?= esc(url('superadmin/igrejas/' . $s['igreja_id'])) ?>"
                                   style="color:var(--brown-900);text-decoration:none;font-weight:500">
                                    <i class="fa-solid fa-church" aria-hidden="true" style="color:var(--gold-600);margin-right:6px"></i>
                                    <?= esc($s['igreja_nome']) ?>
                                </a>
                            </td>
                            <td class="muted"><?= esc($s['plano_nome']) ?></td>
                            <td class="muted" style="font-size:12px"><?= esc(date('d/m/Y', strtotime($s['inicio']))) ?></td>
                            <td class="muted" style="font-size:12px">
                                <?= $s['expiracao'] ? esc(date('d/m/Y', strtotime($s['expiracao']))) : '—' ?>
                            </td>
                            <td>
                                <span class="status <?= $statusClass[$s['status']] ?? 'off' ?>">
                                    <?= esc($statusLabel[$s['status']] ?? $s['status']) ?>
                                </span>
                            </td>
                            <td style="text-align:right">
                                <a href="<?= esc(url('superadmin/assinaturas/' . $s['id'])) ?>"
                                   class="icon-btn" title="Ver detalhes" style="display:inline-grid">
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
                    <a href="?<?= http_build_query(array_filter(['q' => $q, 'status' => $status, 'page' => $i])) ?>"
                       style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;<?= $i === $page ? 'background:var(--gold-500);color:#fff' : 'color:var(--brown-800)' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>