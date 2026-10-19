<?php
/** @var array $user */
/** @var array $eventos */
/** @var array $porDia */
/** @var array $proximos */
/** @var int $mes */
/** @var int $ano */
/** @var string $tipo */
/** @var array $tipos */

use App\Core\Rbac;

$podeCriar = Rbac::can('eventos.criar');

$meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
         'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$diasSemana = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

// Navegação do mês anterior/próximo
$prevMes = $mes - 1; $prevAno = $ano;
if ($prevMes < 1) { $prevMes = 12; $prevAno--; }
$nextMes = $mes + 1; $nextAno = $ano;
if ($nextMes > 12) { $nextMes = 1; $nextAno++; }

// Construção do calendário (semanas começam na segunda)
$primeiroDia = mktime(0, 0, 0, $mes, 1, $ano);
$dowPrimeiro = (int) date('N', $primeiroDia); // 1=segunda ... 7=domingo
$diasNoMes   = (int) date('t', $primeiroDia);
$hoje        = date('Y-m-d');

$celulas = [];
// Células vazias no início
for ($i = 1; $i < $dowPrimeiro; $i++) {
    $celulas[] = null;
}
for ($d = 1; $d <= $diasNoMes; $d++) {
    $celulas[] = sprintf('%04d-%02d-%02d', $ano, $mes, $d);
}
// Completa até múltiplo de 7
while (count($celulas) % 7 !== 0) {
    $celulas[] = null;
}

$tipoIcones = [
    'Culto'       => 'fa-church',
    'Juventude'   => 'fa-fire',
    'Ensino'      => 'fa-book-open',
    'Mulheres'    => 'fa-female',
    'Homens'      => 'fa-male',
    'Evangelismo' => 'fa-bullhorn',
    'Outro'       => 'fa-star',
];
?>
<div class="page-head">
    <h1 class="page-title">Eventos</h1>
    <?php if ($podeCriar): ?>
        <a href="<?= esc(url('eventos/criar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Novo Evento
        </a>
    <?php endif; ?>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom:16px">
    <form method="get" action="<?= esc(url('eventos')) ?>"
          style="display:grid;grid-template-columns:1fr 200px auto;gap:12px">
        <input class="input" name="mes" type="hidden" value="<?= (int) $mes ?>">
        <input class="input" name="ano" type="hidden" value="<?= (int) $ano ?>">
        <select class="select" name="tipo">
            <option value="">Todos os tipos</option>
            <?php foreach ($tipos as $t): ?>
                <option value="<?= esc($t) ?>" <?= $tipo === $t ? 'selected' : '' ?>><?= esc($t) ?></option>
            <?php endforeach; ?>
        </select>
        <div></div>
        <button type="submit" class="btn btn-outline">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            Filtrar
        </button>
    </form>
</div>

<div class="grid row-2" style="gap:16px">
    <!-- Calendário -->
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px">
            <div style="display:flex;align-items:center;gap:14px">
                <a href="?<?= http_build_query(['mes' => $prevMes, 'ano' => $prevAno, 'tipo' => $tipo]) ?>"
                   class="icon-btn" style="text-decoration:none">
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </a>
                <strong style="font-size:16px;color:var(--brown-900)">
                    <?= $meses[$mes] ?> <?= $ano ?>
                </strong>
                <a href="?<?= http_build_query(['mes' => $nextMes, 'ano' => $nextAno, 'tipo' => $tipo]) ?>"
                   class="icon-btn" style="text-decoration:none">
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </a>
            </div>
            <a href="<?= esc(url('eventos')) ?>" class="link-more" style="font-size:12.5px;color:var(--gold-700);text-decoration:none">
                Hoje
            </a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(7, 1fr);text-align:center;gap:1px">
            <?php foreach ($diasSemana as $ds): ?>
                <div style="font-size:11.5px;font-weight:600;color:var(--brown-800);padding:8px 0;border-bottom:1px solid var(--line-soft)">
                    <?= $ds ?>
                </div>
            <?php endforeach; ?>

            <?php foreach ($celulas as $dia):
                if ($dia === null): ?>
                    <div style="min-height:64px;background:var(--ivory-bg)"></div>
                <?php else:
                    $isHoje = $dia === $hoje;
                    $eventosDia = $porDia[$dia] ?? [];
                ?>
                    <div style="min-height:64px;padding:4px;border:1px solid var(--line-soft);border-radius:4px;position:relative;<?= $isHoje ? 'background:var(--gold-100)' : '' ?>">
                        <div style="font-size:12px;font-weight:600;color:<?= $isHoje ? 'var(--gold-800)' : 'var(--brown-900)' ?>;text-align:left;padding:2px 4px">
                            <?= (int) date('d', strtotime($dia)) ?>
                        </div>
                        <?php foreach (array_slice($eventosDia, 0, 2) as $e): ?>
                            <a href="<?= esc(url('eventos/' . $e['id'])) ?>"
                               style="display:block;font-size:10.5px;padding:2px 4px;margin-top:2px;background:var(--gold-500);color:#fff;border-radius:3px;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                               title="<?= esc($e['titulo']) ?>">
                                <?= esc(mb_strimwidth($e['titulo'], 0, 14, '…')) ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($eventosDia) > 2): ?>
                            <div style="font-size:10px;color:var(--gold-700);text-align:right;padding:2px 4px">
                                +<?= count($eventosDia) - 2 ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Próximos eventos -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                Próximos Eventos
            </div>
        </div>

        <?php if (empty($proximos)): ?>
            <div style="text-align:center;padding:24px 10px;color:var(--text-muted)">
                <i class="fa-regular fa-calendar-xmark" aria-hidden="true"
                   style="font-size:28px;color:var(--gold-300);display:block;margin-bottom:8px"></i>
                Sem eventos agendados.
            </div>
        <?php else: ?>
            <?php foreach ($proximos as $e):
                $ts = strtotime($e['data']);
                $diaNum = date('d', $ts);
                $mesAbrev = date('M', $ts);
            ?>
                <a href="<?= esc(url('eventos/' . $e['id'])) ?>"
                   style="display:flex;align-items:center;gap:14px;padding:10px 0;border-top:1px solid var(--line-soft);text-decoration:none;color:inherit">
                    <div style="width:44px;height:44px;border-radius:8px;background:var(--gold-50);border:1px solid var(--gold-100);display:grid;place-content:center;text-align:center;color:var(--gold-700);line-height:1;flex-shrink:0">
                        <b style="font-size:16px;font-weight:600"><?= esc($diaNum) ?></b>
                        <small style="font-size:11px;margin-top:2px"><?= esc($mesAbrev) ?></small>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13.5px;color:var(--brown-900);font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            <?= esc($e['titulo']) ?>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:3px">
                            <i class="fa-regular fa-clock" aria-hidden="true" style="font-size:11px"></i>
                            <?= esc($e['hora'] ?? '—') ?>
                            · <i class="fa-solid fa-location-dot" aria-hidden="true" style="font-size:11px"></i>
                            <?= esc($e['local'] ?? '—') ?>
                        </div>
                    </div>
                    <?php if ($e['tipo']): ?>
                        <i class="fa-solid <?= $tipoIcones[$e['tipo']] ?? 'fa-star' ?>"
                           aria-hidden="true" style="color:var(--gold-600);font-size:14px"></i>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>