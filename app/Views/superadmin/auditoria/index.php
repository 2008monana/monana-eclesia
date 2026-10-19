<?php
/** @var array $supervisor */
/** @var array $logs */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var int $perPage */
/** @var string $modulo */
/** @var string $acao */
/** @var int $igrejaId */
/** @var int $supervisorId */
/** @var string $de */
/** @var string $ate */
/** @var string $q */
/** @var array $modulos */
/** @var array $acoes */
/** @var array $igrejas */
/** @var array $supervisores */

$acaoCor = [
    'criar' => 'var(--success)',
    'editar' => 'var(--info)',
    'eliminar' => 'var(--danger)',
    'desativar' => 'var(--danger)',
    'bloquear' => 'var(--danger)',
    'reativar' => 'var(--success)',
    'confirmar_pagamento' => 'var(--success)',
    'rejeitar_pagamento' => 'var(--danger)',
    'criar_igreja' => 'var(--success)',
    'renovar_assinatura' => 'var(--gold-700)',
    'fechar_mes' => 'var(--gold-700)',
    'restaurar' => 'var(--danger)',
];
?>
<div class="page-head">
    <h1 class="page-title">Auditoria Global</h1>
    <div style="display:flex;gap:10px;align-items:center">
        <span class="muted" style="font-size:13px"><?= number_format($total, 0, ',', '.') ?> registo(s)</span>
        <a href="<?= esc(url('superadmin/auditoria/exportar?' . http_build_query(array_filter(['modulo' => $modulo, 'acao' => $acao, 'igreja' => $igrejaId ?: '', 'de' => $de, 'ate' => $ate])))) ?>"
           class="btn btn-gold">
            <i class="fa-solid fa-download" aria-hidden="true"></i>
            Exportar CSV
        </a>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= esc(url('superadmin/auditoria')) ?>">
        <div class="field" style="position:relative;margin-bottom:12px">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"
               style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-soft);font-size:13px"></i>
            <input class="input" name="q" placeholder="Pesquisar entidade ou ID..." value="<?= esc($q) ?>"
                   style="padding-left:38px">
        </div>

        <div class="filters" style="display:grid;grid-template-columns:repeat(6, 1fr) auto;gap:10px">
            <select class="select" name="modulo">
                <option value="">Módulo</option>
                <?php foreach ($modulos as $m): ?>
                    <option value="<?= esc($m['modulo']) ?>" <?= $modulo === $m['modulo'] ? 'selected' : '' ?>>
                        <?= esc(ucfirst($m['modulo'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select class="select" name="acao">
                <option value="">Ação</option>
                <?php foreach ($acoes as $a): ?>
                    <option value="<?= esc($a['acao']) ?>" <?= $acao === $a['acao'] ? 'selected' : '' ?>>
                        <?= esc($a['acao']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select class="select" name="igreja">
                <option value="">Igreja</option>
                <?php foreach ($igrejas as $ig): ?>
                    <option value="<?= (int) $ig['id'] ?>" <?= $igrejaId === (int) $ig['id'] ? 'selected' : '' ?>>
                        <?= esc($ig['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select class="select" name="supervisor">
                <option value="">Supervisor</option>
                <?php foreach ($supervisores as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $supervisorId === (int) $s['id'] ? 'selected' : '' ?>>
                        <?= esc($s['nome']) ?>
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
                    <th>Quem</th>
                    <th>Igreja</th>
                    <th>Módulo</th>
                    <th>Ação</th>
                    <th>Entidade</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="muted" style="text-align:center;padding:30px">
                        <i class="fa-solid fa-clipboard-list" aria-hidden="true" style="font-size:24px;color:var(--gold-300);display:block;margin-bottom:8px"></i>
                        Nenhum registo encontrado.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="muted" style="font-size:12px;white-space:nowrap">
                                <?= esc(date('d/m/Y H:i', strtotime($l['criado_em']))) ?>
                            </td>
                            <td>
                                <?php if ($l['supervisor_nome']): ?>
                                    <span style="font-size:12px;background:var(--gold-100);color:var(--gold-700);padding:2px 8px;border-radius:999px;font-weight:500">
                                        <i class="fa-solid fa-user-shield" aria-hidden="true" style="font-size:10px"></i>
                                        <?= esc($l['supervisor_nome']) ?>
                                    </span>
                                <?php elseif ($l['user_nome']): ?>
                                    <span style="font-size:12.5px"><?= esc($l['user_nome']) ?></span>
                                <?php else: ?>
                                    <span class="muted">Sistema</span>
                                <?php endif; ?>
                            </td>
                            <td class="muted" style="font-size:12px"><?= esc($l['igreja_nome'] ?? '—') ?></td>
                            <td>
                                <span style="font-size:11.5px;background:var(--ivory-bg);color:var(--brown-800);padding:2px 8px;border-radius:999px">
                                    <?= esc($l['modulo']) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color:<?= $acaoCor[$l['acao']] ?? 'var(--brown-900)' ?>;font-size:12.5px">
                                    <?= esc($l['acao']) ?>
                                </strong>
                            </td>
                            <td class="muted" style="font-size:12px">
                                <?= esc($l['entidade'] ?? '—') ?>
                                <?php if ($l['entidade_id']): ?>
                                    <span class="muted">#<?= esc($l['entidade_id']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="muted" style="font-size:11.5px;font-family:monospace"><?= esc($l['ip'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pager-row" style="display:flex;justify-content:space-between;align-items:center;padding-top:16px;font-size:13px;color:var(--text-muted);flex-wrap:wrap;gap:10px">
        <span>Mostrando <?= $total ? (($page - 1) * $perPage + 1) : 0 ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?></span>
        <?php if ($pages > 1): ?>
            <div style="display:flex;gap:4px">
                <?php $start = max(1, $page - 2); $end = min($pages, $page + 2); ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?<?= http_build_query(array_filter(['modulo' => $modulo, 'acao' => $acao, 'igreja' => $igrejaId ?: '', 'supervisor' => $supervisorId ?: '', 'de' => $de, 'ate' => $ate, 'q' => $q, 'page' => $i])) ?>"
                       style="min-width:30px;height:30px;display:inline-grid;place-items:center;border-radius:6px;text-decoration:none;<?= $i === $page ? 'background:var(--gold-500);color:#fff' : 'color:var(--brown-800)' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>