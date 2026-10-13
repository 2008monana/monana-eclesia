<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$membro = is_array($membro ?? null) ? $membro : [];
$memberId = (int) ($membro['id'] ?? 0);
$categoryLabels = [
    'mama' => 'Mamã',
    'papa' => 'Papá',
    'jovem' => 'Jovem',
    'crianca' => 'Criança',
];
$createdAt = isset($membro['criado_em']) ? strtotime((string) $membro['criado_em']) : false;
$birthDate = isset($membro['data_nascimento']) && $membro['data_nascimento'] !== ''
    ? strtotime((string) $membro['data_nascimento'])
    : false;
$isAdmin = !empty($isAdmin);
?>
<div class="page-head">
    <div>
        <h2><i class="fas fa-user" aria-hidden="true"></i><?= e($membro['nome_completo'] ?? 'Membro') ?></h2>
        <div class="sub">Informação do membro desta igreja.</div>
    </div>
    <div class="admin-actions">
        <a class="admin-btn" href="<?= e(url('membros/' . $memberId . '/editar')) ?>"><i class="fas fa-pen" aria-hidden="true"></i>Editar</a>
        <a class="admin-btn" href="<?= e(url('membros')) ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i>Voltar</a>
    </div>
</div>
<div class="panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <tbody>
                <tr><th scope="row">Categoria</th><td><?= e($categoryLabels[$membro['categoria'] ?? ''] ?? '—') ?></td></tr>
                <tr><th scope="row">Telefone</th><td><?= e($membro['telefone'] ?? 'Não informado') ?></td></tr>
                <tr><th scope="row">Data de nascimento</th><td><?= e($birthDate === false ? 'Não informado' : date('d/m/Y', $birthDate)) ?></td></tr>
                <tr><th scope="row">Endereço</th><td><?= e($membro['endereco'] ?? 'Não informado') ?></td></tr>
                <tr><th scope="row">Registado em</th><td><?= e($createdAt === false ? '—' : date('d/m/Y', $createdAt)) ?></td></tr>
                <tr><th scope="row">Estado</th><td><span class="status-pill<?= (int) ($membro['ativo'] ?? 0) === 1 ? ' ok' : ' bad' ?>"><?= (int) ($membro['ativo'] ?? 0) === 1 ? 'Activo' : 'Inactivo' ?></span></td></tr>
            </tbody>
        </table>
    </div>
    <div class="admin-actions" style="margin-top:18px">
        <form method="POST" action="<?= e(url('membros/' . $memberId . '/estado')) ?>">
            <?= csrf_field() ?>
            <button class="admin-btn" type="submit"><i class="fas <?= (int) ($membro['ativo'] ?? 0) === 1 ? 'fa-user-slash' : 'fa-user-check' ?>" aria-hidden="true"></i><?= (int) ($membro['ativo'] ?? 0) === 1 ? 'Desactivar' : 'Activar' ?></button>
        </form>
        <?php if ($isAdmin): ?>
            <form method="POST" action="<?= e(url('membros/' . $memberId . '/apagar')) ?>" onsubmit="return confirm('Sem histórico financeiro, o membro será removido; com histórico, será desactivado. Continuar?');">
                <?= csrf_field() ?>
                <button class="admin-btn danger" type="submit"><i class="fas fa-trash" aria-hidden="true"></i>Remover</button>
            </form>
        <?php endif; ?>
    </div>
    <p class="hint" style="margin-top:16px">A consulta de pagamentos/histórico será disponibilizada quando os módulos financeiros forem migrados com isolamento por igreja.</p>
</div>
