<?php
/** @var array $user */
/** @var array $logs */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var int $perPage */
/** @var string $modulo */
/** @var string $acao */
/** @var string $de */
/** @var string $ate */
/** @var string $q */
/** @var array $modulos */
/** @var array $acoes */

$acaoCor = [
    'criar' => 'var(--success)',
    'editar' => 'var(--info)',
    'eliminar' => 'var(--danger)',
    'desativar' => 'var(--danger)',
    'fechar_mes' => 'var(--gold-700)',
    'reabrir_mes' => 'var(--gold-700)',
    'upload_logo' => 'var(--info)',
];
?>
<div class="page-head">
    <h1 class="page-title">Auditoria</h1>
    <span class="muted" style="font-size:13px"><?= number_format($total, 0, ',', '.') ?> registo(s)</span>
</div>

<div class="card">
    <form method="get" action="<?= esc(url('auditoria')) ?>">
        <div class="field" style="position:relative;margin-bottom:12px">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"
               style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-soft);font-size:13px"></i>
            <input class="input" name="q" placeholder="Pesquisar entidade ou ID..." value="<?= esc($q) ?>"
                   style="padding-left:38px">
        </div>

        <div class="filters" style="display:grid;grid-template-columns:repeat(4, 1fr) auto;gap:10px">
            <select class="select" name="modulo">
                <option value="">Todos os módulos</option>
                <?php foreach ($modulos as $m): ?>
                    <option value="<?= esc($m['modulo']) ?>" <?= $modulo === $m['modulo'] ? 'selected' : '' ?>>
                        <?= esc(ucfirst($m['modulo'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select class="select" name="acao">
                <option value="">Todas as ações</option>
                <?php foreach ($acoes as $a): ?>
                    <option value="<?= esc($a['acao']) ?>" <?= $acao === $a['acao'] ? 'selected' : '' ?>>
                        <?= esc($a['acao']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input class="input" type="date" name="de" value="<?= esc($de) ?>" title="De">
            <input class="input" type="date" name="ate" value="<?= esc($ate) ?>" title="Até">

            <button type="submit" class="btn btn-outline">
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
            </button>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:14px">
        <table class="table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Utilizador</th>
                    <th>Módulo</th>
                    <th>Ação</th>
                    <th>Entidade</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="muted" style="text-align:center;padding:30px">
                        <i class="fa-solid fa-clipboard-list" aria-hidden="true" style="font-size:24px;color:var(--gold-300);display:block;margin-bottom:8px"></i>
                        Nenhum registo de auditoria.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="muted" style="font-size:12px;white-space:nowrap">
                                <?= esc(date('d/m/Y H:i', strtotime($l['criado_em']))) ?>
                            </td>
                            <td>
                                <?php if ($l['user_nome']): ?>
                                    <?= esc($l['user_nome']) ?>
                                <?php else: ?>
                                    <span class="muted">Sistema</span>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-size:12px;background:var(--gold-100);color:var(--gold-700);padding:2px 8px;border-radius:999px"><?= esc($l['modulo']) ?></span></td>
                            <td>
                                <strong style="color:<?= $acaoCor[$l['acao']] ?? 'var(--brown-900)' ?>">
                                    <?= esc($l['acao']) ?>
                                </strong>
                            </td>
                            <td class="muted" style="font-size:12.5px">
                                <?= esc($l['entidade'] ?? '—') ?>
                                <?php if ($l['entidade_id']): ?>
                                    <span class="muted">#<?= esc($l['entidade_id']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="muted" style="font-size:12px"><?= esc($l['ip'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pager-row" style="display:flex;justify-content:space-between;align-items:center;padding-top:16px;font-size:13px;color:var(--text-muted);flex-wrap:wrap;gap:10px">
        <span>Mostrando <?= $total ? (($page - 1) * $perPage + 1) : 0 ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?></span>
        <?php if ($pages > 1): ?>
            <div class="pager" style="display:flex;gap:4px">
                <?php $start = max(1, $page - 2); $end = min($pages, $page + 2); ?>
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_filter(['modulo' => $modulo, 'acao' => $acao, 'de' => $de, 'ate' => $ate, 'q' => $q, 'page' => $page - 1])) ?>"
                       style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;color:var(--brown-800)">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true" style="font-size:11px"></i>
                    </a>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?<?= http_build_query(array_filter(['modulo' => $modulo, 'acao' => $acao, 'de' => $de, 'ate' => $ate, 'q' => $q, 'page' => $i])) ?>"
                       style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;<?= $i === $page ? 'background:var(--gold-500);color:#fff' : 'color:var(--brown-800)' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                    <a href="?<?= http_build_query(array_filter(['modulo' => $modulo, 'acao' => $acao, 'de' => $de, 'ate' => $ate, 'q' => $q, 'page' => $page + 1])) ?>"
                       style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;color:var(--brown-800)">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true" style="font-size:11px"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>