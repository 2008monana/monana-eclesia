<?php
/** @var array $user */
/** @var int $mes */
/** @var array $aniversariantes */

use App\Core\Rbac;

$meses = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$podeExportar = Rbac::can('relatorios.exportar');
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('relatorios')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title">Aniversariantes — <?= $meses[$mes] ?></h1>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
        <form method="get" style="margin:0">
            <select class="select" name="mes" onchange="this.form.submit()" style="width:160px">
                <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?= $i ?>" <?= $i === $mes ? 'selected' : '' ?>><?= $meses[$i] ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <?php if ($podeExportar): ?>
            <a href="<?= esc(url('relatorios/exportar/aniversariantes')) ?>" class="btn btn-gold">
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                Exportar CSV
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <?php if (empty($aniversariantes)): ?>
        <div style="text-align:center;padding:40px">
            <i class="fa-solid fa-cake-candles" aria-hidden="true" style="font-size:48px;color:var(--gold-300)"></i>
            <p class="muted" style="margin:12px 0 0">Nenhum aniversariante em <?= $meses[$mes] ?>.</p>
        </div>
    <?php else: ?>
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
            <strong style="color:var(--brown-900);font-size:18px"><?= count($aniversariantes) ?></strong> aniversariante(s) neste mês
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dia</th>
                        <th>Nome</th>
                        <th>Código</th>
                        <th>Idade</th>
                        <th>Telefone</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aniversariantes as $a):
                        $dia = (int) date('j', strtotime($a['data_nascimento']));
                        $isHoje = (int) date('j') === $dia && (int) date('n') === $mes;
                    ?>
                        <tr <?= $isHoje ? 'style="background:var(--gold-100)"' : '' ?>>
                            <td style="font-size:16px;font-weight:600;color:var(--gold-700);width:60px">
                                <?= $dia ?>
                                <?php if ($isHoje): ?>
                                    <i class="fa-solid fa-cake-candles" aria-hidden="true" style="color:var(--gold-500);font-size:12px;margin-left:4px"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= esc(url('membros/' . $a['id'])) ?>" style="color:var(--brown-900);text-decoration:none;font-weight:500">
                                    <?= esc($a['nome']) ?>
                                </a>
                            </td>
                            <td class="muted"><?= esc($a['codigo']) ?></td>
                            <td><?= (int) $a['idade'] ?> anos</td>
                            <td class="muted"><?= esc($a['telefone'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>