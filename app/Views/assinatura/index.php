<?php
/** @var array $user */
/** @var array|null $assinatura */
/** @var array $pagamentos */
/** @var array $planos */
/** @var array $uso */

use App\Core\Subscription;

$statusReal = Subscription::realStatus();
$statusLabel = [
    'trial' => 'Trial', 'activa' => 'Ativa', 'expirando' => 'A expirar',
    'expirada' => 'Expirada', 'em_tolerancia' => 'Em tolerância',
    'suspensa' => 'Suspensa', 'cancelada' => 'Cancelada',
];
$statusCor = [
    'activa' => 'ok', 'trial' => 'ok', 'expirando' => 'off',
    'expirada' => 'off', 'em_tolerancia' => 'off', 'suspensa' => 'off', 'cancelada' => 'off',
];

$diasRestantes = null;
if ($assinatura && $assinatura['expiracao']) {
    $diasRestantes = (int) ceil((strtotime($assinatura['expiracao']) - time()) / 86400);
}
?>
<div class="page-head">
    <h1 class="page-title">Assinatura</h1>
</div>

<?php if (!$assinatura): ?>
    <div class="card" style="text-align:center;padding:40px">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true" style="font-size:48px;color:var(--gold-400)"></i>
        <p class="muted" style="margin:12px 0 0">Sem assinatura registada. Contacte o suporte da plataforma.</p>
    </div>
<?php else: ?>

    <!-- Cartão de plano -->
    <section class="card" style="margin-bottom:16px;padding:24px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px">
            <div>
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Plano atual</div>
                <h2 style="font-size:26px;margin:6px 0 4px;color:var(--brown-900)">
                    <?= esc($assinatura['plano_nome']) ?>
                </h2>
                <div class="muted" style="font-size:13px"><?= esc($assinatura['plano_desc']) ?></div>
                <div style="margin-top:12px;display:flex;gap:20px;flex-wrap:wrap;font-size:13px">
                    <div>
                        <span class="muted">Início:</span>
                        <strong><?= esc(date('d/m/Y', strtotime($assinatura['inicio']))) ?></strong>
                    </div>
                    <div>
                        <span class="muted">Expira em:</span>
                        <strong><?= esc(date('d/m/Y', strtotime($assinatura['expiracao']))) ?></strong>
                        <?php if ($diasRestantes !== null): ?>
                            <span class="muted">(<?= $diasRestantes ?> dias)</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="status <?= $statusCor[$statusReal] ?? 'off' ?>">
                            <?= esc($statusLabel[$statusReal] ?? $statusReal) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div style="text-align:right">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Valor do plano</div>
                <div style="font-size:28px;font-weight:600;color:var(--gold-700);margin-top:4px">
                    <?= fmtKz($assinatura['preco_kz']) ?>
                </div>
                <?php if ($assinatura['periodicidade_dias'] > 0): ?>
                    <div class="muted" style="font-size:12px">a cada <?= (int) $assinatura['periodicidade_dias'] ?> dias</div>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--line-soft);display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <div>
                <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px">
                    <span>Membros</span>
                    <span class="muted">
                        <?= $uso['membros'] ?>
                        <?= $assinatura['limite_membros'] !== null ? '/ ' . $assinatura['limite_membros'] : '/ ilimitado' ?>
                    </span>
                </div>
                <?php if ($assinatura['limite_membros']): ?>
                    <?php $pct = min(100, round(($uso['membros'] / $assinatura['limite_membros']) * 100)); ?>
                    <div style="background:var(--gold-100);height:8px;border-radius:999px;overflow:hidden">
                        <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct > 85 ? 'var(--danger)' : 'var(--gold-500)' ?>"></div>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px">
                    <span>Usuários</span>
                    <span class="muted">
                        <?= $uso['users'] ?>
                        <?= $assinatura['limite_users'] !== null ? '/ ' . $assinatura['limite_users'] : '/ ilimitado' ?>
                    </span>
                </div>
                <?php if ($assinatura['limite_users']): ?>
                    <?php $pct = min(100, round(($uso['users'] / $assinatura['limite_users']) * 100)); ?>
                    <div style="background:var(--gold-100);height:8px;border-radius:999px;overflow:hidden">
                        <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct > 85 ? 'var(--danger)' : 'var(--gold-500)' ?>"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Registar pagamento -->
    <section class="card" style="margin-bottom:16px">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i>
                Registar pagamento
            </div>
        </div>
        <p class="muted" style="margin:0 0 16px;font-size:13px">
            Registe o pagamento e o superadmin irá confirmar. Pode anexar o comprovativo (PNG, JPG ou PDF).
        </p>
        <form method="post" action="<?= esc(url('assinatura/pagar')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="grid" style="grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px">
                <div>
                    <label class="lb">Valor (Kz)</label>
                    <input class="input" name="valor_kz" type="number" step="0.01" min="0.01" required
                           value="<?= esc($assinatura['preco_kz']) ?>">
                </div>
                <div>
                    <label class="lb">Método</label>
                    <select class="select" name="metodo" required>
                        <option value="">—</option>
                        <option value="transferencia">Transferência</option>
                        <option value="multicaixa">Multicaixa</option>
                        <option value="deposito">Depósito</option>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div>
                    <label class="lb">Referência</label>
                    <input class="input" name="referencia" placeholder="Ex: TRX123456">
                </div>
            </div>
            <div style="margin-bottom:14px">
                <label class="lb">Comprovativo</label>
                <input class="input" type="file" name="comprovativo" accept=".png,.jpg,.jpeg,.pdf"
                       style="padding:7px 12px;height:auto">
                <p class="muted" style="font-size:11.5px;margin:4px 0 0">Opcional — máximo 5 MB</p>
            </div>
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                Registar pagamento
            </button>
        </form>
    </section>

    <!-- Histórico -->
    <section class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                Histórico de pagamentos
            </div>
        </div>
        <?php if (empty($pagamentos)): ?>
            <p class="muted">Nenhum pagamento registado.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Plano</th>
                            <th>Valor</th>
                            <th>Método</th>
                            <th>Referência</th>
                            <th>Estado</th>
                            <th style="text-align:right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagamentos as $p):
                            $estadoClass = match ($p['estado']) {
                                'confirmado' => 'ok',
                                default      => 'off',
                            };
                        ?>
                            <tr>
                                <td class="muted" style="font-size:12.5px"><?= esc(date('d/m/Y H:i', strtotime($p['criado_em']))) ?></td>
                                <td><?= esc($p['plano_nome']) ?></td>
                                <td><strong><?= fmtKz($p['valor_kz']) ?></strong></td>
                                <td class="muted"><?= esc(ucfirst($p['metodo'] ?? '—')) ?></td>
                                <td class="muted"><?= esc($p['referencia'] ?? '—') ?></td>
                                <td>
                                    <span class="status <?= $estadoClass ?>"><?= esc(ucfirst($p['estado'])) ?></span>
                                </td>
                                <td style="text-align:right">
                                    <?php if ($p['comprovativo']): ?>
                                        <a href="<?= esc(asset('uploads/' . $p['comprovativo'])) ?>" target="_blank"
                                           class="icon-btn" title="Ver comprovativo">
                                            <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php endif; ?>