<?php
/** @var array $user */
/** @var array $departamentos */
/** @var string $q */
/** @var int $totalMembros */

use App\Core\Rbac;

$podeCriar = Rbac::can('departamentos.criar');
?>
<div class="page-head">
    <h1 class="page-title">Departamentos</h1>
    <?php if ($podeCriar): ?>
        <a href="<?= esc(url('departamentos/criar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Novo Departamento
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= esc(url('departamentos')) ?>">
        <div class="field" style="position:relative">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"
               style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-soft);font-size:13px"></i>
            <input class="input" name="q" placeholder="Pesquisar departamento..." value="<?= esc($q) ?>"
                   style="padding-left:38px">
        </div>
    </form>

    <div class="table-wrap" style="margin-top:14px">
        <table class="table">
            <thead>
                <tr>
                    <th>Departamento</th>
                    <th>Líder</th>
                    <th>Vice</th>
                    <th>Membros</th>
                    <th style="width:200px">Percentagem</th>
                    <th>Status</th>
                    <th style="text-align:right">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departamentos)): ?>
                    <tr><td colspan="7" class="muted" style="text-align:center;padding:30px">
                        <i class="fa-regular fa-folder-open" aria-hidden="true" style="font-size:24px;display:block;margin-bottom:8px;color:var(--gold-300)"></i>
                        Nenhum departamento encontrado.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($departamentos as $d):
                        $pct = round(((int) $d['total_membros'] / $totalMembros) * 100);
                        $inativo = (int) $d['ativo'] === 0;
                    ?>
                        <tr style="<?= $inativo ? 'opacity:.55' : '' ?>">
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:10px">
                                    <span style="width:10px;height:10px;border-radius:50%;background:<?= esc($d['cor'] ?: '#b8862b') ?>"></span>
                                    <a href="<?= esc(url('departamentos/' . $d['id'])) ?>"
                                       style="color:var(--brown-900);font-weight:500;text-decoration:none">
                                        <?= esc($d['nome']) ?>
                                    </a>
                                </span>
                            </td>
                            <td class="muted"><?= esc($d['lider_nome'] ?? '—') ?></td>
                            <td class="muted"><?= esc($d['vice_nome'] ?? '—') ?></td>
                            <td><?= (int) $d['total_membros'] ?></td>
                            <td>
                                <div style="background:var(--gold-100);border-radius:999px;height:8px;overflow:hidden">
                                    <div style="width:<?= $pct ?>%;height:100%;background:<?= esc($d['cor'] ?: '#b8862b') ?>"></div>
                                </div>
                                <div class="muted" style="font-size:11.5px;margin-top:3px"><?= $pct ?>%</div>
                            </td>
                            <td>
                                <?php if ($inativo): ?>
                                    <span class="status off">Inactivo</span>
                                <?php else: ?>
                                    <span class="status ok">Activo</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right">
                                <a href="<?= esc(url('departamentos/' . $d['id'])) ?>"
                                   class="icon-btn" title="Ver" style="display:inline-grid">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </a>
                                <?php if (Rbac::can('departamentos.editar')): ?>
                                    <a href="<?= esc(url('departamentos/' . $d['id'] . '/editar')) ?>"
                                       class="icon-btn" title="Editar" style="display:inline-grid">
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>