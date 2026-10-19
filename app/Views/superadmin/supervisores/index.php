<?php
/** @var array $supervisor */
/** @var array $supervisores */
?>
<div class="page-head">
    <h1 class="page-title">Supervisores</h1>
    <a href="<?= esc(url('superadmin/supervisores/criar')) ?>" class="btn btn-gold">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        Novo Supervisor
    </a>
</div>

<div class="card">
    <?php if (empty($supervisores)): ?>
        <p class="muted" style="text-align:center;padding:30px">Nenhum supervisor registado.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Nível</th>
                        <th>Igrejas</th>
                        <th>Último login</th>
                        <th>Status</th>
                        <th style="text-align:right">Acções</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($supervisores as $s): ?>
                        <tr>
                            <td><strong><?= esc($s['nome']) ?></strong></td>
                            <td class="muted"><?= esc($s['email']) ?></td>
                            <td>
                                <span style="background:var(--gold-100);color:var(--gold-700);padding:2px 8px;border-radius:999px;font-size:11px;font-weight:500">
                                    <?= esc(ucfirst($s['nivel'])) ?>
                                </span>
                            </td>
                            <td><?= (int) $s['total_igrejas'] ?></td>
                            <td class="muted" style="font-size:12px">
                                <?= $s['ultimo_login'] ? esc(date('d/m/Y H:i', strtotime($s['ultimo_login']))) : '—' ?>
                            </td>
                            <td>
                                <?php if ((int) $s['ativo'] === 1): ?>
                                    <span class="status ok">Ativo</span>
                                <?php else: ?>
                                    <span class="status off">Bloqueado</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right">
                                <a href="<?= esc(url('superadmin/supervisores/' . $s['id'] . '/editar')) ?>"
                                   class="icon-btn" title="Editar" style="display:inline-grid">
                                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                </a>
                                <?php if ((int) $s['id'] !== (int) $supervisor['id']): ?>
                                    <form method="post" action="<?= esc(url('superadmin/supervisores/' . $s['id'] . '/bloquear')) ?>"
                                          style="margin:0;display:inline-grid"
                                          onsubmit="return confirm('<?= $s['ativo'] ? 'Bloquear' : 'Reativar' ?> este supervisor?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn" title="<?= $s['ativo'] ? 'Bloquear' : 'Reativar' ?>">
                                            <i class="fa-solid <?= $s['ativo'] ? 'fa-lock' : 'fa-lock-open' ?>" aria-hidden="true" style="color:<?= $s['ativo'] ? 'var(--danger)' : 'var(--success)' ?>"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>