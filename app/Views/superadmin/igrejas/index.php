<?php
/** @var array $supervisor */
/** @var array $igrejas */
/** @var array $planos */
/** @var string $q */
/** @var string $status */
/** @var int $planoId */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var int $perPage */

$statusLabel = ['em_teste' => 'Em teste', 'activa' => 'Ativa', 'suspensa' => 'Suspensa', 'cancelada' => 'Cancelada'];
$statusClass = ['em_teste' => 'off', 'activa' => 'ok', 'suspensa' => 'off', 'cancelada' => 'off'];
?>
<div class="page-head">
    <h1 class="page-title">Igrejas</h1>
    <a href="<?= esc(url('superadmin/igrejas/criar')) ?>" class="btn btn-gold">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        Nova Igreja
    </a>
</div>

<div class="card">
    <form method="get" action="<?= esc(url('superadmin/igrejas')) ?>">
        <div class="field" style="position:relative;margin-bottom:12px">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"
               style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-soft);font-size:13px"></i>
            <input class="input" name="q" placeholder="Pesquisar por nome, e-mail ou slug..."
                   value="<?= esc($q) ?>" style="padding-left:38px">
        </div>

        <div class="filters" style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px">
            <select class="select" name="status">
                <option value="">Todos os status</option>
                <?php foreach ($statusLabel as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>

            <select class="select" name="plano">
                <option value="">Todos os planos</option>
                <?php foreach ($planos as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= $planoId === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= esc($p['nome']) ?>
                    </option>
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
                    <th>Membros</th>
                    <th>Users</th>
                    <th>Assinatura</th>
                    <th>Status</th>
                    <th style="text-align:right">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($igrejas)): ?>
                    <tr><td colspan="7" class="muted" style="text-align:center;padding:30px">
                        Nenhuma igreja encontrada.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($igrejas as $i): ?>
                        <tr>
                            <td>
                                <a href="<?= esc(url('superadmin/igrejas/' . $i['id'])) ?>"
                                   style="color:var(--brown-900);text-decoration:none;font-weight:500">
                                    <i class="fa-solid fa-church" aria-hidden="true" style="color:var(--gold-600);margin-right:6px"></i>
                                    <?= esc($i['nome']) ?>
                                </a>
                                <div class="muted" style="font-size:11.5px;margin-top:2px"><?= esc($i['slug']) ?></div>
                            </td>
                            <td class="muted"><?= esc($i['plano_nome'] ?? '—') ?></td>
                            <td>
                                <span style="font-weight:500"><?= (int) $i['total_membros'] ?></span>
                            </td>
                            <td>
                                <span style="font-weight:500"><?= (int) $i['total_users'] ?></span>
                            </td>
                            <td class="muted" style="font-size:12px">
                                <?php if ($i['sub_expiracao']): ?>
                                    <?= esc(date('d/m/Y', strtotime($i['sub_expiracao']))) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status <?= $statusClass[$i['status']] ?? 'off' ?>">
                                    <?= esc($statusLabel[$i['status']] ?? $i['status']) ?>
                                </span>
                            </td>
                            <td style="text-align:right">
                                <a href="<?= esc(url('superadmin/igrejas/' . $i['id'])) ?>"
                                   class="icon-btn" title="Ver" style="display:inline-grid">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </a>
                                <a href="<?= esc(url('superadmin/igrejas/' . $i['id'] . '/editar')) ?>"
                                   class="icon-btn" title="Editar" style="display:inline-grid">
                                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
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
                    <a href="?<?= http_build_query(array_filter(['q' => $q, 'status' => $status, 'plano' => $planoId ?: '', 'page' => $i])) ?>"
                       style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;<?= $i === $page ? 'background:var(--gold-500);color:#fff' : 'color:var(--brown-800)' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>