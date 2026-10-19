<?php
/** @var array $user */
/** @var array $departamento */
/** @var array $membrosDoDept */
/** @var array $membrosDisponiveis */

use App\Core\Rbac;

$podeEditar = Rbac::can('departamentos.editar');
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('departamentos')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title" style="display:flex;align-items:center;gap:10px">
            <span style="width:12px;height:12px;border-radius:50%;background:<?= esc($departamento['cor'] ?: '#b8862b') ?>"></span>
            <?= esc($departamento['nome']) ?>
        </h1>
    </div>
    <?php if ($podeEditar): ?>
        <a href="<?= esc(url('departamentos/' . $departamento['id'] . '/editar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-pen" aria-hidden="true"></i>
            Editar
        </a>
    <?php endif; ?>
</div>

<section class="card" style="margin-bottom:16px;padding:22px 24px">
    <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">
        <div style="flex:1;min-width:280px">
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Descrição</div>
            <div style="font-size:14px;color:var(--brown-900)">
                <?= esc($departamento['descricao'] ?: '—') ?>
            </div>
        </div>
        <div style="display:flex;gap:28px">
            <div>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Membros</div>
                <div style="font-size:22px;font-weight:600;color:var(--brown-900)"><?= count($membrosDoDept) ?></div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Status</div>
                <div>
                    <?php if ((int) $departamento['ativo'] === 1): ?>
                        <span class="status ok">Activo</span>
                    <?php else: ?>
                        <span class="status off">Inactivo</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div class="card-title">
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            Membros do Departamento
        </div>
    </div>

    <?php if (empty($membrosDoDept)): ?>
        <p class="muted" style="padding:20px 0">Ainda não há membros associados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:60px">Foto</th>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Função</th>
                        <th>Desde</th>
                        <th style="text-align:right"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($membrosDoDept as $m):
                        $partes = preg_split('/\s+/', trim($m['nome']));
                        $iniciais = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
                    ?>
                        <tr>
                            <td>
                                <span class="avatar" style="width:34px;height:34px;display:inline-grid;place-items:center;color:var(--gold-700);font-weight:600;font-size:12px;background:var(--gold-100);border-radius:50%">
                                    <?= esc($iniciais) ?>
                                </span>
                            </td>
                            <td class="muted" style="font-size:12px"><?= esc($m['codigo']) ?></td>
                            <td>
                                <a href="<?= esc(url('membros/' . $m['id'])) ?>"
                                   style="color:var(--brown-900);font-weight:500;text-decoration:none">
                                    <?= esc($m['nome']) ?>
                                </a>
                            </td>
                            <td class="muted"><?= esc($m['funcao'] ?? 'Membro') ?></td>
                            <td class="muted"><?= $m['desde'] ? esc(date('d/m/Y', strtotime($m['desde']))) : '—' ?></td>
                            <td style="text-align:right">
                                <?php if ($podeEditar): ?>
                                    <form method="post"
                                          action="<?= esc(url('departamentos/' . $departamento['id'] . '/membros/' . $m['id'] . '/remover')) ?>"
                                          style="margin:0" onsubmit="return confirm('Remover este membro do departamento?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn" title="Remover">
                                            <i class="fa-solid fa-user-minus" aria-hidden="true" style="color:var(--danger)"></i>
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

    <?php if ($podeEditar && !empty($membrosDisponiveis)): ?>
        <h3 style="font-size:14px;margin:22px 0 12px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-user-plus" aria-hidden="true" style="color:var(--gold-600)"></i>
            Adicionar membro
        </h3>
        <form method="post" action="<?= esc(url('departamentos/' . $departamento['id'] . '/membros')) ?>"
              style="display:grid;grid-template-columns:1fr 1fr 160px auto;gap:12px">
            <?= csrf_field() ?>
            <select name="member_id" class="select" required>
                <option value="">Escolher membro...</option>
                <?php foreach ($membrosDisponiveis as $md): ?>
                    <option value="<?= (int) $md['id'] ?>">
                        <?= esc($md['nome']) ?> (<?= esc($md['codigo']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <input name="funcao" class="input" placeholder="Função (ex: Membro, Líder...)">
            <input name="desde" type="date" class="input" value="<?= date('Y-m-d') ?>">
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Adicionar
            </button>
        </form>
    <?php endif; ?>
</section>