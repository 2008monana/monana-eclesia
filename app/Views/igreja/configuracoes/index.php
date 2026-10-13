<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$config = is_array($configuracao ?? null) ? $configuracao : [];
$errors = is_array($erros ?? null) ? $erros : [];
$message = (string) ($mensagem ?? '');
$money = number_format((float) ($config['valor_cota'] ?? 0), 2, ',', '.');
?>
<div class="page-head"><div><h2><i class="fas fa-sliders" aria-hidden="true"></i>Configurações da igreja</h2><div class="sub">Dados de apresentação, cota e datas de início definidas manualmente.</div></div></div>
<?php if ($message !== ''): ?><div class="inline-notice" role="status"><?= e($message) ?></div><?php endif; ?>
<div class="panel" style="max-width:900px"><form method="POST" action="<?= e(url('configuracoes')) ?>" class="admin-form">
    <?= csrf_field() ?>
    <h3>Identidade</h3>
    <div class="field-group"><label for="config-nome">Nome de apresentação</label><input id="config-nome" name="nome_exibicao" maxlength="180" value="<?= e($config['nome_exibicao'] ?? '') ?>" required><?php if (isset($errors['nome_exibicao'])): ?><div class="field-error"><?= e($errors['nome_exibicao']) ?></div><?php endif; ?></div>
    <div class="field-group"><label for="config-subtitulo">Subtítulo</label><input id="config-subtitulo" name="subtitulo" maxlength="255" value="<?= e($config['subtitulo'] ?? '') ?>"><?php if (isset($errors['subtitulo'])): ?><div class="field-error"><?= e($errors['subtitulo']) ?></div><?php endif; ?></div>
    <div class="field-group"><label for="config-rodape">Rodapé</label><textarea id="config-rodape" name="rodape" maxlength="500"><?= e($config['rodape'] ?? '') ?></textarea><?php if (isset($errors['rodape'])): ?><div class="field-error"><?= e($errors['rodape']) ?></div><?php endif; ?></div>
    <div class="field-group"><label for="config-versiculo">Versículo ou mensagem</label><input id="config-versiculo" name="versiculo" maxlength="255" value="<?= e($config['versiculo'] ?? '') ?>"><?php if (isset($errors['versiculo'])): ?><div class="field-error"><?= e($errors['versiculo']) ?></div><?php endif; ?></div>
    <h3 style="margin-top:24px">Contribuições da igreja</h3>
    <div class="field-group"><label for="config-cota">Valor da cota (Kz)</label><input id="config-cota" name="valor_cota" type="text" inputmode="decimal" value="<?= e(isset($config['valor_cota']) ? $money : '') ?>" placeholder="1.250,00" required><div class="hint">Usa o formato 1.250,00. O valor zero mantém os pagamentos bloqueados. Depois do primeiro pagamento, valor, periodicidade e início ficam protegidos contra alterações nesta interface.</div><?php if (isset($errors['valor_cota'])): ?><div class="field-error"><?= e($errors['valor_cota']) ?></div><?php endif; ?></div>
    <div class="field-group"><label for="config-periodicidade">Periodicidade da cota</label><select id="config-periodicidade" name="periodicidade_cota"><option value="mensal"<?= ($config['periodicidade_cota'] ?? '') === 'mensal' ? ' selected' : '' ?>>Mensal</option><option value="anual"<?= ($config['periodicidade_cota'] ?? '') === 'anual' ? ' selected' : '' ?>>Anual · ciclo móvel de 12 meses</option></select><?php if (isset($errors['periodicidade_cota'])): ?><div class="field-error"><?= e($errors['periodicidade_cota']) ?></div><?php endif; ?></div>
    <div class="form-grid"><div class="field-group"><label for="config-inicio-cotas">Início das cotas</label><input id="config-inicio-cotas" type="date" name="inicio_cotas" value="<?= e($config['inicio_cotas'] ?? '') ?>"><div class="hint">Definido manualmente. O ciclo anual não reinicia em Janeiro; após o primeiro pagamento, esta data fica protegida.</div><?php if (isset($errors['inicio_cotas'])): ?><div class="field-error"><?= e($errors['inicio_cotas']) ?></div><?php endif; ?></div><div class="field-group"><label for="config-inicio-fim-ano">Início das contribuições de fim de ano</label><input id="config-inicio-fim-ano" type="date" name="inicio_contribuicoes" value="<?= e($config['inicio_contribuicoes'] ?? '') ?>"><div class="hint">Os pagamentos permanecem bloqueados enquanto a data não estiver definida; após o primeiro pagamento, a data fica protegida.</div><?php if (isset($errors['inicio_contribuicoes'])): ?><div class="field-error"><?= e($errors['inicio_contribuicoes']) ?></div><?php endif; ?></div></div>
    <div class="phase-notice" style="margin-bottom:18px"><strong>Importante:</strong> a data de início não é inferida da criação da igreja nem de Janeiro. Confirma o mapeamento histórico antes da Fase 6. O valor monetário é exibido em Kz e armazenado como AOA.</div>
    <div class="admin-actions"><button class="admin-btn primary" type="submit"><i class="fas fa-save" aria-hidden="true"></i>Guardar configurações</button></div>
</form></div>
