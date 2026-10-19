<?php
/** @var array $user */
/** @var array $celula */
/** @var array $membrosDaCelula */
/** @var array $membrosDisponiveis */
/** @var array $reunioes */

use App\Core\Rbac;

$podeEditar = Rbac::can('celulas.editar');
$diasLabel = [
    'seg' => 'Segunda', 'ter' => 'Terça', 'qua' => 'Quarta',
    'qui' => 'Quinta', 'sex' => 'Sexta', 'sab' => 'Sábado', 'dom' => 'Domingo',
];

$mediaPresentes = 0;
if (!empty($reunioes)) {
    $soma = 0; $n = 0;
    foreach ($reunioes as $r) { $soma += (int) $r['presentes']; $n++; }
    $mediaPresentes = $n ? round($soma / $n, 1) : 0;
}
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('celulas')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title" style="display:flex;align-items:center;gap:10px">
            <i class="fa-solid fa-people-roof" aria-hidden="true" style="color:var(--gold-600)"></i>
            <?= esc($celula['nome']) ?>
        </h1>
    </div>
    <?php if ($podeEditar): ?>
        <a href="<?= esc(url('celulas/' . $celula['id'] . '/editar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-pen" aria-hidden="true"></i>
            Editar
        </a>
    <?php endif; ?>
</div>

<section class="card" style="margin-bottom:16px;padding:22px 24px">
    <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">
        <div style="flex:1;min-width:280px">
            <div style="display:grid;gap:8px">
                <div><span style="font-size:12px;color:var(--text-muted)">Dia / Hora:</span>
                    <span style="color:var(--brown-900);margin-left:6px">
                        <?= esc($diasLabel[$celula['dia_semana']] ?? '—') ?>
                        <?php if ($celula['hora']): ?> · <?= esc(substr((string) $celula['hora'], 0, 5)) ?><?php endif; ?>
                    </span>
                </div>
                <div><span style="font-size:12px;color:var(--text-muted)">Endereço:</span>
                    <span style="color:var(--brown-900);margin-left:6px"><?= esc($celula['endereco'] ?: '—') ?></span>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:28px">
            <div>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Membros</div>
                <div style="font-size:22px;font-weight:600;color:var(--brown-900)"><?= count($membrosDaCelula) ?></div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Reuniões</div>
                <div style="font-size:22px;font-weight:600;color:var(--brown-900)"><?= count($reunioes) ?></div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Média presentes</div>
                <div style="font-size:22px;font-weight:600;color:var(--brown-900)"><?= $mediaPresentes ?></div>
            </div>
        </div>
    </div>
</section>

<section class="grid row-2" style="margin-bottom:16px">
    <!-- Membros -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                Membros
            </div>
        </div>

        <?php if (empty($membrosDaCelula)): ?>
            <p class="muted" style="padding:20px 0">Ainda não há membros nesta célula.</p>
        <?php else: ?>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px">
                <?php foreach ($membrosDaCelula as $m): ?>
                    <li style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--gold-50);border-radius:8px">
                        <i class="fa-solid fa-user" aria-hidden="true" style="color:var(--gold-600);font-size:13px"></i>
                        <a href="<?= esc(url('membros/' . $m['id'])) ?>" style="color:var(--brown-900);text-decoration:none;font-weight:500">
                            <?= esc($m['nome']) ?>
                        </a>
                        <span class="muted" style="margin-left:auto;font-size:11.5px"><?= esc($m['codigo']) ?></span>
                        <?php if ($podeEditar): ?>
                            <form method="post" action="<?= esc(url('celulas/' . $celula['id'] . '/membros/' . $m['id'] . '/remover')) ?>" style="margin:0" onsubmit="return confirm('Remover?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="icon-btn" title="Remover" style="padding:3px 5px">
                                    <i class="fa-solid fa-xmark" aria-hidden="true" style="color:var(--danger);font-size:12px"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($podeEditar && !empty($membrosDisponiveis)): ?>
            <form method="post" action="<?= esc(url('celulas/' . $celula['id'] . '/membros')) ?>"
                  style="display:grid;grid-template-columns:1fr auto;gap:10px;margin-top:16px">
                <?= csrf_field() ?>
                <select name="member_id" class="select" required>
                    <option value="">Adicionar membro...</option>
                    <?php foreach ($membrosDisponiveis as $md): ?>
                        <option value="<?= (int) $md['id'] ?>"><?= esc($md['nome']) ?> (<?= esc($md['codigo']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-gold">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Reuniões -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                Reuniões
            </div>
        </div>

        <?php if (empty($reunioes)): ?>
            <p class="muted" style="padding:20px 0">Ainda não há reuniões registadas.</p>
        <?php else: ?>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px;max-height:400px;overflow-y:auto">
                <?php foreach ($reunioes as $r): ?>
                    <li style="display:flex;align-items:flex-start;gap:10px;padding:10px;background:var(--gold-50);border-radius:8px">
                        <div style="text-align:center;flex-shrink:0">
                            <div style="font-size:15px;font-weight:600;color:var(--gold-700)"><?= esc(date('d', strtotime($r['data']))) ?></div>
                            <div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase"><?= esc(date('M', strtotime($r['data']))) ?></div>
                        </div>
                        <div style="flex:1">
                            <div style="font-weight:500;color:var(--brown-900);font-size:13.5px"><?= esc($r['tema'] ?: 'Reunião') ?></div>
                            <div style="font-size:12px;color:var(--text-muted);margin-top:3px">
                                <i class="fa-solid fa-user-group" aria-hidden="true" style="font-size:11px"></i>
                                <?= (int) $r['presentes'] ?> presentes
                                <?php if ($r['observacoes']): ?>
                                    · <?= esc(mb_strimwidth($r['observacoes'], 0, 50, '...')) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($podeEditar): ?>
                            <form method="post" action="<?= esc(url('celulas/' . $celula['id'] . '/reunioes/' . $r['id'] . '/eliminar')) ?>" style="margin:0" onsubmit="return confirm('Eliminar reunião?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="icon-btn" style="padding:3px 5px">
                                    <i class="fa-solid fa-trash" aria-hidden="true" style="color:var(--danger);font-size:12px"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($podeEditar): ?>
            <h3 style="font-size:13.5px;margin:20px 0 10px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
                <i class="fa-solid fa-plus" aria-hidden="true" style="color:var(--gold-600)"></i>
                Registar reunião
            </h3>
            <form method="post" action="<?= esc(url('celulas/' . $celula['id'] . '/reunioes')) ?>"
                  style="display:grid;gap:10px">
                <?= csrf_field() ?>
                <div style="display:grid;grid-template-columns:140px 1fr 100px;gap:10px">
                    <input name="data" type="date" class="input" required value="<?= date('Y-m-d') ?>">
                    <input name="tema" class="input" placeholder="Tema da reunião">
                    <input name="presentes" type="number" min="0" class="input" placeholder="Presentes" value="0">
                </div>
                <textarea name="observacoes" class="input" rows="2" placeholder="Observações (opcional)"
                          style="height:auto;padding:8px 14px;resize:vertical"></textarea>
                <div>
                    <button type="submit" class="btn btn-gold">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        Registar
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>