<?php
/** @var array $user */
/** @var array $usuarios */
/** @var string $q */

use App\Core\Rbac;

$podeCriar = Rbac::can('utilizadores.criar');
?>
<div class="page-head">
    <h1 class="page-title">Usuários</h1>
    <?php if ($podeCriar): ?>
        <a href="<?= esc(url('usuarios/criar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
            Novo Usuário
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="<?= esc(url('usuarios')) ?>">
        <div class="field" style="position:relative">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"
               style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-soft);font-size:13px"></i>
            <input class="input" name="q" placeholder="Pesquisar por nome ou e-mail..." value="<?= esc($q) ?>"
                   style="padding-left:38px">
        </div>
    </form>

    <div class="table-wrap" style="margin-top:14px">
        <table class="table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Roles</th>
                    <th>Último login</th>
                    <th>Status</th>
                    <th style="text-align:right">Acções</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                    <tr><td colspan="6" class="muted" style="text-align:center;padding:30px">Nenhum usuário.</td></tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $u):
                        $inativo = (int) $u['ativo'] === 0;
                    ?>
                        <tr style="<?= $inativo ? 'opacity:.55' : '' ?>">
                            <td>
                                <a href="<?= esc(url('usuarios/' . $u['id'])) ?>"
                                   style="color:var(--brown-900);font-weight:500;text-decoration:none">
                                    <?= esc($u['nome']) ?>
                                </a>
                            </td>
                            <td class="muted"><?= esc($u['email']) ?></td>
                            <td>
                                <?php if ($u['roles_nomes']): ?>
                                    <?php foreach (explode(', ', $u['roles_nomes']) as $rn): ?>
                                        <span style="background:var(--gold-100);color:var(--gold-700);padding:2px 8px;border-radius:999px;font-size:11px;font-weight:500;margin-right:4px">
                                            <?= esc($rn) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="muted" style="font-size:12.5px">
                                <?= $u['ultimo_login'] ? esc(date('d/m/Y H:i', strtotime($u['ultimo_login']))) : '—' ?>
                            </td>
                            <td>
                                <?php if ($inativo): ?>
                                    <span class="status off">Inactivo</span>
                                <?php else: ?>
                                    <span class="status ok">Activo</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right">
                                <a href="<?= esc(url('usuarios/' . $u['id'])) ?>"
                                   class="icon-btn" title="Ver" style="display:inline-grid">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </a>
                                <?php if (Rbac::can('utilizadores.editar')): ?>
                                    <a href="<?= esc(url('usuarios/' . $u['id'] . '/editar')) ?>"
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