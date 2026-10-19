<?php
/** @var array $user */
/** @var array $igreja */
/** @var array $settings */

use App\Core\Rbac;

$corAtual   = $settings['cor_primaria'] ?? '#b8862b';
$versiculo  = $settings['versiculo_sidebar'] ?? '“Servir a Deus é um privilégio.”';
$smtp       = $settings['smtp'] ?? [];
$podeEditar = Rbac::can('configuracoes.editar');
?>
<div class="page-head">
    <h1 class="page-title">Configurações</h1>
</div>

<div class="grid row-2" style="gap:16px">
    <!-- Dados da Igreja -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-church" aria-hidden="true"></i>
                Dados da Igreja
            </div>
        </div>
        <form method="post" action="<?= esc(url('configuracoes/igreja')) ?>">
            <?= csrf_field() ?>
            <div style="display:grid;gap:14px">
                <div>
                    <label class="lb">Nome *</label>
                    <input name="nome" class="input" required value="<?= esc($igreja['nome'] ?? '') ?>">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="lb">NIF</label>
                        <input name="nif" class="input" value="<?= esc($igreja['nif'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="lb">E-mail *</label>
                        <input name="email" type="email" class="input" required value="<?= esc($igreja['email'] ?? '') ?>">
                    </div>
                </div>
                <div>
                    <label class="lb">Telefone</label>
                    <input name="telefone" class="input" value="<?= esc($igreja['telefone'] ?? '') ?>">
                </div>
                <div>
                    <label class="lb">Endereço</label>
                    <input name="endereco" class="input" value="<?= esc($igreja['endereco'] ?? '') ?>">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="lb">Cidade</label>
                        <input name="cidade" class="input" value="<?= esc($igreja['cidade'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="lb">Província</label>
                        <input name="provincia" class="input" value="<?= esc($igreja['provincia'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <?php if ($podeEditar): ?>
                <button type="submit" class="btn btn-gold" style="margin-top:16px;width:100%">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    Guardar dados
                </button>
            <?php endif; ?>
        </form>
    </div>

    <!-- Logotipo -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-image" aria-hidden="true"></i>
                Logotipo
            </div>
        </div>

        <div style="text-align:center;padding:20px;background:var(--gold-50);border-radius:8px;margin-bottom:14px">
            <?php if (!empty($igreja['logo'])): ?>
                <img src="<?= esc(asset('uploads/' . $igreja['logo'])) ?>"
                     alt="Logo" style="max-width:160px;max-height:160px;object-fit:contain">
            <?php else: ?>
                <div style="font-size:40px;color:var(--gold-400)"><i class="fa-solid fa-image"></i></div>
                <p class="muted" style="margin:8px 0 0;font-size:12.5px">Sem logotipo</p>
            <?php endif; ?>
        </div>

        <?php if ($podeEditar): ?>
            <form method="post" action="<?= esc(url('configuracoes/logo')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg" class="input"
                       style="padding:7px 12px;height:auto" required>
                <p class="muted" style="font-size:11.5px;margin:6px 0 0">PNG, JPG ou SVG — máx. 2 MB</p>
                <button type="submit" class="btn btn-gold" style="margin-top:12px;width:100%">
                    <i class="fa-solid fa-upload" aria-hidden="true"></i>
                    Enviar logotipo
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Tema -->
<section class="card" style="margin-top:16px">
    <div class="card-head">
        <div class="card-title">
            <i class="fa-solid fa-palette" aria-hidden="true"></i>
            Tema e Identidade Visual
        </div>
    </div>
    <form method="post" action="<?= esc(url('configuracoes/tema')) ?>">
        <?= csrf_field() ?>
        <div class="grid" style="grid-template-columns:1fr 2fr;gap:16px">
            <div>
                <label class="lb">Cor primária</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input type="color" name="cor_primaria" value="<?= esc($corAtual) ?>"
                           class="input" style="width:70px;height:40px;padding:4px;cursor:pointer">
                    <input class="input" disabled value="<?= esc($corAtual) ?>" style="flex:1;background:var(--ivory-bg)">
                </div>
            </div>
            <div>
                <label class="lb">Versículo da sidebar</label>
                <input name="versiculo" class="input" value="<?= esc($versiculo) ?>"
                       placeholder="“Servir a Deus é um privilégio.”">
            </div>
        </div>
        <?php if ($podeEditar): ?>
            <button type="submit" class="btn btn-gold" style="margin-top:16px">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                Guardar tema
            </button>
        <?php endif; ?>
    </form>
</section>

<!-- SMTP -->
<section class="card" style="margin-top:16px">
    <div class="card-head">
        <div class="card-title">
            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
            SMTP da Igreja
        </div>
    </div>
    <p class="muted" style="margin:0 0 16px;font-size:13px">
        Se configurar, a igreja usará o seu próprio servidor de e-mail (sobrepõe o global).
    </p>
    <form method="post" action="<?= esc(url('configuracoes/smtp')) ?>">
        <?= csrf_field() ?>
        <div class="grid" style="grid-template-columns:2fr 1fr 1fr;gap:16px">
            <div>
                <label class="lb">Host</label>
                <input name="smtp_host" class="input" value="<?= esc($smtp['host'] ?? '') ?>" placeholder="smtp.gmail.com">
            </div>
            <div>
                <label class="lb">Porta</label>
                <input name="smtp_port" type="number" class="input" value="<?= esc($smtp['port'] ?? 587) ?>">
            </div>
            <div>
                <label class="lb">Encriptação</label>
                <select name="smtp_enc" class="select">
                    <option value="tls" <?= ($smtp['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= ($smtp['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                </select>
            </div>
        </div>
        <div class="grid" style="grid-template-columns:1fr 1fr;gap:16px;margin-top:16px">
            <div>
                <label class="lb">Username</label>
                <input name="smtp_user" class="input" value="<?= esc($smtp['username'] ?? '') ?>">
            </div>
            <div>
                <label class="lb">Password</label>
                <input name="smtp_pass" type="password" class="input" value="<?= esc($smtp['password'] ?? '') ?>"
                       placeholder="(deixe em branco para manter)">
            </div>
        </div>
        <div class="grid" style="grid-template-columns:2fr 1fr;gap:16px;margin-top:16px">
            <div>
                <label class="lb">E-mail de envio</label>
                <input name="smtp_from" type="email" class="input" value="<?= esc($smtp['from_email'] ?? '') ?>">
            </div>
            <div>
                <label class="lb">Nome do remetente</label>
                <input name="smtp_from_name" class="input" value="<?= esc($smtp['from_name'] ?? '') ?>">
            </div>
        </div>
        <?php if ($podeEditar): ?>
            <button type="submit" class="btn btn-gold" style="margin-top:16px">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                Guardar SMTP
            </button>
        <?php endif; ?>
    </form>
</section>