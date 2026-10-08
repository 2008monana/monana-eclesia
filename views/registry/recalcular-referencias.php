<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-calculator"></i> Recalcular Referências de Mês</h2>
            <a href="<?= url('modules/registry/index.php') ?>" style="display:inline-flex;align-items:center;gap:6px;color:var(--gray-500);text-decoration:none;font-weight:600;font-size:13px;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <div style="background:#fcf8ec;border:1px solid #eddba0;color:var(--blue-800);padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:13px;line-height:1.5;">
            <i class="fas fa-info-circle"></i>
            Esta ferramenta corrige registos <strong>antigos</strong> cujo mês de referência ficou preso ao mês do calendário
            em que o pagamento entrou, em vez do mês mais antigo em dívida. Reprocessa os pagamentos de cada membro em
            ordem cronológica e reatribui os meses seguindo a mesma regra usada nos registos novos (1.000 Kz por mês,
            do mais antigo para o mais recente) - <strong>cada pagamento fica sempre inteiro num único mês</strong>, mesmo
            que ultrapasse a cota desse mês; nunca é dividido entre dois meses. Se algum pagamento já tiver sido
            dividido por uma versão anterior desta ferramenta, ele é automaticamente consolidado de volta num só registo.
            <br><br>
            <strong>⚠️ Recomendado:</strong> gere um backup antes de aplicar, em
            <a href="<?= url('modules/backups/index.php') ?>" style="color:var(--blue-800);font-weight:700;">Backups → Exportar SQL</a>.
            A "Análise" abaixo é apenas leitura - nada é alterado até você marcar os membros e clicar em "Aplicar Correções".
            <br><br>
            <span style="font-size:12px;color:var(--gray-600);">
                Nota técnica: a tabela de cotas usa o motor MyISAM (não suporta transações), por isso a aplicação insere
                sempre os registos corrigidos primeiro e só remove os antigos depois de confirmar que todos foram gravados -
                mas, para segurança total, considere converter a tabela para InnoDB (<code>ALTER TABLE cotas_diarias ENGINE=InnoDB;</code>).
            </span>
        </div>

        <?php if ($erro): ?>
        <div style="background:#f9e9e5;border:1px solid #f5c6c6;color:#721c24;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:13px;">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?>
        </div>
        <?php endif; ?>

        <?php if ($mensagem): ?>
        <div style="background:#eaf3ec;border:1px solid #a5d6a7;color:#2e7d32;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:13px;">
            <?= $mensagem ?>
        </div>
        <?php endif; ?>

        <!-- FORMULÁRIO DE ANÁLISE -->
        <div class="panel" style="margin-bottom:20px;">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <input type="hidden" name="modo" value="analisar">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Ano</label>
                    <select name="ano" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <?php foreach (getAnosDisponiveis() as $i): ?>
                        <option value="<?= $i ?>" <?= $ano == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="min-width:220px;">
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Membro (deixe em branco para analisar todos)</label>
                    <select name="membro_id" style="width:100%;padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="0">Todos os membros com cotas em <?= $ano ?></option>
                        <?php foreach ($todos_membros as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $membro_id_filtro == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nome_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-primary" style="padding:9px 20px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                        <i class="fas fa-search"></i> Analisar
                    </button>
                </div>
            </form>
        </div>

        <?php if ($modo === 'analisar' || $mensagem): ?>

            <?php if (empty($resultados)): ?>
            <div class="panel" style="text-align:center;padding:40px;color:var(--gray-400);">
                <i class="fas fa-check-circle" style="font-size:32px;display:block;margin-bottom:10px;color:#3f7d4e;"></i>
                Nenhum registo encontrado para o ano <?= $ano ?><?= $membro_id_filtro ? ' para este membro' : '' ?>.
            </div>
            <?php else: ?>

            <div class="panel" style="margin-bottom:16px;">
                <?php if ($total_com_diferenca === 0): ?>
                <div style="color:#3f7d4e;font-weight:600;font-size:13px;"><i class="fas fa-check-circle"></i> Todos os membros analisados já estão com o mês de referência correto - nada a corrigir.</div>
                <?php else: ?>
                <div style="color:var(--gray-700);font-weight:600;font-size:13px;margin-bottom:12px;">
                    <i class="fas fa-exclamation-triangle" style="color:#b9770e;"></i>
                    <?= $total_com_diferenca ?> de <?= count($resultados) ?> membro(s) têm registos com referência incorreta em <?= $ano ?>. Reveja abaixo e marque quem quer corrigir.
                </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="acao" value="aplicar">
                <input type="hidden" name="ano" value="<?= $ano ?>">

                <?php foreach ($resultados as $item):
                    $m = $item['membro'];
                    $r = $item['resultado'];
                    if (!$r['mudou']) continue; // só mostra quem realmente tem diferença
                ?>
                <div class="panel" style="margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                        <input type="checkbox" name="aplicar_membro[]" value="<?= $m['id'] ?>" id="chk_<?= $m['id'] ?>" style="width:16px;height:16px;">
                        <label for="chk_<?= $m['id'] ?>" style="font-weight:700;color:var(--blue-800);font-size:14px;cursor:pointer;">
                            <?= htmlspecialchars($m['nome_completo']) ?>
                        </label>
                        <span style="background:#fdf1d9;color:#b9770e;font-size:11px;font-weight:600;padding:2px 10px;border-radius:20px;">Referência incorreta</span>
                    </div>

                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                            <thead>
                                <tr style="background:var(--gray-100);">
                                    <th style="padding:6px 10px;text-align:left;">Data Pagamento</th>
                                    <th style="padding:6px 10px;text-align:left;">Referência(s) Atual(is)</th>
                                    <th style="padding:6px 10px;text-align:right;">Valor Atual</th>
                                    <th style="padding:6px 10px;text-align:center;">→</th>
                                    <th style="padding:6px 10px;text-align:left;">Referência Corrigida</th>
                                    <th style="padding:6px 10px;text-align:right;">Valor Corrigido</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Mostra um pagamento real por linha (nunca mais dividido). Se este
                                // pagamento já estava fragmentado em vários registos antigos (de uma
                                // versão anterior desta ferramenta), lista todas as referências
                                // antigas juntas, para deixar claro que foram consolidadas num só.
                                $originais_por_id = [];
                                foreach ($r['originais'] as $orig) {
                                    $originais_por_id[(int) $orig['id']] = $orig;
                                }
                                foreach ($r['novos'] as $n):
                                    $contribuintes = array_map(function ($id) use ($originais_por_id) {
                                        return $originais_por_id[$id];
                                    }, $n['orig_ids']);
                                    $fragmentado = count($contribuintes) > 1;
                                    $valor_atual_total = array_sum(array_map(function ($o) { return (float) $o['valor']; }, $contribuintes));
                                    $mudouEstePagamento = $fragmentado
                                        || $contribuintes[0]['mes_referencia'] !== $n['mes_referencia']
                                        || abs((float) $contribuintes[0]['valor'] - $n['valor']) > 0.001;
                                ?>
                                <tr style="border-bottom:1px solid var(--gray-100); <?= $mudouEstePagamento ? 'background:#fffbea;' : '' ?>">
                                    <td style="padding:6px 10px;"><?= date('d/m/Y', strtotime($n['data_pagamento'])) ?></td>
                                    <td style="padding:6px 10px;">
                                        <?php foreach ($contribuintes as $c): ?>
                                        <div><?= nomeMesPT((int) date('n', strtotime($c['mes_referencia']))) . '/' . date('Y', strtotime($c['mes_referencia'])) ?> (<?= number_format((float) $c['valor'], 2, ',', '.') ?> Kz)</div>
                                        <?php endforeach; ?>
                                        <?php if ($fragmentado): ?>
                                        <span style="font-size:10px;color:#b5412f;"><i class="fas fa-exclamation-triangle"></i> estava dividido em <?= count($contribuintes) ?> registos - será consolidado num só</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:right;"><?= number_format($valor_atual_total, 2, ',', '.') ?> Kz</td>
                                    <td style="padding:6px 10px;text-align:center;color:<?= $mudouEstePagamento ? '#b5412f' : 'var(--gray-300)' ?>;"><i class="fas fa-arrow-right"></i></td>
                                    <td style="padding:6px 10px;font-weight:<?= $mudouEstePagamento ? '700' : '400' ?>;color:<?= $mudouEstePagamento ? '#3f7d4e' : 'var(--gray-600)' ?>;">
                                        <?= nomeMesPT((int) date('n', strtotime($n['mes_referencia']))) . '/' . date('Y', strtotime($n['mes_referencia'])) ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:right;font-weight:<?= $mudouEstePagamento ? '700' : '400' ?>;">
                                        <?= number_format($n['valor'], 2, ',', '.') ?> Kz
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if ($total_com_diferenca > 0): ?>
                <div class="panel" style="position:sticky;bottom:16px;box-shadow:0 -4px 16px rgba(0,0,0,.08);">
                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <button type="button" onclick="marcarTodos(true)" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);background:#fff;color:var(--gray-600);font-size:12px;cursor:pointer;">Marcar todos</button>
                        <button type="button" onclick="marcarTodos(false)" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);background:#fff;color:var(--gray-600);font-size:12px;cursor:pointer;">Desmarcar todos</button>
                        <button type="submit" onclick="return confirm('Confirma a substituição dos registos de cota dos membros marcados? Esta ação reescreve dados financeiros e não tem \'desfazer\' automático.');" class="btn-primary" style="margin-left:auto;padding:12px 26px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;">
                            <i class="fas fa-check"></i> Aplicar Correções nos Marcados
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </form>

            <?php endif; ?>
        <?php endif; ?>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>

<script>
function marcarTodos(valor) {
    document.querySelectorAll('input[name="aplicar_membro[]"]').forEach(function (chk) {
        chk.checked = valor;
    });
}
</script>
