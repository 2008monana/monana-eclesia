<?php
/** @var array $user */
/** @var array $celulas */
/** @var string $q */

use App\Core\Rbac;

$podeCriar = Rbac::can('celulas.criar');
$diasLabel = [
    'seg' => 'Segunda', 'ter' => 'Terça', 'qua' => 'Quarta',
    'qui' => 'Quinta', 'sex' => 'Sexta', 'sab' => 'Sábado', 'dom' => 'Domingo',
];
?>
<div class="page-head">
    <h1 class="page-title">Células</h1>
    <?php if ($podeCriar): ?>
        <a href="<?= esc(url('celulas/criar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Nova Célula
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= esc(url('celulas')) ?>">
        <div class="field" style="position:relative">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"
               style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-soft);font-size:13px"></i>
            <input class="input" name="q" placeholder="Pesquisar célula..." value="<?= esc($q) ?>"
                   style="padding-left:38px">
        </div>
    </form>

    <div class="table-wrap" style="margin-top:14px">
        <table class="table">
            <thead>
                <tr>
                    <th>Célula</th>
                    <th>Líder</th>
                    <th>Anfitrião</th>
                    <th>Dia / Hora</th>
                    <th>Membros</th>
                    <th>Status</th>
                    <th style="text-align:right">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($celulas)): ?>
                    <tr><td colspan="7" class="muted" style="text-align:center;padding:30px">
                        <i class="fa-regular fa-folder-open" aria-hidden="true" style="font-size:24px;display:block;margin-bottom:8px;color:var(--gold-300)"></i>
                        Nenhuma célula encontrada.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($celulas as $c):
                        $inativo = (int) $c['ativo'] === 0;
                    ?>
                        <tr style="<?= $inativo ? 'opacity:.55' : '' ?>">
                            <td>
                                <a href="<?= esc(url('celulas/' . $c['id'])) ?>"
                                   style="color:var(--brown-900);font-weight:500;text-decoration:none">
                                    <i class="fa-solid fa-people-roof" aria-hidden="true" style="color:var(--gold-600);margin-right:6px"></i>
                                    <?= esc($c['nome']) ?>
                                </a>
                            </td>
                            <td class="muted"><?= esc($c['lider_nome'] ?? '—') ?></td>
                            <td class="muted"><?= esc($c['anfitriao_nome'] ?? '—') ?></td>
                            <td class="muted">
                                <?= esc($diasLabel[$c['dia_semana']] ?? '—') ?>
                                <?php if ($c['hora']): ?> · <?= esc(substr((string) $c['hora'], 0, 5)) ?><?php endif; ?>
                            </td>
                            <td><?= (int) $c['total_membros'] ?></td>
                            <td>
                                <?php if ($inativo): ?>
                                    <span class="status off">Inactiva</span>
                                <?php else: ?>
                                    <span class="status ok">Activa</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right">
                                <a href="<?= esc(url('celulas/' . $c['id'])) ?>"
                                   class="icon-btn" title="Ver" style="display:inline-grid">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </a>
                                <?php if (Rbac::can('celulas.editar')): ?>
                                    <a href="<?= esc(url('celulas/' . $c['id'] . '/editar')) ?>"
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