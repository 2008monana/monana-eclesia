<?php
/** @var array $supervisor */
/** @var array $settings */

$smtp = $settings['smtp'] ?? [];
?>
<div class="page-head">
    <h1 class="page-title">Configurações Globais</h1>
</div>

<div class="grid row-2" style="gap:16px;margin-bottom:16px">
    <!-- Dados da plataforma -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-gear" aria-hidden="true"></i>
                Plataforma
            </div>
        </div>
        <form method="post" action="<?= esc(url('superadmin/configuracoes/geral')) ?>">
            <?= csrf_field() ?>
            <div style="display:grid;gap:14px">
                <div>
                    <label class="lb">Nome da plataforma</label>
                    <input name="app_nome" class="input" value="<?= esc($settings['app_nome'] ?? '') ?>">
                </div>
                <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="lb">Moeda</label>
                        <input name="app_moeda" class="input" value="<?= esc($settings['app_moeda'] ?? 'AOA') ?>">
                    </div>
                    <div>
                        <label class="lb">Idioma</label>
                        <input name="app_idioma" class="input" value="<?= esc($settings['app_idioma'] ?? 'pt-AO') ?>">
                    </div>
                </div>
                <div>
                    <label class="lb">Fuso horário</label>
                    <input name="app_fuso" class="input" value="<?= esc($settings['app_fuso'] ?? 'Africa/Luanda') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-gold" style="margin-top:16px;width:100%">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                Guardar
            </button>
        </form>
    </div>

    <!-- Regras de negócio -->
    <div class="card">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                Regras de Negócio
            </div>
        </div>
        <form method="post" action="<?= esc(url('superadmin/configuracoes/geral')) ?>">
            <?= csrf_field() ?>
            <div style="display:grid;gap:14px">
                <div>
                    <label class="lb">Tolerância após expiração (horas)</label>
                    <input name="assinatura_tolerancia_horas" type="number" min="0" class="input"
                           value="<?= esc($settings['assinatura_tolerancia_horas'] ?? '24') ?>">
                    <p class="muted" style="font-size:11.5px;margin:4px 0 0">
                        Tempo que a igreja pode continuar a usar após a expiração antes de ser bloqueada.
                    </p>
                </div>
                <div>
                    <label class="lb">Aviso de expiração (horas antes)</label>
                    <input name="assinatura_aviso_horas" type="number" min="0" class="input"
                           value="<?= esc($settings['assinatura_aviso_horas'] ?? '48') ?>">
                    <p class="muted" style="font-size:11.5px;margin:4px 0 0">
                        Quando começar a avisar a igreja sobre a expiração próxima.
                    </p>
                </div>
                <div>
                    <label class="lb">Retenção de backups (dias)</label>
                    <input name="backup_retencao_dias" type="number" min="1" class="input"
                           value="<?= esc($settings['backup_retencao_dias'] ?? '30') ?>">
                    <p class="muted" style="font-size:11.5px;margin:4px 0 0">
                        Backups mais antigos que este valor são eliminados automaticamente.
                    </p>
                </div>
            </div>
            <button type="submit" class="btn btn-gold" style="margin-top:16px;width:100%">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                Guardar
            </button>
        </form>
    </div>
</div>

<!-- SMTP Global -->
<section class="card">
    <div class="card-head">
        <div class="card-title">
            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
            SMTP Global (fallback)
        </div>
    </div>
    <p class="muted" style="font-size:13px;margin:0 0 18px">
        Este é o servidor de e-mail padrão. Se uma igreja não configurar o seu próprio SMTP,
        o sistema usará este.
    </p>
    <form method="post" action="<?= esc(url('superadmin/configuracoes/smtp')) ?>">
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
        <button type="submit" class="btn btn-gold" style="margin-top:16px">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            Guardar SMTP
        </button>
    </form>
</section>