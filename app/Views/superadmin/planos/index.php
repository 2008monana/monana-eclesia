<?php
/** @var array $supervisor */
/** @var array $planos */
?>
<div class="page-head">
    <h1 class="page-title">Planos</h1>
    <a href="<?= esc(url('superadmin/planos/criar')) ?>" class="btn btn-gold">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        Novo Plano
    </a>
</div>

<div class="card">
    <?php if (empty($planos)): ?>
        <p class="muted" style="text-align:center;padding:30px">Nenhum plano criado.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Preço</th>
                        <th>Periodicidade</th>
                        <th>Limites</th>
                        <th>Assinaturas ativas</th>
                        <th>Status</th>
                        <th style="text-align:right">Acções</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($planos as $p): ?>
                        <tr>
                            <td>
                                <strong><?= esc($p['nome']) ?></strong>
                                <div class="muted" style="font-size:11.5px"><?= esc($p['slug']) ?></div>
                            </td>
                            <td>
                                <?php if ((float) $p['preco_kz'] > 0): ?>
                                    <strong><?= fmtKz($p['preco_kz']) ?></strong>
                                <?php else: ?>
                                    <span class="status ok">Grátis</span>
                                <?php endif; ?>
                            </td>
                            <td class="muted">
                                <?= (int) $p['periodicidade_dias'] > 0
                                    ? (int) $p['periodicidade_dias'] . ' dias'
                                    : '—' ?>
                            </td>
                            <td class="muted" style="font-size:12px">
                                <?= $p['limite_membros'] !== null ? $p['limite_membros'] . ' membros' : 'ilimitado' ?>
                                ·
                                <?= $p['limite_users'] !== null ? $p['limite_users'] . ' users' : 'ilimitado' ?>
                            </td>
                            <td><strong><?= (int) $p['total_assinaturas'] ?></strong></td>
                            <td>
                                <?php if ((int) $p['ativo'] === 1): ?>
                                    <span class="status ok">Ativo</span>
                                <?php else: ?>
                                    <span class="status off">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right">
                                <a href="<?= esc(url('superadmin/planos/' . $p['id'] . '/editar')) ?>"
                                   class="icon-btn" title="Editar" style="display:inline-grid">
                                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                </a>
                                <?php if ((int) $p['ativo'] === 1): ?>
                                    <form method="post" action="<?= esc(url('superadmin/planos/' . $p['id'] . '/eliminar')) ?>"
                                          style="margin:0;display:inline-grid" onsubmit="return confirm('Desativar este plano?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn" title="Desativar">
                                            <i class="fa-solid fa-ban" aria-hidden="true" style="color:var(--danger)"></i>
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