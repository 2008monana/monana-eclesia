<?php
/** @var array $user */
/** @var array $fechos */

$meses = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('financeiro')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title">Fecho Mensal</h1>
    </div>
</div>

<section class="card" style="margin-bottom:16px">
    <div class="card-head">
        <div class="card-title">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            Executar fecho
        </div>
    </div>
    <p class="muted" style="margin:0 0 14px;font-size:13px">
        O fecho consolida entradas e despesas do mês e <strong>bloqueia</strong> novas transações nesse período.
    </p>
    <form method="post" action="<?= esc(url('financeiro/fecho')) ?>"
          style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <?= csrf_field() ?>
        <div>
            <label class="lb">Mês</label>
            <select class="select" name="mes" required style="width:150px">
                <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?= $i ?>" <?= $i === (int) date('n') ? 'selected' : '' ?>><?= $meses[$i] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label class="lb">Ano</label>
            <select class="select" name="ano" required style="width:120px">
                <?php for ($i = (int) date('Y') - 2; $i <= (int) date('Y'); $i++): ?>
                    <option value="<?= $i ?>" <?= $i === (int) date('Y') ? 'selected' : '' ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-gold" onclick="return confirm('Confirmar fecho do mês?')">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            Fechar mês
        </button>
    </form>
</section>

<section class="card">
    <div class="card-head">
        <div class="card-title">
            <i class="fa-solid fa-list" aria-hidden="true"></i>
            Fechos realizados
        </div>
    </div>
    <?php if (empty($fechos)): ?>
        <p class="muted" style="margin:0;padding:16px 0">Nenhum fecho realizado.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th style="text-align:right">Entradas</th>
                        <th style="text-align:right">Despesas</th>
                        <th style="text-align:right">Saldo</th>
                        <th>Fechado por</th>
                        <th>Quando</th>
                        <th style="text-align:right"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fechos as $f): ?>
                        <tr>
                            <td>
                                <strong><?= esc($meses[(int) $f['mes']]) ?> <?= (int) $f['ano'] ?></strong>
                            </td>
                            <td style="text-align:right;color:var(--success)">+<?= fmtKz($f['total_entradas']) ?></td>
                            <td style="text-align:right;color:var(--danger)">−<?= fmtKz($f['total_despesas']) ?></td>
                            <td style="text-align:right;font-weight:600;color:<?= (float) $f['saldo'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                                <?= fmtKz($f['saldo']) ?>
                            </td>
                            <td class="muted"><?= esc($f['fechado_por_nome'] ?? '—') ?></td>
                            <td class="muted" style="font-size:12.5px"><?= esc(date('d/m/Y H:i', strtotime($f['fechado_em']))) ?></td>
                            <td style="text-align:right">
                                <form method="post"
                                      action="<?= esc(url('financeiro/fecho/' . $f['id'] . '/reabrir')) ?>"
                                      style="margin:0"
                                      onsubmit="return confirm('Reabrir este mês? Permitirá editar transações novamente.')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="icon-btn" title="Reabrir">
                                        <i class="fa-solid fa-lock-open" aria-hidden="true" style="color:var(--gold-600)"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>