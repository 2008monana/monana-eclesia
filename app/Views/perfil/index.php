<?php
/** @var array $user */
/** @var array|null $perfil */
/** @var array $sessoes */

$iniciais = iniciais($user['nome'] ?? '');
?>
<div class="page-head">
    <h1 class="page-title">O meu perfil</h1>
</div>

<!-- Cartão de perfil -->
<section class="card" style="padding:24px;margin-bottom:16px">
    <div style="display:flex;gap:22px;align-items:center;flex-wrap:wrap">
        <span class="avatar"
              style="width:72px;height:72px;display:inline-grid;place-items:center;
                     color:var(--gold-700);font-weight:600;font-size:24px;
                     background:var(--gold-100);border-radius:50%">
            <?= esc($iniciais) ?>
        </span>
        <div>
            <h2 style="font-size:20px;color:var(--brown-900);margin:0">
                <?= esc($user['nome'] ?? '—') ?>
            </h2>
            <p class="muted" style="margin:4px 0 0;font-size:13px">
                <?= esc($user['email'] ?? '—') ?>
            </p>
        </div>
    </div>
</section>

<div class="grid row-2" style="gap:16px">
    <!-- Dados pessoais -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                Dados pessoais
            </div>
        </div>
        <form method="post" action="<?= esc(url('perfil/dados')) ?>">
            <?= csrf_field() ?>
            <div style="display:grid;gap:14px">
                <div>
                    <label class="lb" for="nome">Nome *</label>
                    <input id="nome" name="nome" class="input" required
                           value="<?= esc($user['nome'] ?? '') ?>">
                </div>
                <div>
                    <label class="lb">E-mail</label>
                    <input class="input" disabled
                           value="<?= esc($user['email'] ?? '') ?>"
                           style="background:var(--ivory-bg)">
                </div>
                <div>
                    <label class="lb" for="telefone">Telefone</label>
                    <input id="telefone" name="telefone" class="input"
                           value="<?= esc($user['telefone'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-gold" style="margin-top:16px;width:100%">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                Guardar
            </button>
        </form>
    </div>

    <!-- Alterar senha -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                Alterar palavra-passe
            </div>
        </div>
        <form method="post" action="<?= esc(url('perfil/senha')) ?>">
            <?= csrf_field() ?>
            <div style="display:grid;gap:14px">
                <div>
                    <label class="lb" for="senha_atual">Palavra-passe atual</label>
                    <input id="senha_atual" name="senha_atual" type="password" class="input" required>
                </div>
                <div>
                    <label class="lb" for="senha_nova">Nova palavra-passe</label>
                    <input id="senha_nova" name="senha_nova" type="password" class="input"
                           required minlength="8" placeholder="Mínimo 8 caracteres">
                </div>
                <div>
                    <label class="lb" for="senha_conf">Confirmar nova</label>
                    <input id="senha_conf" name="senha_conf" type="password" class="input"
                           required minlength="8">
                </div>
            </div>
            <button type="submit" class="btn btn-gold" style="margin-top:16px;width:100%">
                <i class="fa-solid fa-key" aria-hidden="true"></i>
                Alterar senha
            </button>
        </form>
    </div>
</div>

<!-- Sessões ativas -->
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
                    <tr>
                        <th>IP</th>
                        <th>Dispositivo</th>
                        <th>Criada</th>
                        <th style="text-align:right"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessoes as $s): ?>
                        <tr>
                            <td class="muted"><?= esc($s['ip'] ?: '—') ?></td>
                            <td class="muted" style="font-size:12px;max-width:300px;overflow:hidden;text-overflow:ellipsis">
                                <?= esc(mb_strimwidth($s['user_agent'] ?? '—', 0, 55, '...')) ?>
                            </td>
                            <td class="muted" style="font-size:12.5px">
                                <?= esc(date('d/m/Y H:i', strtotime($s['criado_em']))) ?>
                            </td>
                            <td style="text-align:right">
                                <form method="post"
                                      action="<?= esc(url('perfil/sessoes/' . $s['id'] . '/revogar')) ?>"
                                      style="margin:0"
                                      onsubmit="return confirm('Revogar esta sessão?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="icon-btn" title="Revogar sessão">
                                        <i class="fa-solid fa-power-off" aria-hidden="true"
                                           style="color:var(--danger)"></i>
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