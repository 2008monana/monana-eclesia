<?php
/** @var array $user */
/** @var array $membros */
/** @var array $departamentos */
/** @var string $q */
/** @var string $dep */
/** @var string $status */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
/** @var int $perPage */

use App\Core\Rbac;

$podeCriar = Rbac::can('membros.criar');
?>
<div class="page-head">
    <h1 class="page-title">Gestão de Membros</h1>
    <?php if ($podeCriar): ?>
        <a href="<?= esc(url('membros/criar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
            Novo Membro
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= esc(url('membros')) ?>">
        <div class="field" style="position:relative">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"
               style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-soft);font-size:13px"></i>
            <input class="input" name="q" placeholder="Pesquisar por nome, telefone ou código..."
                   value="<?= esc($q) ?>" style="padding-left:38px">
        </div>

        <div class="filters" style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;margin-top:12px">
            <select class="select" name="dep">
                <option value="">Todos os departamentos</option>
                <?php foreach ($departamentos as $d): ?>
                    <option value="<?= esc($d['nome']) ?>" <?= $dep === $d['nome'] ? 'selected' : '' ?>>
                        <?= esc($d['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select class="select" name="status">
                <option value="">Todos os status</option>
                <?php foreach (MEMBER_STATUS as $s): ?>
                    <option value="<?= esc($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                        <?= esc(ucfirst($s)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-outline" type="submit" title="Filtrar">
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
            </button>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:14px">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:60px">Foto</th>
                    <th>Código</th>
                    <th>Nome</th>
                    <th>Departamento</th>
                    <th>Status</th>
                    <th style="text-align:right">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($membros)): ?>
                    <tr>
                        <td colspan="6" class="muted" style="text-align:center;padding:30px">
                            <i class="fa-regular fa-face-frown" aria-hidden="true" style="font-size:24px;display:block;margin-bottom:8px;color:var(--gold-300)"></i>
                            Nenhum membro encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($membros as $m): ?>
                        <?php
                        $iniciais = '';
                        $partes = preg_split('/\s+/', trim($m['nome']));
                        $iniciais = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
                        $statusClass = $m['status'] === 'activo' ? 'ok' : 'off';
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($m['foto'])): ?>
                                    <img src="<?= esc(asset('uploads/membros/' . $m['foto'])) ?>"
                                         alt="" class="avatar" width="34" height="34">
                                <?php else: ?>
                                    <span class="avatar" style="width:34px;height:34px;display:inline-grid;place-items:center;color:var(--gold-700);font-weight:600;font-size:12px;background:var(--gold-100);border-radius:50%">
                                        <?= esc($iniciais) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="muted" style="font-size:12px"><?= esc($m['codigo']) ?></td>
                            <td>
                                <a href="<?= esc(url('membros/' . $m['id'])) ?>" class="name-link" style="color:var(--brown-900);font-weight:500;text-decoration:none">
                                    <?= esc($m['nome']) ?>
                                </a>
                            </td>
                            <td class="muted"><?= esc($m['primeiro_departamento'] ?? '—') ?></td>
                            <td>
                                <span class="status <?= $statusClass ?>">
                                    <?= esc(ucfirst($m['status'])) ?>
                                </span>
                            </td>
                            <td style="text-align:right">
                                <a href="<?= esc(url('membros/' . $m['id'])) ?>"
                                   class="icon-btn" title="Ver perfil" style="display:inline-grid">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pager-row">
        <span>
            Mostrando <?= $total ? (($page - 1) * $perPage + 1) : 0 ?>–<?= min($page * $perPage, $total) ?>
            de <?= $total ?>
        </span>
        <?php if ($pages > 1): ?>
            <div class="pager">
                <?php
                $start = max(1, $page - 2);
                $end   = min($pages, $page + 2);
                ?>
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_filter(['q' => $q, 'dep' => $dep, 'status' => $status, 'page' => $page - 1])) ?>"
                       class="pager-btn" style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;color:var(--brown-800)">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true" style="font-size:11px"></i>
                    </a>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?<?= http_build_query(array_filter(['q' => $q, 'dep' => $dep, 'status' => $status, 'page' => $i])) ?>"
                       class="pager-btn <?= $i === $page ? 'on' : '' ?>"
                       style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;<?= $i === $page ? 'background:var(--gold-500);color:#fff' : 'color:var(--brown-800)' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                    <a href="?<?= http_build_query(array_filter(['q' => $q, 'dep' => $dep, 'status' => $status, 'page' => $page + 1])) ?>"
                       class="pager-btn" style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;color:var(--brown-800)">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true" style="font-size:11px"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>