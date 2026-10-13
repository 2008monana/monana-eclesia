<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$membros = is_array($membros ?? null) ? $membros : [];
$estatisticas = is_array($estatisticas ?? null) ? $estatisticas : [];
$filtros = is_array($filtros ?? null) ? $filtros : ['q' => '', 'categoria' => '', 'estado' => 'activo'];
$pagina = max(1, (int) ($pagina ?? 1));
$paginas = max(1, (int) ($paginas ?? 1));
$totalFiltrado = max(0, (int) ($totalFiltrado ?? 0));
$mensagem = (string) ($mensagem ?? '');
$isAdmin = !empty($isAdmin);
$categorias = [
    'mama' => 'Mamã',
    'papa' => 'Papá',
    'jovem' => 'Jovem',
    'crianca' => 'Criança',
];
?>
<div class="page-head">
    <div>
        <h2><i class="fas fa-users" aria-hidden="true"></i>Membros</h2>
        <div class="sub">Registo de membros desta igreja.</div>
    </div>
    <a class="admin-btn primary" href="<?= e(url('membros/novo')) ?>"><i class="fas fa-plus" aria-hidden="true"></i>Adicionar membro</a>
</div>

<?php if ($mensagem !== ''): ?>
    <div class="inline-notice status-pill ok" role="status"><?= e($mensagem) ?></div>
<?php endif; ?>

<div class="grid-stats" style="margin-bottom:20px">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-users" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($estatisticas['total'] ?? 0)) ?></div><div class="stat-label">Total</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-female" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($estatisticas['mamas'] ?? 0)) ?></div><div class="stat-label">Mamãs activas</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-male" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($estatisticas['papas'] ?? 0)) ?></div><div class="stat-label">Papás activos</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-graduate" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($estatisticas['jovens'] ?? 0)) ?></div><div class="stat-label">Jovens activos</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-child" aria-hidden="true"></i></div><div class="stat-num"><?= e((int) ($estatisticas['criancas'] ?? 0)) ?></div><div class="stat-label">Crianças activas</div></div>
</div>

<div class="panel" style="margin-bottom:20px">
    <form method="GET" action="<?= e(url('membros')) ?>" class="admin-form" style="max-width:none;display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap">
        <div class="field-group" style="flex:1;min-width:200px;margin:0">
            <label for="membro-q">Pesquisar</label>
            <input id="membro-q" type="search" name="q" value="<?= e($filtros['q'] ?? '') ?>" maxlength="120" placeholder="Nome ou telefone">
        </div>
        <div class="field-group" style="min-width:150px;margin:0">
            <label for="membro-categoria">Categoria</label>
            <select id="membro-categoria" name="categoria">
                <option value="">Todas</option>
                <?php foreach ($categorias as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= ($filtros['categoria'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-group" style="min-width:150px;margin:0">
            <label for="membro-estado">Estado</label>
            <select id="membro-estado" name="estado">
                <option value="activo"<?= ($filtros['estado'] ?? '') === 'activo' ? ' selected' : '' ?>>Activos</option>
                <option value="inactivo"<?= ($filtros['estado'] ?? '') === 'inactivo' ? ' selected' : '' ?>>Inactivos</option>
                <option value="todos"<?= ($filtros['estado'] ?? '') === 'todos' ? ' selected' : '' ?>>Todos</option>
            </select>
        </div>
        <button class="admin-btn" type="submit"><i class="fas fa-magnifying-glass" aria-hidden="true"></i>Pesquisar</button>
        <a class="admin-btn" href="<?= e(url('membros')) ?>">Limpar</a>
    </form>
</div>

<div class="panel">
    <div class="panel-head">
        <h3><i class="fas fa-list" aria-hidden="true"></i>Lista de membros</h3>
        <span class="empty-note" style="padding:0"><?= e($totalFiltrado) ?> registo(s)</span>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th>#</th><th>Nome</th><th>Categoria</th><th>Telefone</th><th>Registado em</th><th>Estado</th><th>Acções</th></tr>
            </thead>
            <tbody>
                <?php if ($membros === []): ?>
                    <tr><td colspan="7" class="empty-note">Não foram encontrados membros para estes filtros.</td></tr>
                <?php else: ?>
                    <?php foreach ($membros as $membro): ?>
                        <?php
                        $memberId = (int) ($membro['id'] ?? 0);
                        $category = (string) ($membro['categoria'] ?? '');
                        $createdAt = isset($membro['criado_em']) ? strtotime((string) $membro['criado_em']) : false;
                        ?>
                        <tr>
                            <td><?= e($memberId) ?></td>
                            <td><a href="<?= e(url('membros/' . $memberId)) ?>"><?= e($membro['nome_completo'] ?? '') ?></a></td>
                            <td><?= e($categorias[$category] ?? '—') ?></td>
                            <td><?= e($membro['telefone'] ?? '—') ?></td>
                            <td><?= e($createdAt === false ? '—' : date('d/m/Y', $createdAt)) ?></td>
                            <td><span class="status-pill<?= (int) ($membro['ativo'] ?? 0) === 1 ? ' ok' : ' bad' ?>"><?= (int) ($membro['ativo'] ?? 0) === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            <td>
                                <div class="admin-actions">
                                    <a class="admin-btn small" href="<?= e(url('membros/' . $memberId)) ?>" aria-label="Ver membro"><i class="fas fa-eye" aria-hidden="true"></i></a>
                                    <a class="admin-btn small" href="<?= e(url('membros/' . $memberId . '/editar')) ?>" aria-label="Editar membro"><i class="fas fa-pen" aria-hidden="true"></i></a>
                                    <form method="POST" action="<?= e(url('membros/' . $memberId . '/estado')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="admin-btn small" type="submit" aria-label="<?= (int) ($membro['ativo'] ?? 0) === 1 ? 'Desactivar' : 'Activar' ?> membro">
                                            <i class="fas <?= (int) ($membro['ativo'] ?? 0) === 1 ? 'fa-user-slash' : 'fa-user-check' ?>" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <?php if ($isAdmin): ?>
                                        <form method="POST" action="<?= e(url('membros/' . $memberId . '/apagar')) ?>" onsubmit="return confirm('Sem histórico financeiro, o membro será removido; com histórico, será desactivado. Continuar?');">
                                            <?= csrf_field() ?>
                                            <button class="admin-btn small danger" type="submit" aria-label="Remover ou desactivar membro"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginas > 1): ?>
        <?php $queryParams = $filtros; ?>
        <nav class="admin-actions" aria-label="Paginação" style="justify-content:center;margin-top:18px">
            <?php if ($pagina > 1): ?>
                <?php $queryParams['pagina'] = $pagina - 1; ?>
                <a class="admin-btn small" href="<?= e(url('membros') . '?' . http_build_query($queryParams)) ?>">Anterior</a>
            <?php endif; ?>
            <span class="status-pill">Página <?= e($pagina) ?> de <?= e($paginas) ?></span>
            <?php if ($pagina < $paginas): ?>
                <?php $queryParams['pagina'] = $pagina + 1; ?>
                <a class="admin-btn small" href="<?= e(url('membros') . '?' . http_build_query($queryParams)) ?>">Seguinte</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
