<?php
/** @var array $user */
/** @var array $evento */
/** @var array $membros */
/** @var array $presencas */
/** @var int $totalPresentes */
/** @var int $totalMembros */

use App\Core\Rbac;

$podeMarcar = Rbac::can('eventos.registar_presenca');
$podeEditar = Rbac::can('eventos.editar');
$podeElim   = Rbac::can('eventos.eliminar');

$tipoIcones = [
    'Culto'       => 'fa-church',
    'Juventude'   => 'fa-fire',
    'Ensino'      => 'fa-book-open',
    'Mulheres'    => 'fa-female',
    'Homens'      => 'fa-male',
    'Evangelismo' => 'fa-bullhorn',
    'Outro'       => 'fa-star',
];

$percentagem = $totalMembros > 0 ? round(($totalPresentes / $totalMembros) * 100) : 0;
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('eventos')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title" style="display:flex;align-items:center;gap:10px">
            <?php if ($evento['tipo']): ?>
                <i class="fa-solid <?= $tipoIcones[$evento['tipo']] ?? 'fa-star' ?>" aria-hidden="true" style="color:var(--gold-600)"></i>
            <?php endif; ?>
            <?= esc($evento['titulo']) ?>
        </h1>
    </div>
    <div style="display:flex;gap:10px">
        <?php if ($podeEditar): ?>
            <a href="<?= esc(url('eventos/' . $evento['id'] . '/editar')) ?>" class="btn btn-gold">
                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                Editar
            </a>
        <?php endif; ?>
        <?php if ($podeElim): ?>
            <form method="post" action="<?= esc(url('eventos/' . $evento['id'] . '/eliminar')) ?>"
                  style="margin:0" onsubmit="return confirm('Eliminar este evento?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline" style="color:var(--danger)">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<section class="card" style="margin-bottom:16px;padding:22px 24px">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <div>
            <div style="display:grid;gap:8px;font-size:13.5px">
                <div>
                    <span style="font-size:12px;color:var(--text-muted)"><i class="fa-regular fa-calendar" aria-hidden="true"></i> Data:</span>
                    <strong style="color:var(--brown-900);margin-left:6px">
                        <?= esc(date('d/m/Y', strtotime($evento['data']))) ?>
                    </strong>
                </div>
                <?php if ($evento['hora']): ?>
                    <div>
                        <span style="font-size:12px;color:var(--text-muted)"><i class="fa-regular fa-clock" aria-hidden="true"></i> Hora:</span>
                        <strong style="color:var(--brown-900);margin-left:6px"><?= esc(substr($evento['hora'], 0, 5)) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if ($evento['local']): ?>
                    <div>
                        <span style="font-size:12px;color:var(--text-muted)"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Local:</span>
                        <strong style="color:var(--brown-900);margin-left:6px"><?= esc($evento['local']) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if ($evento['tipo']): ?>
                    <div>
                        <span style="font-size:12px;color:var(--text-muted)"><i class="fa-solid fa-tag" aria-hidden="true"></i> Tipo:</span>
                        <strong style="color:var(--brown-900);margin-left:6px"><?= esc($evento['tipo']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div style="border-left:1px solid var(--line);padding-left:24px">
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Presenças</div>
            <div style="font-size:28px;font-weight:600;color:var(--brown-900)">
                <?= $totalPresentes ?> <span style="font-size:16px;color:var(--text-muted)">/ <?= $totalMembros ?></span>
            </div>
            <div style="background:var(--gold-100);border-radius:999px;height:8px;overflow:hidden;margin-top:8px">
                <div style="width:<?= $percentagem ?>%;height:100%;background:var(--gold-500);transition:width .3s"></div>
            </div>
            <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px"><?= $percentagem ?>% dos membros ativos</div>
        </div>
    </div>

    <?php if ($evento['descricao']): ?>
        <div style="margin-top:18px;padding:14px;background:var(--gold-50);border-radius:8px;font-size:13.5px;color:var(--brown-800)">
            <?= nl2br(esc($evento['descricao'])) ?>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head">
        <div class="card-title">
            <i class="fa-solid fa-user-check" aria-hidden="true"></i>
            Lista de Presenças
        </div>
        <?php if ($podeMarcar): ?>
            <form method="post" action="<?= esc(url('eventos/' . $evento['id'] . '/presencas')) ?>"
                  style="margin:0" onsubmit="return confirm('Marcar TODOS os membros ativos como presentes?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline" style="font-size:12.5px">
                    <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                    Marcar todos
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($membros)): ?>
        <p class="muted">Nenhum membro ativo para registar presença.</p>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:10px">
            <?php foreach ($membros as $m):
                $presente = $presencas[(int) $m['id']] ?? false;
            ?>
                <form method="post"
                      action="<?= esc(url('eventos/' . $evento['id'] . '/presenca/' . $m['id'])) ?>"
                      style="margin:0">
                    <?= csrf_field() ?>
                    <button type="submit"
                            style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;border:1px solid <?= $presente ? 'var(--success)' : 'var(--line)' ?>;background:<?= $presente ? 'var(--success-bg)' : 'var(--ivory-surface)' ?>;width:100%;text-align:left;cursor:pointer;transition:all .15s">
                        <span style="width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:<?= $presente ? 'var(--success)' : 'var(--gold-100)' ?>;color:<?= $presente ? '#fff' : 'var(--gold-700)' ?>;flex-shrink:0">
                            <i class="fa-solid <?= $presente ? 'fa-check' : 'fa-user' ?>" aria-hidden="true" style="font-size:11px"></i>
                        </span>
                        <span style="flex:1;min-width:0">
                            <span style="display:block;font-size:13px;font-weight:500;color:var(--brown-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                <?= esc($m['nome']) ?>
                            </span>
                            <span style="display:block;font-size:11px;color:var(--text-muted);margin-top:2px">
                                <?= esc($m['codigo']) ?>
                            </span>
                        </span>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>