<?php
/** @var array $supervisor */
/** @var array $igreja */
/** @var array|null $assinatura */
/** @var array $stats */
/** @var array $pagamentos */
/** @var array $usuarios */

$statusLabel = ['em_teste' => 'Em teste', 'activa' => 'Ativa', 'suspensa' => 'Suspensa', 'cancelada' => 'Cancelada'];
$statusClass = ['em_teste' => 'off', 'activa' => 'ok', 'suspensa' => 'off', 'cancelada' => 'off'];
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('superadmin/igrejas')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title" style="display:flex;align-items:center;gap:10px">
            <i class="fa-solid fa-church" aria-hidden="true" style="color:var(--gold-600)"></i>
            <?= esc($igreja['nome']) ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="<?= esc(url('superadmin/igrejas/' . $igreja['id'] . '/editar')) ?>" class="btn btn-outline">
            <i class="fa-solid fa-pen" aria-hidden="true"></i>
            Editar
        </a>
        <?php if ($igreja['status'] !== 'suspensa'): ?>
            <form method="post" action="<?= esc(url('superadmin/igrejas/' . $igreja['id'] . '/suspender')) ?>"
                  style="margin:0" onsubmit="return confirm('Suspender a igreja?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline" style="color:var(--danger)">
                    <i class="fa-solid fa-pause" aria-hidden="true"></i>
                    Suspender
                </button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= esc(url('superadmin/igrejas/' . $igreja['id'] . '/reativar')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-gold">
                    <i class="fa-solid fa-play" aria-hidden="true"></i>
                    Reativar
                </button>
            </form>
        <?php endif; ?>
        <a href="<?= esc(url('superadmin/igrejas/' . $igreja['id'] . '/impersonate')) ?>"
           class="btn btn-outline"
           onclick="return confirm('Entrar como administrador desta igreja?')">
            <i class="fa-solid fa-mask" aria-hidden="true"></i>
            Ver como
        </a>
    </div>
</div>

<!-- KPIs da igreja -->
<section class="grid kpis-3" style="margin-bottom:16px">
    <div class="card kpi-simple" style="padding:16px 18px">
        <div class="lbl muted">Membros</div>
        <div class="val"><?= number_format($stats['membros'], 0, ',', '.') ?></div>
    </div>
    <div class="card kpi-simple" style="padding:16px 18px">
        <div class="lbl muted">Utilizadores</div>
        <div class="val"><?= number_format($stats['users'], 0, ',', '.') ?></div>
    </div>
    <div class="card kpi-simple" style="padding:16px 18px">
        <div class="lbl muted">Transações</div>
        <div class="val"><?= number_format($stats['transacoes'], 0, ',', '.') ?></div>
    </div>
</section>

<div class="grid row-2" style="gap:16px;margin-bottom:16px">
    <!-- Dados -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                Dados da Igreja
            </div>
        </div>
        <div style="display:grid;gap:10px;font-size:13.5px">
            <div><span class="muted">Slug:</span> <strong><?= esc($igreja['slug']) ?></strong></div>
            <div><span class="muted">E-mail:</span> <?= esc($igreja['email']) ?></div>
            <?php if ($igreja['telefone']): ?><div><span class="muted">Telefone:</span> <?= esc($igreja['telefone']) ?></div><?php endif; ?>
            <?php if ($igreja['nif']): ?><div><span class="muted">NIF:</span> <?= esc($igreja['nif']) ?></div><?php endif; ?>
            <?php if ($igreja['endereco']): ?><div><span class="muted">Endereço:</span> <?= esc($igreja['endereco']) ?></div><?php endif; ?>
            <div>
                <span class="muted">Localização:</span>
                <?= esc(($igreja['cidade'] ?: '—') . ' / ' . ($igreja['provincia'] ?: '—') . ' / ' . $igreja['pais']) ?>
            </div>
            <div><span class="muted">Fuso horário:</span> <?= esc($igreja['fuso_horario']) ?></div>
            <div><span class="muted">Moeda:</span> <?= esc($igreja['moeda']) ?></div>
            <div><span class="muted">Criada em:</span> <?= esc(date('d/m/Y H:i', strtotime($igreja['criado_em']))) ?></div>
            <div>
                <span class="muted">Status:</span>
                <span class="status <?= $statusClass[$igreja['status']] ?? 'off' ?>">
                    <?= esc($statusLabel[$igreja['status']] ?? $igreja['status']) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Assinatura -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-file-contract" aria-hidden="true"></i>
                Assinatura
            </div>
        </div>
        <?php if (!$assinatura): ?>
            <p class="muted">Sem assinatura registada.</p>
        <?php else: ?>
            <div style="display:grid;gap:10px;font-size:13.5px">
                <div><span class="muted">Plano:</span> <strong><?= esc($assinatura['plano_nome']) ?></strong></div>
                <div><span class="muted">Início:</span> <?= esc(date('d/m/Y', strtotime($assinatura['inicio']))) ?></div>
                <?php if ($assinatura['expiracao']): ?>
                    <div><span class="muted">Expira em:</span> <?= esc(date('d/m/Y', strtotime($assinatura['expiracao']))) ?></div>
                <?php endif; ?>
                <div>
                    <span class="muted">Status:</span>
                    <span class="status ok"><?= esc(ucfirst($assinatura['status'])) ?></span>
                </div>
            </div>
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--line-soft)">
                <form method="post" action="<?= esc(url('superadmin/igrejas/' . $igreja['id'] . '/reenviar-credenciais')) ?>"
                      style="margin:0" onsubmit="return confirm('Gerar nova senha temporária e reenviar por e-mail?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline" style="width:100%;font-size:13px">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        Reenviar credenciais ao admin
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Últimos utilizadores -->
<section class="card" style="margin-bottom:16px">
    <div class="card-head">
        <div class="card-title">
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            Utilizadores (<?= count($usuarios) ?>)
        </div>
    </div>
    <?php if (empty($usuarios)): ?>
        <p class="muted" style="margin:0">Sem utilizadores.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Nome</th><th>E-mail</th><th>Roles</th><th>Último login</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><strong><?= esc($u['nome']) ?></strong></td>
                            <td class="muted"><?= esc($u['email']) ?></td>
                            <td class="muted" style="font-size:12px"><?= esc($u['roles'] ?? '—') ?></td>
                            <td class="muted" style="font-size:12px">
                                <?= $u['ultimo_login'] ? esc(date('d/m/Y H:i', strtotime($u['ultimo_login']))) : '—' ?>
                            </td>
                            <td>
                                <?php if ((int) $u['ativo'] === 1): ?>
                                    <span class="status ok">Ativo</span>
                                <?php else: ?>
                                    <span class="status off">Inativo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Últimos pagamentos -->
<section class="card">
    <div class="card-head">
        <div class="card-title">
            <i class="fa-solid fa-money-bill" aria-hidden="true"></i>
            Últimos pagamentos (<?= count($pagamentos) ?>)
        </div>
    </div>
    <?php if (empty($pagamentos)): ?>
        <p class="muted" style="margin:0">Sem pagamentos registados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Data</th><th>Valor</th><th>Método</th><th>Referência</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($pagamentos as $p):
                        $estadoClass = $p['estado'] === 'confirmado' ? 'ok' : 'off';
                    ?>
                        <tr>
                            <td class="muted" style="font-size:12.5px"><?= esc(date('d/m/Y', strtotime($p['criado_em']))) ?></td>
                            <td><strong><?= fmtKz($p['valor_kz']) ?></strong></td>
                            <td class="muted"><?= esc(ucfirst($p['metodo'] ?? '—')) ?></td>
                            <td class="muted"><?= esc($p['referencia'] ?? '—') ?></td>
                            <td><span class="status <?= $estadoClass ?>"><?= esc(ucfirst($p['estado'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>