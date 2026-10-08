<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-gift"></i> Contribuição de Fim de Ano</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button onclick="gerarPDF()" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-file-pdf"></i> Gerar PDF
                </button>
            </div>
        </div>

        <!-- FILTRO -->
        <div class="panel" style="margin-bottom:20px;">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Ano</label>
                    <?php $anos_disponiveis = getAnosDisponiveis(); ?>
                    <?php if (count($anos_disponiveis) > 1): ?>
                    <select name="ano" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <?php foreach ($anos_disponiveis as $i): ?>
                        <option value="<?= $i ?>" <?= $ano == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="hidden" name="ano" value="<?= $ano ?>">
                    <div style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-size:13px;color:var(--gray-600);background:var(--gray-100);"><?= $ano ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Categoria</label>
                    <select name="categoria" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="todos" <?= $categoria == 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="mama" <?= $categoria == 'mama' ? 'selected' : '' ?>>Mamãs</option>
                        <option value="papa" <?= $categoria == 'papa' ? 'selected' : '' ?>>Papás</option>
                        <option value="jovem" <?= $categoria == 'jovem' ? 'selected' : '' ?>>Jovens</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-primary" style="padding:8px 20px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
                <div>
                    <a href="<?= url('modules/year-end/index.php') ?>" style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;display:inline-block;">
                        <i class="fas fa-undo"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- RESUMO (CARDS) -->
        <div class="grid-stats" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-users"></i></div>
                <div class="stat-num"><?= $total_membros ?></div>
                <div class="stat-label">Total de Membros</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-check-circle"></i></div>
                <div class="stat-num"><?= $total_quitados ?></div>
                <div class="stat-label">Já Quitaram (5.000 Kz)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fdeee3;color:#b9770e;"><i class="fas fa-coins"></i></div>
                <div class="stat-num"><?= $total_com_pagamento ?></div>
                <div class="stat-label">Membros com Pagamentos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-coins"></i></div>
                <div class="stat-num"><?= number_format($total_arrecadado, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Total Arrecadado</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f9e9e5;color:#b5412f;"><i class="fas fa-arrow-circle-down"></i></div>
                <div class="stat-num"><?= number_format($total_saidas_contribuicoes, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Saídas (Contribuições)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#ede7f6;color:#7a5ca8;"><i class="fas fa-piggy-bank"></i></div>
                <div class="stat-num"><?= number_format($saldo_disponivel_contribuicoes, 2, ',', '.') ?> Kz</div>
                <div class="stat-label">Saldo Disponível</div>
            </div>
        </div>

        <!-- RESUMO POR CATEGORIA -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px;">
            <?php
            $cats = [
                'mama' => ['label' => 'Mamãs', 'color' => '#b5527a', 'icon' => 'fa-female'],
                'papa' => ['label' => 'Papás', 'color' => '#3f7d4e', 'icon' => 'fa-male'],
                'jovem' => ['label' => 'Jovens', 'color' => '#7a5ca8', 'icon' => 'fa-user-graduate']
            ];
            foreach ($cats as $key => $cat):
                $data = isset($resumo_categorias[$key]) ? $resumo_categorias[$key] : ['total_membros' => 0, 'total_quitados' => 0, 'total_arrecadado' => 0];
            ?>
            <div style="background:var(--white);border-radius:var(--radius);padding:16px;box-shadow:var(--shadow);border:1px solid var(--gray-100);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:50%;background:<?= $cat['color'] ?>20;color:<?= $cat['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:16px;">
                        <i class="fas <?= $cat['icon'] ?>"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;color:var(--gray-700);"><?= $cat['label'] ?></div>
                        <div style="font-size:12px;color:var(--gray-500);">
                            <?= ($data['total_quitados'] ?? 0) ?>/<?= ($data['total_membros'] ?? 0) ?> quitados · <?= number_format($data['total_arrecadado'] ?? 0, 0, ',', '.') ?> Kz
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- TABELA PRINCIPAL -->
        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 style="font-size:14px;font-weight:700;color:var(--blue-800);">
                    <i class="fas fa-list"></i> Lista de Contribuintes - <?= $ano ?>
                    <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(<?= $total_membros ?> membros)</span>
                </h3>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="text" id="busca_nome_fimano" placeholder="Pesquisar nome..." oninput="filtrarTabelaFimAno()"
                           style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                    <select id="filtro_status_fimano" onchange="filtrarTabelaFimAno()"
                            style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="">Todos</option>
                        <option value="quitado">Quitado</option>
                        <option value="parcial">Parcial</option>
                        <option value="pendente">Pendente</option>
                    </select>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-100);">
                            <th style="padding:10px 12px;text-align:left;">#</th>
                            <th style="padding:10px 12px;text-align:left;">Nome</th>
                            <?php if ($categoria == 'todos'): ?>
                            <th style="padding:10px 12px;text-align:left;">Categoria</th>
                            <?php endif; ?>
                            <th style="padding:10px 12px;text-align:left;">Telefone</th>
                            <th style="padding:10px 12px;text-align:center;">Total Pago (Kz)</th>
                            <th style="padding:10px 12px;text-align:center;">Saldo Restante (Kz)</th>
                            <th style="padding:10px 12px;text-align:center;">Status</th>
                            <th style="padding:10px 12px;text-align:center;">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="tabela_fimano">
                        <tr id="linha_nenhum_fimano" style="<?= empty($membros) ? '' : 'display:none;' ?>">
                            <td colspan="<?= $categoria == 'todos' ? 8 : 7 ?>" style="padding:40px;text-align:center;color:var(--gray-400);">
                                <i class="fas fa-users" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.3;"></i>
                                Nenhum membro encontrado.
                            </td>
                        </tr>
                        <?php if (!empty($membros)): ?>
                        <?php foreach ($membros as $index => $membro):
                            $cat = isset($categoria_cores[$membro['categoria']]) ? $categoria_cores[$membro['categoria']] : ['label' => $membro['categoria'], 'color' => '#gray-500'];
                            $saldo_restante = $membro['saldo_restante'];
                            $esta_quitado = $membro['esta_quitado'];
                            $status_class = $esta_quitado ? '#eaf3ec' : ($membro['total_pago'] > 0 ? '#fdf1d9' : '#f9e9e5');
                            $status_color = $esta_quitado ? '#3f7d4e' : ($membro['total_pago'] > 0 ? '#b9770e' : '#b5412f');
                            $status_text = $esta_quitado ? '✅ Quitado' : ($membro['total_pago'] > 0 ? '⏳ Parcial' : '❌ Pendente');
                            $status_key = $esta_quitado ? 'quitado' : ($membro['total_pago'] > 0 ? 'parcial' : 'pendente');
                        ?>
                        <tr class="linha-membro-fimano"
                            data-nome="<?= htmlspecialchars(mb_strtolower($membro['nome_completo'])) ?>"
                            data-status="<?= $status_key ?>"
                            style="border-bottom:1px solid var(--gray-100);">
                            <td style="padding:10px 12px;color:var(--gray-500);"><?= $index + 1 ?></td>
                            <td style="padding:10px 12px;font-weight:600;color:var(--gray-800);">
                                <?= htmlspecialchars($membro['nome_completo']) ?>
                            </td>
                            <?php if ($categoria == 'todos'): ?>
                            <td style="padding:10px 12px;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= $cat['color'] ?>;color:#fff;font-size:11px;font-weight:600;">
                                    <?= $cat['label'] ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            <td style="padding:10px 12px;color:var(--gray-600);font-size:12px;">
                                <?= htmlspecialchars($membro['telefone'] ?? '-') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;font-weight:700;color:var(--blue-800);">
                                <?= number_format($membro['total_pago'], 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;font-weight:700;color:<?= $saldo_restante > 0 ? '#b5412f' : '#3f7d4e' ?>;">
                                <?= number_format($saldo_restante, 2, ',', '.') ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= $status_class ?>;color:<?= $status_color ?>;font-size:11px;font-weight:600;">
                                    <?= $status_text ?>
                                </span>
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                <?php if ($esta_quitado): ?>
                                <span style="color:var(--gray-400);font-size:12px;font-style:italic;">—</span>
                                <?php else: ?>
                                <button onclick="abrirModal(<?= $membro['id'] ?>, '<?= htmlspecialchars($membro['nome_completo']) ?>', <?= $ano ?>)" 
                                        style="padding:6px 10px;border-radius:var(--radius);border:none;background:#eaf3ec;color:#3f7d4e;cursor:pointer;font-size:12px;font-family:'Inter',sans-serif;transition:.2s;display:inline-flex;align-items:center;gap:4px;">
                                    <i class="fas fa-plus"></i> Registrar Pagamento
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- LEGENDA -->
            <div style="margin-top:16px;padding:12px;background:var(--gray-50);border-radius:var(--radius);font-size:12px;color:var(--gray-500);display:flex;flex-wrap:wrap;gap:16px;">
                <span><span style="display:inline-block;width:12px;height:12px;background:#eaf3ec;border-radius:2px;margin-right:4px;"></span> Quitado (≥ 5.000 Kz)</span>
                <span><span style="display:inline-block;width:12px;height:12px;background:#fdf1d9;border-radius:2px;margin-right:4px;"></span> Parcial (tem pagamentos)</span>
                <span><span style="display:inline-block;width:12px;height:12px;background:#f9e9e5;border-radius:2px;margin-right:4px;"></span> Pendente (sem pagamentos)</span>
            </div>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>

<!-- ==========================================
MODAL PARA REGISTRAR PAGAMENTO
========================================== -->
<div id="modalPagamento" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--white);border-radius:var(--radius-lg);padding:32px;max-width:450px;width:90%;box-shadow:var(--shadow-xl);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="font-size:18px;font-weight:700;color:var(--blue-800);">Registrar Pagamento</h3>
            <button onclick="fecharModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--gray-400);">&times;</button>
        </div>
        <p style="margin-bottom:16px;color:var(--gray-600);">
            <strong id="modalNome"></strong>
        </p>
        <p style="margin-bottom:16px;font-size:13px;color:var(--gray-500);">
            Saldo restante: <strong id="modalSaldo" style="color:var(--blue-600);"></strong>
        </p>
        <form id="formPagamento" method="POST">
            <input type="hidden" name="membro_id" id="modalMembroId">
            <input type="hidden" name="ano" id="modalAno">
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                    Valor (Kz) <span style="color:var(--danger);">*</span>
                </label>
                <input type="number" name="valor" id="modalValor" step="0.01" min="0.01" required
                       placeholder="Digite o valor pago"
                       style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;">
            </div>
            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn-primary" style="flex:1;padding:12px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;">
                    <i class="fas fa-save"></i> Registrar
                </button>
                <button type="button" onclick="fecharModal()" style="padding:12px 24px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);font-weight:600;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;background:#fff;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal(membroId, nome, ano) {
    document.getElementById('modalMembroId').value = membroId;
    document.getElementById('modalNome').textContent = 'Membro: ' + nome;
    document.getElementById('modalAno').value = ano;
    
    // Buscar saldo restante
    fetch('<?= url('modules/year-end/get-saldo.php') ?>?membro_id=' + membroId + '&ano=' + ano)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalSaldo').textContent = data.saldo_restante + ' Kz';
        });
    
    document.getElementById('modalPagamento').style.display = 'flex';
    document.getElementById('modalValor').focus();
}

function fecharModal() {
    document.getElementById('modalPagamento').style.display = 'none';
    document.getElementById('modalValor').value = '';
}

// Enviar formulário via AJAX
document.getElementById('formPagamento').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('<?= url('modules/year-end/register.php') ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Pagamento registrado com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro na requisição: ' + error);
    });
});

function gerarPDF() {
    const params = new URLSearchParams(window.location.search);
    window.open('<?= url('modules/year-end/generate-pdf.php') ?>?' + params.toString(), '_blank');
}

// Pesquisa por nome e filtro de status (quitado/parcial/pendente) em tempo
// real, sem recarregar a página.
function filtrarTabelaFimAno() {
    const busca = document.getElementById('busca_nome_fimano').value.trim().toLowerCase();
    const status = document.getElementById('filtro_status_fimano').value;

    let algumaVisivel = false;

    document.querySelectorAll('#tabela_fimano tr.linha-membro-fimano').forEach(function (tr) {
        const nomeOk = !busca || tr.dataset.nome.includes(busca);
        const statusOk = !status || tr.dataset.status === status;
        const visivel = nomeOk && statusOk;
        tr.style.display = visivel ? '' : 'none';
        if (visivel) algumaVisivel = true;
    });

    const linhaVazia = document.getElementById('linha_nenhum_fimano');
    if (linhaVazia) {
        linhaVazia.style.display = algumaVisivel ? 'none' : '';
    }
}
</script>
