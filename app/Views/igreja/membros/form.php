<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$dados = is_array($dados ?? null) ? $dados : [];
$erros = is_array($erros ?? null) ? $erros : [];
$titulo = (string) ($titulo ?? 'Membro');
$action = (string) ($action ?? 'membros/novo');
$membroId = isset($membroId) ? (int) $membroId : null;
$categorias = [
    'mama' => 'Mamã',
    'papa' => 'Papá',
    'jovem' => 'Jovem',
    'crianca' => 'Criança',
];
?>
<div class="page-head">
    <div>
        <h2><i class="fas <?= $membroId === null ? 'fa-user-plus' : 'fa-user-pen' ?>" aria-hidden="true"></i><?= e($titulo) ?></h2>
        <div class="sub">Os dados são guardados apenas na igreja associada à tua sessão.</div>
    </div>
    <a class="admin-btn" href="<?= e(url('membros')) ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i>Voltar</a>
</div>
<div class="panel admin-form">
    <?php if ($erros !== []): ?>
        <div class="danger-zone" role="alert">
            <strong>Confirma os campos assinalados.</strong>
            <ul>
                <?php foreach ($erros as $field => $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="POST" action="<?= e(url($action)) ?>">
        <?= csrf_field() ?>
        <div class="field-group">
            <label for="membro-nome">Nome completo <span aria-hidden="true">*</span></label>
            <input id="membro-nome" name="nome" type="text" maxlength="180" required autocomplete="name" value="<?= e($dados['nome_completo'] ?? '') ?>">
            <?php if (isset($erros['nome_completo'])): ?><div class="hint" role="alert"><?= e($erros['nome_completo']) ?></div><?php endif; ?>
        </div>
        <div class="form-grid">
            <div class="field-group">
                <label for="membro-categoria">Categoria <span aria-hidden="true">*</span></label>
                <select id="membro-categoria" name="categoria" required>
                    <option value="">Selecciona uma categoria</option>
                    <?php foreach ($categorias as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= ($dados['categoria'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($erros['categoria'])): ?><div class="hint" role="alert"><?= e($erros['categoria']) ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="membro-telefone">Telefone</label>
                <input id="membro-telefone" name="telefone" type="tel" maxlength="40" autocomplete="tel" value="<?= e($dados['telefone'] ?? '') ?>">
                <?php if (isset($erros['telefone'])): ?><div class="hint" role="alert"><?= e($erros['telefone']) ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="membro-data-nascimento">Data de nascimento</label>
                <input id="membro-data-nascimento" name="data_nascimento" type="date" value="<?= e($dados['data_nascimento'] ?? '') ?>">
                <?php if (isset($erros['data_nascimento'])): ?><div class="hint" role="alert"><?= e($erros['data_nascimento']) ?></div><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="membro-endereco">Endereço</label>
                <textarea id="membro-endereco" name="endereco" maxlength="255" rows="3"><?= e($dados['endereco'] ?? '') ?></textarea>
                <?php if (isset($erros['endereco'])): ?><div class="hint" role="alert"><?= e($erros['endereco']) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="admin-actions" style="margin-top:18px">
            <button class="admin-btn primary" type="submit"><i class="fas fa-save" aria-hidden="true"></i><?= $membroId === null ? 'Guardar membro' : 'Guardar alterações' ?></button>
            <a class="admin-btn" href="<?= e(url('membros')) ?>">Cancelar</a>
        </div>
    </form>
</div>
