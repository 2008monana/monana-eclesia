<?php
/** @var array $user */
/** @var array $classes */
/** @var string $q */

use App\Core\Rbac;

$podeCriar = Rbac::can('ebd.criar');
?>
<div class="page-head">
    <h1 class="page-title">Escola Bíblica Dominical</h1>
    <?php if ($podeCriar): ?>
        <a href="<?= esc(url('ebd/criar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Nova Classe
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= esc(url('ebd')) ?>">
        <div class="field" style="position:relative">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"
               style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-soft);font-size:13px"></i>
            <input class="input" name="q" placeholder="Pesquisar classe..." value="<?= esc($q) ?>"
                   style="padding-left:38px">
        </div>
    </form>

    <div class="table-wrap" style="margin-top:14px">
        <table class="table">
            <thead>
                <tr>
                    <th>Classe</th>
                    <th>Faixa Etária</th>
                    <th>Sala</th>
                    <th>Alunos</th>
                    <th>Professores</th>
                    <th>Status</th>
                    <th style="text-align:right">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classes)): ?>
                    <tr><td colspan="7" class="muted" style="text-align:center;padding:30px">
                        <i class="fa-regular fa-folder-open" aria-hidden="true"
                           style="font-size:24px;display:block;margin-bottom:8px;color:var(--gold-300)"></i>
                        Nenhuma classe criada.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($classes as $c):
                        $inativo = (int) $c['ativo'] === 0;
                    ?>
                        <tr style="<?= $inativo ? 'opacity:.55' : '' ?>">
                            <td>
                                <a href="<?= esc(url('ebd/' . $c['id'])) ?>"
                                   style="color:var(--brown-900);font-weight:500;text-decoration:none">
                                    <i class="fa-solid fa-book-open" aria-hidden="true"
                                       style="color:var(--gold-600);margin-right:6px"></i>
                                    <?= esc($c['nome']) ?>
                                </a>
                            </td>
                            <td class="muted"><?= esc($c['faixa_etaria'] ?: '—') ?></td>
                            <td class="muted"><?= esc($c['sala'] ?: '—') ?></td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:5px">
                                    <i class="fa-solid fa-user-group" aria-hidden="true" style="color:var(--gold-600);font-size:12px"></i>
                                    <?= (int) $c['total_alunos'] ?>
                                </span>
                            </td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:5px">
                                    <i class="fa-solid fa-chalkboard-user" aria-hidden="true" style="color:var(--gold-600);font-size:12px"></i>
                                    <?= (int) $c['total_professores'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($inativo): ?>
                                    <span class="status off">Inactiva</span>
                                <?php else: ?>
                                    <span class="status ok">Activa</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right">
                                <a href="<?= esc(url('ebd/' . $c['id'])) ?>"
                                   class="icon-btn" title="Ver" style="display:inline-grid">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </a>
                                <?php if (Rbac::can('ebd.editar')): ?>
                                    <a href="<?= esc(url('ebd/' . $c['id'] . '/editar')) ?>"
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