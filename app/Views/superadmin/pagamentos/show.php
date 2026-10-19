<?php
/** @var array $supervisor */
/** @var array $pagamento */

$estadoLabel = ['pendente' => 'Pendente', 'confirmado' => 'Confirmado', 'rejeitado' => 'Rejeitado', 'reembolsado' => 'Reembolsado'];
$estadoClass = ['pendente' => 'off', 'confirmado' => 'ok', 'rejeitado' => 'off', 'reembolsado' => 'off'];
$isPendente = $pagamento['estado'] === 'pendente';
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('superadmin/pagamentos')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title">Pagamento #<?= (int) $pagamento['id'] ?></h1>
    </div>
    <div style="display:flex;gap:8px">
        <?php if ($isPendente): ?>
            <form method="post" action="<?= esc(url('superadmin/pagamentos/' . $pagamento['id'] . '/confirmar')) ?>"
                  style="margin:0" onsubmit="return confirm('Confirmar este pagamento e renovar a assinatura?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-gold">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Confirmar
                </button>
            </form>
            <button type="button" class="btn btn-outline" style="color:var(--danger)"
                    onclick="document.getElementById('modal-rejeitar').style.display='flex'">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                Rejeitar
            </button>
        <?php endif; ?>
    </div>
</div>

<section class="card" style="margin-bottom:16px;padding:22px 24px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:24px">
        <div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Igreja</div>
            <div style="font-size:15px;color:var(--brown-900);margin-top:4px;font-weight:600"><?= esc($pagamento['igreja_nome']) ?></div>
            <div class="muted" style="font-size:12px"><?= esc($pagamento['igreja_slug']) ?></div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Plano</div>
            <div style="font-size:15px;color:var(--brown-900);margin-top:4px;font-weight:600"><?= esc($pagamento['plano_nome'] ?? '—') ?></div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Valor</div>
            <div style="font-size:20px;color:var(--gold-700);margin-top:4px;font-weight:600"><?= fmtKz($pagamento['valor_kz']) ?></div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Estado</div>
            <div style="margin-top:6px">
                <span class="status <?= $estadoClass[$pagamento['estado']] ?? 'off' ?>">
                    <?= esc($estadoLabel[$pagamento['estado']] ?? $pagamento['estado']) ?>
                </span>
            </div>
        </div>
    </div>

    <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--line-soft);display:grid;grid-template-columns:1fr 1fr;gap:20px;font-size:13.5px">
        <div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Detalhes</div>
            <div style="display:grid;gap:6px">
                <div><span class="muted">Método:</span> <?= esc(ucfirst($pagamento['metodo'] ?? '—')) ?></div>
                <div><span class="muted">Referência:</span> <?= esc($pagamento['referencia'] ?? '—') ?></div>
                <div><span class="muted">Criado em:</span> <?= esc(date('d/m/Y H:i', strtotime($pagamento['criado_em']))) ?></div>
                <?php if ($pagamento['pago_em']): ?>
                    <div><span class="muted">Pago em:</span> <?= esc(date('d/m/Y H:i', strtotime($pagamento['pago_em']))) ?></div>
                <?php endif; ?>
                <?php if ($pagamento['supervisor_nome']): ?>
                    <div><span class="muted">Confirmado por:</span> <?= esc($pagamento['supervisor_nome']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pagamento['comprovativo']): ?>
            <div>
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Comprovativo</div>
                <a href="<?= esc(asset('uploads/' . $pagamento['comprovativo'])) ?>" target="_blank"
                   class="btn btn-outline">
                    <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
                    Ver comprovativo
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Modal de rejeição -->
<div id="modal-rejeitar" style="display:none;position:fixed;inset:0;background:rgba(46,27,14,.55);z-index:100;align-items:center;justify-content:center;padding:20px">
    <div class="card" style="max-width:480px;width:100%;padding:28px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
            <span style="width:44px;height:44px;border-radius:50%;background:var(--danger-bg);display:grid;place-items:center;color:var(--danger)">
                <i class="fa-solid fa-xmark" aria-hidden="true" style="font-size:20px"></i>
            </span>
            <h2 style="font-size:18px;color:var(--brown-900);margin:0">Rejeitar pagamento?</h2>
        </div>
        <form method="post" action="<?= esc(url('superadmin/pagamentos/' . $pagamento['id'] . '/rejeitar')) ?>">
            <?= csrf_field() ?>
            <label class="lb">Motivo (opcional)</label>
            <textarea name="motivo" class="input" rows="3"
                      placeholder="Ex: Comprovativo ilegível"
                      style="height:auto;padding:10px 14px;resize:vertical"></textarea>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modal-rejeitar').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-gold" style="background:linear-gradient(180deg,#dc2626,#991b1b)">
                    Rejeitar
                </button>
            </div>
        </form>
    </div>
</div>