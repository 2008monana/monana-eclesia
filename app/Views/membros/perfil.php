<?php
/** @var array $user */
/** @var array $membro */
/** @var array $departamentosDoMembro */
/** @var array $celulasDoMembro */
/** @var array $familia */
/** @var array $contribuicoes */

use App\Core\Rbac;

$iniciais = '';
$partes = preg_split('/\s+/', trim($membro['nome']));
$iniciais = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));

$isActivo = $membro['status'] === 'activo';

$totalContribuicoes = 0;
foreach ($contribuicoes as $c) {
    if ($c['tipo'] === 'entrada') {
        $totalContribuicoes += (float) $c['valor_kz'];
    }
}
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('membros')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title">Perfil do Membro</h1>
    </div>
    <?php if (Rbac::can('membros.editar')): ?>
        <a href="<?= esc(url('membros/' . $membro['id'] . '/editar')) ?>" class="btn btn-gold">
            <i class="fa-solid fa-pen" aria-hidden="true"></i>
            Editar
        </a>
    <?php endif; ?>
</div>

<!-- Cartão de perfil -->
<section class="card" style="padding:24px 26px">
    <div class="profile-top" style="display:flex;gap:22px;align-items:center;flex-wrap:wrap">
        <?php if (!empty($membro['foto'])): ?>
            <img src="<?= esc(asset('uploads/membros/' . $membro['foto'])) ?>"
                 alt="" class="avatar" width="96" height="96"
                 style="border-radius:50%">
        <?php else: ?>
            <span class="avatar" style="width:96px;height:96px;display:inline-grid;place-items:center;color:var(--gold-700);font-weight:600;font-size:30px;background:var(--gold-100);border-radius:50%">
                <?= esc($iniciais) ?>
            </span>
        <?php endif; ?>
        <div>
            <h2 style="font-size:20px;color:var(--brown-900);display:flex;align-items:center;gap:10px;margin:0">
                <?= esc($membro['nome']) ?>
                <span class="status <?= $isActivo ? 'ok' : 'off' ?>">
                    <?= esc(ucfirst($membro['status'])) ?>
                </span>
            </h2>
            <div class="profile-meta" style="display:flex;flex-direction:column;gap:7px;margin-top:10px;font-size:13px;color:var(--text-muted)">
                <div><i class="fa-solid fa-hashtag" aria-hidden="true" style="color:var(--gold-600);width:16px"></i> <?= esc($membro['codigo']) ?></div>
                <?php if ($membro['cargo']): ?>
                    <div><i class="fa-solid fa-briefcase" aria-hidden="true" style="color:var(--gold-600);width:16px"></i> <?= esc($membro['cargo']) ?></div>
                <?php endif; ?>
                <?php if ($membro['telefone']): ?>
                    <div><i class="fa-solid fa-phone" aria-hidden="true" style="color:var(--gold-600);width:16px"></i> <?= esc($membro['telefone']) ?></div>
                <?php endif; ?>
                <?php if ($membro['email']): ?>
                    <div><i class="fa-solid fa-envelope" aria-hidden="true" style="color:var(--gold-600);width:16px"></i> <?= esc($membro['email']) ?></div>
                <?php endif; ?>
                <?php if ($membro['membro_desde']): ?>
                    <div><i class="fa-solid fa-calendar" aria-hidden="true" style="color:var(--gold-600);width:16px"></i> Membro desde <?= esc(date('d/m/Y', strtotime($membro['membro_desde']))) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs" style="display:flex;gap:6px;border-bottom:1px solid var(--line);margin:22px 0 18px;overflow-x:auto">
        <button type="button" class="tab-btn on" data-tab="info"
                style="background:none;border:0;padding:10px 22px;font-size:13.5px;color:var(--gold-700);border-bottom:2px solid var(--gold-500);margin-bottom:-1px;font-weight:500;cursor:pointer">
            Informações
        </button>
        <button type="button" class="tab-btn" data-tab="familia"
                style="background:none;border:0;padding:10px 22px;font-size:13.5px;color:var(--text-muted);border-bottom:2px solid transparent;margin-bottom:-1px;cursor:pointer">
            Família
        </button>
        <button type="button" class="tab-btn" data-tab="departamentos"
                style="background:none;border:0;padding:10px 22px;font-size:13.5px;color:var(--text-muted);border-bottom:2px solid transparent;margin-bottom:-1px;cursor:pointer">
            Departamentos
        </button>
        <button type="button" class="tab-btn" data-tab="contribuicoes"
                style="background:none;border:0;padding:10px 22px;font-size:13.5px;color:var(--text-muted);border-bottom:2px solid transparent;margin-bottom:-1px;cursor:pointer">
            Contribuições
        </button>
    </div>

    <!-- Tab: Informações -->
    <div class="tab-content" data-tab-content="info">
        <div class="info-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:18px 40px">
            <div><div class="k" style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Data de nascimento</div><div class="v" style="font-size:14px;color:var(--brown-900)"><?= $membro['data_nascimento'] ? esc(date('d/m/Y', strtotime($membro['data_nascimento']))) : '—' ?></div></div>
            <div><div class="k" style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Género</div><div class="v" style="font-size:14px;color:var(--brown-900)"><?= $membro['genero'] === 'M' ? 'Masculino' : ($membro['genero'] === 'F' ? 'Feminino' : '—') ?></div></div>
            <div><div class="k" style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Estado civil</div><div class="v" style="font-size:14px;color:var(--brown-900)"><?= esc($membro['estado_civil'] ?? '—') ?></div></div>
            <div><div class="k" style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Endereço</div><div class="v" style="font-size:14px;color:var(--brown-900)"><?= esc($membro['endereco'] ?? '—') ?></div></div>
            <div><div class="k" style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Cidade / Província</div><div class="v" style="font-size:14px;color:var(--brown-900)"><?= esc(($membro['cidade'] ?? '') . ($membro['provincia'] ? ' / ' . $membro['provincia'] : '')) ?></div></div>
            <div><div class="k" style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Data de conversão</div><div class="v" style="font-size:14px;color:var(--brown-900)"><?= $membro['data_conversao'] ? esc(date('d/m/Y', strtotime($membro['data_conversao']))) : '—' ?></div></div>
            <div><div class="k" style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Data de batismo</div><div class="v" style="font-size:14px;color:var(--brown-900)"><?= $membro['data_baptismo'] ? esc(date('d/m/Y', strtotime($membro['data_baptismo']))) : '—' ?></div></div>
        </div>
        <?php if ($membro['observacoes']): ?>
            <div style="margin-top:22px;padding:14px;background:var(--gold-50);border-radius:8px;font-size:13.5px;color:var(--brown-800)">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Observações</div>
                <?= nl2br(esc($membro['observacoes'])) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab: Família -->
    <div class="tab-content" data-tab-content="familia" style="display:none">
        <?php if (empty($familia)): ?>
            <p class="muted">Nenhum familiar registado.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Nome</th><th>Código</th><th>Relação</th><th style="text-align:right"></th></tr></thead>
                    <tbody>
                        <?php foreach ($familia as $f): ?>
                            <tr>
                                <td><a href="<?= esc(url('membros/' . $f['id'])) ?>" style="color:var(--brown-900);font-weight:500;text-decoration:none"><?= esc($f['nome']) ?></a></td>
                                <td class="muted"><?= esc($f['codigo']) ?></td>
                                <td><span class="status ok"><?= esc(ucfirst($f['relacao'])) ?></span></td>
                                <td style="text-align:right">
                                    <?php if (Rbac::can('membros.editar')): ?>
                                        <form method="post" action="<?= esc(url('membros/' . $membro['id'] . '/familia/' . $f['id'] . '/remover')) ?>" style="margin:0" onsubmit="return confirm('Remover este familiar?')">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="icon-btn" title="Remover">
                                                <i class="fa-solid fa-trash" aria-hidden="true" style="color:var(--danger)"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (Rbac::can('membros.editar')): ?>
            <h3 style="font-size:14px;margin:22px 0 12px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
                <i class="fa-solid fa-user-plus" aria-hidden="true" style="color:var(--gold-600)"></i>
                Adicionar familiar
            </h3>
            <form method="post" action="<?= esc(url('membros/' . $membro['id'] . '/familia')) ?>"
                  style="display:grid;grid-template-columns:1fr 200px auto;gap:12px;max-width:700px">
                <?= csrf_field() ?>
                <select name="parente_id" class="select" required>
                    <option value="">Escolher membro...</option>
                    <?php
                    $outrosMembros = \App\Core\Database::select(
                        'SELECT `id`, `nome`, `codigo` FROM `members`
                         WHERE `igreja_id` = :i AND `deletado_em` IS NULL AND `id` != :m
                         ORDER BY `nome`',
                        ['i' => \App\Core\Tenant::id(), 'm' => $membro['id']]
                    );
                    foreach ($outrosMembros as $om):
                    ?>
                        <option value="<?= (int) $om['id'] ?>"><?= esc($om['nome']) ?> (<?= esc($om['codigo']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select name="relacao" class="select" required>
                    <option value="">Relação...</option>
                    <option value="conjuge">Cônjuge</option>
                    <option value="filho">Filho(a)</option>
                    <option value="pai">Pai</option>
                    <option value="mae">Mãe</option>
                    <option value="irmao">Irmão(ã)</option>
                    <option value="outro">Outro</option>
                </select>
                <button type="submit" class="btn btn-gold">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Adicionar
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Tab: Departamentos -->
    <div class="tab-content" data-tab-content="departamentos" style="display:none">
        <?php if (empty($departamentosDoMembro)): ?>
            <p class="muted">Nenhum departamento associado.</p>
        <?php else: ?>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px">
                <?php foreach ($departamentosDoMembro as $d): ?>
                    <li style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--gold-50);border-radius:8px">
                        <span style="width:10px;height:10px;border-radius:50%;background:<?= esc($d['cor'] ?: '#b8862b') ?>"></span>
                        <span style="font-weight:500;color:var(--brown-900)"><?= esc($d['nome']) ?></span>
                        <span class="muted" style="margin-left:auto;font-size:12.5px">
                            <?= esc($d['funcao'] ?? 'Membro') ?>
                            <?php if ($d['desde']): ?>
                                · desde <?= esc(date('d/m/Y', strtotime($d['desde']))) ?>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($celulasDoMembro)): ?>
            <h3 style="font-size:14px;margin:22px 0 12px;color:var(--brown-900)">Células</h3>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px">
                <?php foreach ($celulasDoMembro as $c): ?>
                    <li style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--gold-50);border-radius:8px">
                        <i class="fa-solid fa-people-roof" aria-hidden="true" style="color:var(--gold-600)"></i>
                        <span style="font-weight:500;color:var(--brown-900)"><?= esc($c['nome']) ?></span>
                        <span class="muted" style="margin-left:auto;font-size:12.5px">
                            <?= esc(ucfirst($c['dia_semana'] ?? '—')) ?> · <?= esc(substr((string) $c['hora'], 0, 5)) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Tab: Contribuições -->
    <div class="tab-content" data-tab-content="contribuicoes" style="display:none">
        <?php if (empty($contribuicoes)): ?>
            <p class="muted">Nenhuma contribuição registada.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th style="text-align:right">Valor</th></tr></thead>
                    <tbody>
                        <?php foreach ($contribuicoes as $c): ?>
                            <tr>
                                <td class="muted"><?= esc(date('d/m/Y', strtotime($c['data']))) ?></td>
                                <td><?= esc($c['descricao']) ?></td>
                                <td class="muted"><?= esc($c['categoria'] ?? '—') ?></td>
                                <td style="text-align:right;font-weight:500;<?= $c['tipo'] === 'entrada' ? 'color:var(--success)' : 'color:var(--danger)' ?>">
                                    <?= $c['tipo'] === 'entrada' ? '+' : '−' ?><?= fmtKz($c['valor_kz']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align:right;padding-top:14px;font-size:13.5px">
                Total contribuído: <b><?= fmtKz($totalContribuicoes) ?></b>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.style.color = 'var(--text-muted)';
                b.style.borderBottom = '2px solid transparent';
                b.style.fontWeight = 'normal';
            });
            btn.style.color = 'var(--gold-700)';
            btn.style.borderBottom = '2px solid var(--gold-500)';
            btn.style.fontWeight = '500';

            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            document.querySelector(`[data-tab-content="${btn.dataset.tab}"]`).style.display = '';
        });
    });
</script>