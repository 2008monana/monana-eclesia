<?php
/** @var array $user */
/** @var array $usuario */
/** @var array $rolesDoUsuario */
/** @var array $rolesDisponiveis */
/** @var array $rolesAtuaisIds */
/** @var array $overrides */
/** @var array $sessoes */
/** @var array $todasPermissoes */

use App\Core\Rbac;

$iniciais = '';
$partes = preg_split('/\s+/', trim($usuario['nome']));
$iniciais = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));

$podeEditarPerm = Rbac::can('utilizadores.atribuir_permissoes');
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('usuarios')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title">Detalhes do Usuário</h1>
    </div>
    <?php if (Rbac::can('utilizadores.editar')): ?>
        <a href="<?= esc(url('usuarios/' . $usuario['id'] . '/editar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-pen" aria-hidden="true"></i>
            Editar
        </a>
    <?php endif; ?>
</div>

<section class="card" style="padding:24px;margin-bottom:16px">
    <div style="display:flex;gap:22px;align-items:center;flex-wrap:wrap">
        <span class="avatar" style="width:72px;height:72px;display:inline-grid;place-items:center;color:var(--gold-700);font-weight:600;font-size:24px;background:var(--gold-100);border-radius:50%">
            <?= esc($iniciais) ?>
        </span>
        <div>
            <h2 style="font-size:20px;color:var(--brown-900);display:flex;align-items:center;gap:10px;margin:0">
                <?= esc($usuario['nome']) ?>
                <?php if ((int) $usuario['ativo'] === 1): ?>
                    <span class="status ok">Activo</span>
                <?php else: ?>
                    <span class="status off">Inactivo</span>
                <?php endif; ?>
            </h2>
            <div style="display:flex;flex-direction:column;gap:5px;margin-top:8px;font-size:13px;color:var(--text-muted)">
                <div><i class="fa-solid fa-envelope" aria-hidden="true" style="color:var(--gold-600);width:16px"></i> <?= esc($usuario['email']) ?></div>
                <?php if ($usuario['telefone']): ?>
                    <div><i class="fa-solid fa-phone" aria-hidden="true" style="color:var(--gold-600);width:16px"></i> <?= esc($usuario['telefone']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<div class="grid row-2" style="gap:16px">
    <!-- Roles -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                Roles
            </div>
        </div>
        <?php if ($podeEditarPerm): ?>
            <form method="post" action="<?= esc(url('usuarios/' . $usuario['id'] . '/roles')) ?>">
                <?= csrf_field() ?>
                <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:14px">
                    <?php foreach ($rolesDisponiveis as $r): ?>
                        <label style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--ivory-bg);border-radius:8px;cursor:pointer;font-size:13px">
                            <input type="checkbox" name="roles[]" value="<?= (int) $r['id'] ?>" class="chk"
                                   <?= in_array((int) $r['id'], $rolesAtuaisIds, true) ? 'checked' : '' ?>>
                            <span><strong><?= esc($r['nome']) ?></strong></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-gold" style="width:100%">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Guardar roles
                </button>
            </form>
        <?php else: ?>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px">
                <?php foreach ($rolesDoUsuario as $r): ?>
                    <li style="padding:8px 10px;background:var(--gold-50);border-radius:8px;font-size:13px"><?= esc($r['nome']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Overrides de permissões -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-key" aria-hidden="true"></i>
                Permissões (override)
            </div>
        </div>

        <?php if (empty($overrides)): ?>
            <p class="muted" style="margin:0 0 14px">Nenhum override. O usuário herda apenas as permissões das roles.</p>
        <?php else: ?>
            <ul style="list-style:none;padding:0;margin:0 0 14px;display:flex;flex-direction:column;gap:6px">
                <?php foreach ($overrides as $o):
                    $isGrant = $o['tipo'] === 'grant';
                ?>
                    <li style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:<?= $isGrant ? 'var(--success-bg)' : 'var(--danger-bg)' ?>;border-radius:8px;font-size:12.5px">
                        <i class="fa-solid fa-<?= $isGrant ? 'check' : 'xmark' ?>" aria-hidden="true"></i>
                        <span style="flex:1"><strong><?= esc($o['modulo']) ?>.</strong><?= esc($o['acao']) ?></span>
                        <?php if ($podeEditarPerm): ?>
                            <form method="post" action="<?= esc(url('usuarios/' . $usuario['id'] . '/permissoes/' . $o['permission_id'] . '/remover')) ?>" style="margin:0">
                                <?= csrf_field() ?>
                                <button type="submit" class="icon-btn" style="padding:2px 4px">
                                    <i class="fa-solid fa-trash" aria-hidden="true" style="font-size:11px"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($podeEditarPerm): ?>
            <form method="post" action="<?= esc(url('usuarios/' . $usuario['id'] . '/permissoes')) ?>"
                  style="display:grid;gap:10px">
                <?= csrf_field() ?>
                <select name="permission_id" class="select" required>
                    <option value="">Escolher permissão...</option>
                    <?php
                    $atualModulo = null;
                    foreach ($todasPermissoes as $p):
                        if ($atualModulo !== $p['modulo']):
                            if ($atualModulo !== null) echo '</optgroup>';
                            $atualModulo = $p['modulo'];
                            echo '<optgroup label="' . esc(ucfirst($p['modulo'])) . '">';
                        endif;
                    ?>
                        <option value="<?= (int) $p['id'] ?>"><?= esc($p['modulo'] . '.' . $p['acao']) ?> — <?= esc($p['descricao'] ?? '') ?></option>
                    <?php endforeach; ?>
                    <?php if ($atualModulo !== null) echo '</optgroup>'; ?>
                </select>
                <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px">
                    <select name="tipo" class="select" required>
                        <option value="grant">Conceder (grant)</option>
                        <option value="deny">Negar (deny)</option>
                    </select>
                    <button type="submit" class="btn btn-gold" style="grid-column:1 / -1">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        Aplicar override
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Sessões -->
<section class="card" style="margin-top:16px">
    <div class="card-head">
        <div class="card-title">
            <i class="fa-solid fa-desktop" aria-hidden="true"></i>
            Sessões ativas (<?= count($sessoes) ?>)
        </div>
    </div>

    <?php if (empty($sessoes)): ?>
        <p class="muted" style="margin:0">Nenhuma sessão ativa.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>IP</th><th>User Agent</th><th>Criada</th><th>Expira</th><th style="text-align:right"></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($sessoes as $s): ?>
                        <tr>
                            <td class="muted"><?= esc($s['ip'] ?: '—') ?></td>
                            <td class="muted" style="font-size:12px;max-width:300px;overflow:hidden;text-overflow:ellipsis">
                                <?= esc(mb_strimwidth($s['user_agent'] ?? '—', 0, 60, '...')) ?>
                            </td>
                            <td class="muted" style="font-size:12.5px"><?= esc(date('d/m/Y H:i', strtotime($s['criado_em']))) ?></td>
                            <td class="muted" style="font-size:12.5px"><?= esc(date('d/m/Y H:i', strtotime($s['expira_em']))) ?></td>
                            <td style="text-align:right">
                                <?php if ($podeEditarPerm): ?>
                                    <form method="post" action="<?= esc(url('usuarios/' . $usuario['id'] . '/sessoes/' . $s['id'] . '/revogar')) ?>" style="margin:0" onsubmit="return confirm('Revogar esta sessão?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn" title="Revogar">
                                            <i class="fa-solid fa-power-off" aria-hidden="true" style="color:var(--danger)"></i>
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
</section>