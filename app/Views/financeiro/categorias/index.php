<?php
/** @var array $user */
/** @var array $categorias */
/** @var string $tipo */

use App\Core\Rbac;

$podeCriar = Rbac::can('financeiro.criar');
$podeEditar = Rbac::can('financeiro.editar');
$podeElim  = Rbac::can('financeiro.eliminar');
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('financeiro')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title">Categorias Financeiras</h1>
    </div>
    <?php if ($podeCriar): ?>
        <a href="<?= esc(url('financeiro/categorias/criar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Nova Categoria
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= esc(url('financeiro/categorias')) ?>" style="display:flex;gap:12px">
        <select class="select" name="tipo" style="max-width:200px" onchange="this.form.submit()">
            <option value="">Todos os tipos</option>
            <option value="entrada" <?= $tipo === 'entrada' ? 'selected' : '' ?>>Entradas</option>
            <option value="despesa" <?= $tipo === 'despesa' ? 'selected' : '' ?>>Despesas</option>
        </select>
    </form>

    <div class="table-wrap" style="margin-top:14px">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:50px"></th>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Transações</th>
                    <th>Status</th>
                    <th style="text-align:right">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categorias)): ?>
                    <tr><td colspan="6" class="muted" style="text-align:center;padding:30px">Nenhuma categoria criada.</td></tr>
                <?php else: ?>
                    <?php foreach ($categorias as $c):
                        $inativo = (int) $c['ativo'] === 0;
                    ?>
                        <tr style="<?= $inativo ? 'opacity:.55' : '' ?>">
                            <td>
                                <span style="width:28px;height:28px;display:inline-grid;place-items:center;border-radius:50%;background:<?= esc($c['cor']) ?>;color:#fff;font-size:12px">
                                    <i class="fa-solid <?= esc($c['icone'] ?: 'fa-tag') ?>" aria-hidden="true"></i>
                                </span>
                            </td>
                            <td><strong style="color:var(--brown-900)"><?= esc($c['nome']) ?></strong></td>
                            <td>
                                <?php if ($c['tipo'] === 'entrada'): ?>
                                    <span class="status ok">Entrada</span>
                                <?php else: ?>
                                    <span class="status off">Despesa</span>
                                <?php endif; ?>
                            </td>
                            <td class="muted"><?= (int) $c['total_transacoes'] ?></td>
                            <td>
                                <?php if ($inativo): ?>
                                    <span class="status off">Inactiva</span>
                                <?php else: ?>
                                    <span class="status ok">Activa</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right">
                                <?php if ($podeEditar): ?>
                                    <a href="<?= esc(url('financeiro/categorias/' . $c['id'] . '/editar')) ?>"
                                       class="icon-btn" title="Editar" style="display:inline-grid">
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($podeElim && !$inativo): ?>
                                    <form method="post"
                                          action="<?= esc(url('financeiro/categorias/' . $c['id'] . '/eliminar')) ?>"
                                          style="margin:0;display:inline-grid" onsubmit="return confirm('Desativar categoria?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn" title="Desativar">
                                            <i class="fa-solid fa-ban" aria-hidden="true" style="color:var(--danger)"></i>
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
</div>