<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$church = is_array($church ?? null) ? $church : [];
$plans = is_array($plans ?? null) ? $plans : [];
$emails = is_array($emails ?? null) ? $emails : [];
$errorMessage = (string) ($errorMessage ?? '');
$notice = (string) ($notice ?? '');
$id = (int) ($church['id'] ?? 0);
$state = (string) ($church['estado'] ?? '');
$stateClass = $state === 'activa' ? 'ok' : (in_array($state, ['suspensa', 'encerrada'], true) ? 'bad' : 'pending');
?>
<div class="breadcrumb"><a href="<?= e(url('superadmin')) ?>">Plataforma</a> / <a href="<?= e(url('superadmin/igrejas')) ?>">Igrejas</a> / <?= e($church['nome'] ?? '') ?></div>
<div class="page-head"><div><h2><i class="fas fa-church"></i>Gerir igreja</h2><div class="sub">Dados da igreja, plano, estado e email de activação.</div></div><span class="status-pill <?= e($stateClass) ?>"><?= e($state) ?></span></div>
<?php if ($notice !== ''): ?><div class="success-box inline-notice" role="status"><i class="fas fa-circle-check"></i><span><?= e($notice) ?></span></div><?php endif; ?>
<?php if ($errorMessage !== ''): ?><div class="alert-danger inline-notice" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= e($errorMessage) ?></span></div><?php endif; ?>
<div class="panel" style="margin-bottom:22px"><h3><i class="fas fa-pen-to-square"></i>Dados principais</h3>
    <?php if ($state === 'encerrada'): ?><div class="empty-note">Esta igreja foi encerrada; os dados são mantidos para auditoria.</div><?php else: ?>
    <form class="admin-form" method="POST" action="<?= e(url('superadmin/igrejas/' . $id . '/editar')) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field-group"><label for="nome">Nome da igreja</label><input id="nome" name="nome" maxlength="180" value="<?= e($church['nome'] ?? '') ?>" required></div>
            <div class="field-group"><label for="plano_id">Plano</label><select id="plano_id" name="plano_id" required><?php foreach ($plans as $plan): ?><option value="<?= e((int) $plan['id']) ?>" <?= (int) ($church['plano_id'] ?? 0) === (int) $plan['id'] ? 'selected' : '' ?>><?= e($plan['nome']) ?><?= (int) ($plan['ativo'] ?? 0) !== 1 ? ' (inactivo)' : '' ?></option><?php endforeach; ?></select></div>
            <div class="field-group"><label for="telefone">Telefone</label><input id="telefone" name="telefone" maxlength="40" value="<?= e($church['telefone'] ?? '') ?>"></div>
            <div class="field-group"><label for="cidade">Cidade / município</label><input id="cidade" name="cidade" maxlength="120" value="<?= e($church['cidade'] ?? '') ?>"></div>
            <div class="field-group"><label>Email inicial do administrador</label><input value="<?= e($church['email_admin'] ?? '') ?>" disabled><div class="hint">O email de login é único e não é alterado nesta edição.</div></div>
            <div class="field-group"><label>Endereço amigável</label><input value="<?= e($church['slug'] ?? '') ?>" disabled></div>
        </div>
        <div class="admin-actions" style="margin-top:18px"><button class="admin-btn primary" type="submit"><i class="fas fa-floppy-disk"></i>Guardar alterações</button></div>
    </form>
    <?php endif; ?>
</div>
<div class="panel" style="margin-bottom:22px"><h3><i class="fas fa-shield-halved"></i>Estado de acesso</h3>
    <p class="empty-note" style="padding:0 0 14px">Suspender impede novos logins e as sessões activas são recusadas no próximo pedido. Encerrar é um fecho lógico: mantém os dados e não pode ser revertido pelo painel.</p>
    <div class="admin-actions">
        <?php if ($state === 'suspensa'): ?>
            <form method="POST" action="<?= e(url('superadmin/igrejas/' . $id . '/estado')) ?>"><?= csrf_field() ?><input type="hidden" name="estado" value="activa"><button class="admin-btn primary" type="submit"><i class="fas fa-circle-check"></i>Reactivar igreja</button></form>
        <?php elseif ($state === 'activa'): ?>
            <form method="POST" action="<?= e(url('superadmin/igrejas/' . $id . '/estado')) ?>" class="admin-actions"><?= csrf_field() ?><input type="hidden" name="estado" value="suspensa"><input name="motivo" maxlength="500" placeholder="Motivo da suspensão (opcional)" style="min-width:260px;padding:8px 10px;border:1px solid var(--line);border-radius:8px"><button class="admin-btn danger" type="submit"><i class="fas fa-ban"></i>Suspender</button></form>
        <?php elseif ($state === 'encerrada'): ?>
            <span class="status-pill bad">Acesso encerrado; dados mantidos.</span>
        <?php else: ?>
            <p class="empty-note" style="padding:0">A igreja ainda aguarda a activação do email.</p>
        <?php endif; ?>
        <?php if ($state !== 'encerrada'): ?>
            <form method="POST" action="<?= e(url('superadmin/igrejas/' . $id . '/estado')) ?>" class="admin-actions" onsubmit="return confirm('Encerrar o acesso desta igreja? Os dados serão mantidos e o encerramento não pode ser revertido por esta página.');"><?= csrf_field() ?><input type="hidden" name="estado" value="encerrada"><input name="motivo" maxlength="500" placeholder="Motivo do encerramento (opcional)" style="min-width:260px;padding:8px 10px;border:1px solid var(--line);border-radius:8px"><button class="admin-btn danger" type="submit"><i class="fas fa-box-archive"></i>Encerrar sem apagar dados</button></form>
        <?php endif; ?>
    </div>
</div>
<div class="panel"><div class="panel-head"><h3><i class="fas fa-envelope"></i>Estado de emails</h3></div>
    <?php if ($emails === []): ?><div class="empty-note">Não há emails associados a esta igreja.</div>
    <?php else: ?>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Destinatário</th><th>Tipo</th><th>Estado</th><th>Tentativas</th><th>Último erro</th><th>Acção</th></tr></thead><tbody>
        <?php foreach ($emails as $email): ?>
            <?php $mailState = (string) ($email['estado'] ?? ''); $mailClass = $mailState === 'enviado' ? 'ok' : ($mailState === 'falhou' ? 'bad' : 'pending'); ?>
            <tr><td><?= e($email['destinatario'] ?? '') ?></td><td><?= e($email['template'] ?? '') ?></td><td><span class="status-pill <?= e($mailClass) ?>"><?= e($mailState) ?></span></td><td><?= e((int) ($email['tentativas'] ?? 0)) ?></td><td><?= e($email['erro'] ?? '') ?></td>
                <td><?php if (in_array((string) ($email['template'] ?? ''), ['activacao', 'redefinicao_senha'], true) && $mailState !== 'enviado'): ?><form method="POST" action="<?= e(url('superadmin/emails/' . (int) $email['id'] . '/reenviar')) ?>"><?= csrf_field() ?><button class="admin-btn small" type="submit"><i class="fas fa-rotate-right"></i>Tentar novamente</button></form><?php else: ?><span style="color:var(--brown-400)">—</span><?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
