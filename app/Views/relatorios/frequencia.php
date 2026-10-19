<?php
/** @var array $user */
/** @var array $porEvento */
/** @var array $porMembro */
/** @var array $porCelula */
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('relatorios')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title">Relatório de Frequência</h1>
    </div>
</div>

<div class="grid row-2" style="gap:16px;margin-bottom:16px">
    <!-- Por evento -->
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Últimos eventos</div>
        </div>
        <?php if (empty($porEvento)): ?>
            <p class="muted">Sem eventos realizados.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Evento</th><th>Data</th><th style="text-align:right">Presentes</th></tr></thead>
                    <tbody>
                        <?php foreach ($porEvento as $e):
                            $pct = $e['total_membros'] > 0 ? round(($e['presentes'] / $e['total_membros']) * 100) : 0;
                        ?>
                            <tr>
                                <td>
                                    <a href="<?= esc(url('eventos/' . $e['id'])) ?>" style="color:var(--brown-900);text-decoration:none;font-weight:500">
                                        <?= esc($e['titulo']) ?>
                                    </a>
                                </td>
                                <td class="muted"><?= esc(date('d/m/Y', strtotime($e['data']))) ?></td>
                                <td style="text-align:right">
                                    <strong><?= (int) $e['presentes'] ?></strong>
                                    <span class="muted" style="font-size:11.5px">/ <?= (int) $e['total_membros'] ?> (<?= $pct ?>%)</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Por célula -->
    <div class="card">
        <div class="card-head">
            <div class="card-title"><i class="fa-solid fa-people-roof" aria-hidden="true"></i> Células (últimos 90 dias)</div>
        </div>
        <?php if (empty($porCelula)): ?>
            <p class="muted">Sem células ativas.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Célula</th><th style="text-align:right">Membros</th><th style="text-align:right">Reuniões</th></tr></thead>
                    <tbody>
                        <?php foreach ($porCelula as $c): ?>
                            <tr>
                                <td>
                                    <a href="<?= esc(url('celulas/' . $c['id'])) ?>" style="color:var(--brown-900);text-decoration:none;font-weight:500">
                                        <?= esc($c['nome']) ?>
                                    </a>
                                </td>
                                <td style="text-align:right"><?= (int) $c['total_membros'] ?></td>
                                <td style="text-align:right"><?= (int) $c['total_reunioes'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Por membro -->
<div class="card">
    <div class="card-head">
        <div class="card-title"><i class="fa-solid fa-user-check" aria-hidden="true"></i> Top 30 membros mais presentes (últimos 90 dias)</div>
    </div>
    <?php if (empty($porMembro)): ?>
        <p class="muted">Sem dados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>Membro</th><th style="text-align:right">Presenças</th><th style="text-align:right">Taxa</th></tr></thead>
                <tbody>
                    <?php foreach ($porMembro as $i => $m):
                        $pct = $m['total_eventos'] > 0 ? round(($m['presencas'] / $m['total_eventos']) * 100) : 0;
                    ?>
                        <tr>
                            <td><strong><?= $i + 1 ?>º</strong></td>
                            <td>
                                <a href="<?= esc(url('membros/' . $m['id'])) ?>" style="color:var(--brown-900);text-decoration:none;font-weight:500">
                                    <?= esc($m['nome']) ?>
                                </a>
                                <span class="muted" style="font-size:11.5px">(<?= esc($m['codigo']) ?>)</span>
                            </td>
                            <td style="text-align:right"><strong><?= (int) $m['presencas'] ?></strong> / <?= (int) $m['total_eventos'] ?></td>
                            <td style="text-align:right">
                                <div style="display:inline-block;width:60px;background:var(--gold-100);height:6px;border-radius:999px;overflow:hidden;vertical-align:middle;margin-right:8px">
                                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct >= 75 ? 'var(--success)' : ($pct >= 40 ? 'var(--gold-500)' : 'var(--danger)') ?>"></div>
                                </div>
                                <span class="muted" style="font-size:12px"><?= $pct ?>%</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>