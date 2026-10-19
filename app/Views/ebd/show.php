<?php
/** @var array $user */
/** @var array $classe */
/** @var array $alunos */
/** @var array $professores */
/** @var array $membrosDisponiveis */

use App\Core\Rbac;

$podeEditar = Rbac::can('ebd.editar');
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('ebd')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title" style="display:flex;align-items:center;gap:10px">
            <i class="fa-solid fa-book-open" aria-hidden="true" style="color:var(--gold-600)"></i>
            <?= esc($classe['nome']) ?>
        </h1>
    </div>
    <?php if ($podeEditar): ?>
        <a href="<?= esc(url('ebd/' . $classe['id'] . '/editar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-pen" aria-hidden="true"></i>
            Editar
        </a>
    <?php endif; ?>
</div>

<section class="card" style="margin-bottom:16px;padding:22px 24px">
    <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">
        <div style="flex:1;min-width:280px">
            <div style="display:grid;gap:8px">
                <div>
                    <span style="font-size:12px;color:var(--text-muted)">Faixa etária:</span>
                    <span style="color:var(--brown-900);margin-left:6px"><?= esc($classe['faixa_etaria'] ?: '—') ?></span>
                </div>
                <div>
                    <span style="font-size:12px;color:var(--text-muted)">Sala:</span>
                    <span style="color:var(--brown-900);margin-left:6px"><?= esc($classe['sala'] ?: '—') ?></span>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:28px">
            <div>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Alunos</div>
                <div style="font-size:22px;font-weight:600;color:var(--brown-900)"><?= count($alunos) ?></div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Professores</div>
                <div style="font-size:22px;font-weight:600;color:var(--brown-900)"><?= count($professores) ?></div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Status</div>
                <div>
                    <?php if ((int) $classe['ativo'] === 1): ?>
                        <span class="status ok">Activa</span>
                    <?php else: ?>
                        <span class="status off">Inactiva</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="grid row-2" style="gap:16px">
    <!-- Professores -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i>
                Professores
            </div>
        </div>

        <?php if (empty($professores)): ?>
            <p class="muted" style="padding:10px 0">Nenhum professor atribuído.</p>
        <?php else: ?>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px">
                <?php foreach ($professores as $p): ?>
                    <li style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--gold-50);border-radius:8px">
                        <i class="fa-solid fa-user-tie" aria-hidden="true"
                           style="color:var(--gold-700);font-size:13px"></i>
                        <a href="<?= esc(url('membros/' . $p['id'])) ?>"
                           style="color:var(--brown-900);text-decoration:none;font-weight:500">
                            <?= esc($p['nome']) ?>
                        </a>
                        <span class="muted" style="margin-left:auto;font-size:11.5px"><?= esc($p['codigo']) ?></span>
                        <?php if ($podeEditar): ?>
                            <form method="post"
                                  action="<?= esc(url('ebd/' . $classe['id'] . '/professores/' . $p['id'] . '/remover')) ?>"
                                  style="margin:0" onsubmit="return confirm('Remover professor?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="icon-btn" style="padding:3px 5px">
                                    <i class="fa-solid fa-xmark" aria-hidden="true"
                                       style="color:var(--danger);font-size:12px"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($podeEditar && !empty($membrosDisponiveis)): ?>
            <form method="post" action="<?= esc(url('ebd/' . $classe['id'] . '/professores')) ?>"
                  style="display:grid;grid-template-columns:1fr auto;gap:10px;margin-top:14px">
                <?= csrf_field() ?>
                <select name="member_id" class="select" required>
                    <option value="">Adicionar professor...</option>
                    <?php foreach ($membrosDisponiveis as $md): ?>
                        <option value="<?= (int) $md['id'] ?>"><?= esc($md['nome']) ?> (<?= esc($md['codigo']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-gold" title="Adicionar">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Alunos -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                Alunos
            </div>
        </div>

        <?php if (empty($alunos)): ?>
            <p class="muted" style="padding:10px 0">Nenhum aluno matriculado.</p>
        <?php else: ?>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px">
                <?php foreach ($alunos as $a): ?>
                    <li style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--gold-50);border-radius:8px">
                        <i class="fa-solid fa-user" aria-hidden="true"
                           style="color:var(--gold-600);font-size:13px"></i>
                        <a href="<?= esc(url('membros/' . $a['id'])) ?>"
                           style="color:var(--brown-900);text-decoration:none;font-weight:500">
                            <?= esc($a['nome']) ?>
                        </a>
                        <span class="muted" style="margin-left:auto;font-size:11.5px"><?= esc($a['codigo']) ?></span>
                        <?php if ($podeEditar): ?>
                            <form method="post"
                                  action="<?= esc(url('ebd/' . $classe['id'] . '/alunos/' . $a['id'] . '/remover')) ?>"
                                  style="margin:0" onsubmit="return confirm('Remover aluno?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="icon-btn" style="padding:3px 5px">
                                    <i class="fa-solid fa-xmark" aria-hidden="true"
                                       style="color:var(--danger);font-size:12px"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($podeEditar && !empty($membrosDisponiveis)): ?>
            <form method="post" action="<?= esc(url('ebd/' . $classe['id'] . '/alunos')) ?>"
                  style="display:grid;grid-template-columns:1fr auto;gap:10px;margin-top:14px">
                <?= csrf_field() ?>
                <select name="member_id" class="select" required>
                    <option value="">Adicionar aluno...</option>
                    <?php foreach ($membrosDisponiveis as $md): ?>
                        <option value="<?= (int) $md['id'] ?>"><?= esc($md['nome']) ?> (<?= esc($md['codigo']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-gold" title="Adicionar">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>