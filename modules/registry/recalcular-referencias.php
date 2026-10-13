<?php
// modules/registry/recalcular-referencias.php
//
// FERRAMENTA DE CORREÇÃO DE DADOS ANTIGOS
// ==========================================================
// Registos de cotas criados ANTES da distribuição automática existir (ou
// editados manualmente em edit.php) podem ter o "mês de referência" preso
// ao mês do calendário em que o dinheiro entrou, em vez do mês mais antigo
// que o membro ainda devia - o que faz meses antigos aparecerem em dívida
// mesmo havendo dinheiro suficiente já pago mais adiante no ano.
//
// Esta página reprocessa, por membro e por ano, todos os pagamentos em
// ordem cronológica (data_pagamento, id) e reaplica exatamente a mesma
// regra usada em modules/lists/functions.php::distribuirPagamentoPorMeses():
// preenche sempre o mês mais antigo em dívida até 1.000 Kz antes de passar
// ao seguinte, dividindo um pagamento entre dois meses quando necessário.
//
// Fluxo: 1) ANALISAR (GET, só leitura) mostra uma pré-visualização com o
// que mudaria. 2) Só depois de rever, o utilizador confirma e os registos
// desse membro/ano são substituídos dentro de uma transação.
//
// Restrito a administradores - reescreve registos financeiros.
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}

require_once '../../config/session.php';
requireModuleAccess('registry');

if (($_SESSION['user_perfil'] ?? '') !== 'admin') {
    redirect('profile?sem_acesso=1');
    exit;
}

$page_title = 'Recalcular Referências - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../modules/audit/functions.php';
require_once '../lists/functions.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$conn = getConnection();

// ============================================
// FUNÇÃO PRINCIPAL DE RECÁLCULO (só lê a BD, não escreve nada)
// ============================================
/**
 * Reprocessa os pagamentos de um membro num ano, na ordem em que
 * entraram, e devolve a distribuição "correta" (mês mais antigo em
 * dívida primeiro), junto com a distribuição atual, para comparação.
 *
 * IMPORTANTE (corrigido): um pagamento real NUNCA é dividido entre dois
 * meses. Cada pagamento (identificado pela sua data_pagamento) é atribuído
 * por inteiro a um único mês de referência - o mês mais antigo ainda em
 * dívida nesse momento - mesmo que o valor ultrapasse a cota desse mês.
 * O excedente fica "adiantado" nesse mesmo mês; é o saldo acumulado
 * (calcularSaldoMembro / verificarStatusMes) que reconhece esse adiantamento
 * e mostra os meses seguintes como quitados, sem precisar de fragmentar o
 * registo. Isto evita que o registo diário e as listas mostrem um único
 * pagamento partido em pedaços.
 *
 * Também consolida, num único registo, pagamentos que uma versão anterior
 * desta ferramenta já tenha dividido entre dois meses (vários registos com
 * a mesma data_pagamento passam a ser tratados como um só pagamento real).
 */
function calcularRedistribuicao($conn, $membro_id, $ano) {
    $cota = getCotaMensal();

    $stmt = $conn->prepare("
        SELECT id, data_pagamento, mes_referencia, valor, observacao, registrado_por, criado_em
        FROM cotas_diarias
        WHERE membro_id = :membro_id AND YEAR(data_pagamento) = :ano
        ORDER BY data_pagamento ASC, id ASC
    ");
    $stmt->execute([':membro_id' => $membro_id, ':ano' => $ano]);
    $originais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($originais)) {
        return null;
    }

    // 1) Agrupar por data_pagamento: vários registos com a MESMA data são,
    // na prática, um único pagamento real - possivelmente já fragmentado por
    // uma versão anterior desta ferramenta. Junta-os antes de recalcular.
    $pagamentos = [];
    foreach ($originais as $r) {
        $data = $r['data_pagamento'];
        if (!isset($pagamentos[$data])) {
            $pagamentos[$data] = [
                'data_pagamento' => $data,
                'valor'          => 0.0,
                'orig_ids'       => [],
                'observacao'     => $r['observacao'],
                'registrado_por' => $r['registrado_por'],
                'criado_em'      => $r['criado_em'],
            ];
        }
        $pagamentos[$data]['valor'] += round((float) $r['valor'], 2);
        $pagamentos[$data]['orig_ids'][] = (int) $r['id'];
    }
    $pagamentos = array_values($pagamentos); // mantém a ordem cronológica das chaves

    // 2) Percorrer os pagamentos em ordem cronológica e atribuir cada um,
    // por inteiro, ao mês mais antigo ainda em dívida.
    $novos = [];
    $total_por_mes = array_fill(1, 12, 0.0);
    $cursor = 1;

    foreach ($pagamentos as $p) {
        while ($cursor <= 12 && $total_por_mes[$cursor] >= $cota - 0.001) {
            $cursor++;
        }

        if ($cursor <= 12) {
            $ano_destino = $ano;
            $mes_destino = $cursor;
        } else {
            // Todos os meses do ano já quitados: adiantamento para Janeiro do ano seguinte.
            $ano_destino = $ano + 1;
            $mes_destino = 1;
        }
        $mes_referencia = sprintf('%04d-%02d-01', $ano_destino, $mes_destino);

        $novos[] = [
            'orig_ids'       => $p['orig_ids'],
            'mes_referencia' => $mes_referencia,
            'data_pagamento' => $p['data_pagamento'],
            'valor'          => round($p['valor'], 2),
            'observacao'     => $p['observacao'],
            'registrado_por' => $p['registrado_por'],
            'criado_em'      => $p['criado_em'],
        ];

        if ($ano_destino == $ano) {
            $total_por_mes[$mes_destino] += $p['valor'];
        }
    }

    // 3) Verificar se mudou algo: mudou se algum pagamento estava
    // fragmentado em mais de um registo, ou se o mês/valor final é
    // diferente do que já lá estava.
    $mudou = false;
    $originais_por_id = [];
    foreach ($originais as $r) {
        $originais_por_id[(int) $r['id']] = $r;
    }
    foreach ($novos as $n) {
        if (count($n['orig_ids']) > 1) {
            $mudou = true;
            break;
        }
        $orig = $originais_por_id[$n['orig_ids'][0]];
        if ($orig['mes_referencia'] !== $n['mes_referencia'] || abs((float) $orig['valor'] - $n['valor']) > 0.001) {
            $mudou = true;
            break;
        }
    }

    return [
        'originais' => $originais,
        'novos'     => $novos,
        'mudou'     => $mudou,
    ];
}

/**
 * Aplica a redistribuição calculada, substituindo os registos antigos.
 *
 * IMPORTANTE: a tabela `cotas_diarias` usa engine MyISAM, que não suporta
 * transações reais (um ROLLBACK não desfaz nada nela). Por isso, em vez de
 * apagar primeiro e inserir depois, fazemos o inverso: inserimos todos os
 * novos registos primeiro e só apagamos os antigos depois de confirmar que
 * TODOS os novos foram gravados com sucesso. Se algo falhar a meio, os
 * registos antigos permanecem intactos e os novos que já tenham entrado
 * são removidos (melhor esforço), para não haver duplicação de valores.
 */
function aplicarRedistribuicao($conn, $membro_id, $ano, $resultado) {
    $ins = $conn->prepare("
        INSERT INTO cotas_diarias (membro_id, data_pagamento, mes_referencia, valor, observacao, registrado_por, criado_em)
        VALUES (:membro_id, :data_pagamento, :mes_referencia, :valor, :observacao, :registrado_por, :criado_em)
    ");

    $ids_inseridos = [];
    try {
        foreach ($resultado['novos'] as $n) {
            $ins->execute([
                ':membro_id'      => $membro_id,
                ':data_pagamento' => $n['data_pagamento'],
                ':mes_referencia' => $n['mes_referencia'],
                ':valor'          => $n['valor'],
                ':observacao'     => $n['observacao'],
                ':registrado_por' => $n['registrado_por'],
                ':criado_em'      => $n['criado_em'],
            ]);
            $ids_inseridos[] = (int) $conn->lastInsertId();
        }
    } catch (Exception $e) {
        // Melhor esforço: remove os novos registos já inseridos antes de
        // propagar o erro, para não deixar valores duplicados.
        if (!empty($ids_inseridos)) {
            $placeholders = implode(',', array_fill(0, count($ids_inseridos), '?'));
            $conn->prepare("DELETE FROM cotas_diarias WHERE id IN ($placeholders)")->execute($ids_inseridos);
        }
        throw $e;
    }

    // Só chega aqui se TODOS os novos registos foram gravados com sucesso.
    $ids_antigos = array_map(function ($r) { return $r['id']; }, $resultado['originais']);
    if (!empty($ids_antigos)) {
        $placeholders = implode(',', array_fill(0, count($ids_antigos), '?'));
        $conn->prepare("DELETE FROM cotas_diarias WHERE id IN ($placeholders)")->execute($ids_antigos);
    }

    return true;
}

// ============================================
// PARÂMETROS
// ============================================
$ano = validarAnoCotas($_GET['ano'] ?? $_POST['ano'] ?? date('Y'));
$membro_id_filtro = intval($_GET['membro_id'] ?? $_POST['membro_id'] ?? 0);
$modo = $_GET['modo'] ?? '';
$mensagem = '';
$erro = '';

// Lista de membros para o seletor
$stmt = $conn->query("SELECT id, nome_completo, categoria FROM membros ORDER BY nome_completo");
$todos_membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Membros a analisar: um específico, ou todos os que têm cotas nesse ano
if ($membro_id_filtro > 0) {
    $membros_analisar = array_values(array_filter($todos_membros, function ($m) use ($membro_id_filtro) {
        return $m['id'] == $membro_id_filtro;
    }));
} else {
    $stmt = $conn->prepare("
        SELECT DISTINCT m.id, m.nome_completo, m.categoria
        FROM membros m
        JOIN cotas_diarias c ON c.membro_id = m.id
        WHERE YEAR(c.data_pagamento) = :ano
        ORDER BY m.nome_completo
    ");
    $stmt->execute([':ano' => $ano]);
    $membros_analisar = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// APLICAR (POST de confirmação)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'aplicar') {
    $ids_para_aplicar = isset($_POST['aplicar_membro']) && is_array($_POST['aplicar_membro'])
        ? array_map('intval', $_POST['aplicar_membro'])
        : [];

    if (empty($ids_para_aplicar)) {
        $erro = 'Nenhum membro selecionado para corrigir.';
    } else {
        $corrigidos = 0;
        try {
            foreach ($ids_para_aplicar as $mid) {
                $resultado = calcularRedistribuicao($conn, $mid, $ano);
                if ($resultado && $resultado['mudou']) {
                    aplicarRedistribuicao($conn, $mid, $ano, $resultado);
                    $corrigidos++;
                }
            }
            registrarLog(
                $_SESSION['user_id'],
                $_SESSION['user_nome'],
                'editar',
                'registry',
                "Recalculou automaticamente o mês de referência das cotas de {$corrigidos} membro(s) para o ano {$ano}"
            );
            $mensagem = "✅ Referências corrigidas com sucesso para {$corrigidos} membro(s) no ano {$ano}.";
        } catch (Exception $e) {
            $erro = 'Erro ao aplicar as correções: ' . $e->getMessage();
        }
    }
}

// ============================================
// ANALISAR (calcula a pré-visualização para todos os membros selecionados)
// ============================================
$resultados = [];
if ($modo === 'analisar' || $mensagem) {
    foreach ($membros_analisar as $m) {
        $r = calcularRedistribuicao($conn, $m['id'], $ano);
        if ($r) {
            $resultados[] = [
                'membro' => $m,
                'resultado' => $r,
            ];
        }
    }
}

$total_com_diferenca = count(array_filter($resultados, function ($r) { return $r['resultado']['mudou']; }));
?>

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
