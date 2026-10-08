<?php
// includes/modal-historico-pagamentos.php
//
// Card/modal reutilizável que mostra o histórico completo de pagamentos
// (cotas diárias, com dia e mês de referência) de um membro. Incluído nas
// páginas: modules/members/index.php, modules/lists/mamas.php,
// modules/lists/papas.php e modules/lists/jovens.php.
//
// Para usar: chamar abrirHistoricoPagamentos(membroId, nomeDoMembro) no
// onclick do nome do membro na lista.
?>
<!-- ==========================================
CARD / MODAL: HISTÓRICO DE PAGAMENTOS DO MEMBRO
========================================== -->
<div id="modalHistorico" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:16px;">
    <div style="background:var(--white);border-radius:var(--radius-lg);padding:26px;max-width:820px;width:100%;max-height:88vh;overflow-y:auto;box-shadow:var(--shadow-xl);">

        <!-- Cabeçalho -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px;gap:12px;">
            <div>
                <h3 style="font-size:18px;font-weight:700;color:var(--blue-800);">
                    <i class="fas fa-history"></i> Histórico de Pagamentos
                </h3>
                <div id="histNomeMembro" style="font-size:14px;color:var(--gray-600);font-weight:600;margin-top:2px;"></div>
            </div>
            <button onclick="fecharHistoricoPagamentos()" style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--gray-400);line-height:1;">&times;</button>
        </div>

        <!-- Estado de carregamento / erro -->
        <div id="histCarregando" style="text-align:center;padding:40px 0;color:var(--gray-400);">
            <i class="fas fa-spinner fa-spin" style="font-size:24px;display:block;margin-bottom:10px;"></i>
            A carregar histórico...
        </div>
        <div id="histErro" style="display:none;text-align:center;padding:40px 0;color:#b5412f;">
            <i class="fas fa-exclamation-triangle" style="font-size:24px;display:block;margin-bottom:10px;"></i>
            <span id="histErroMsg">Não foi possível carregar o histórico.</span>
        </div>

        <!-- Conteúdo (só aparece depois de carregar) -->
        <div id="histConteudo" style="display:none;">

            <!-- Resumo -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:16px;">
                <div style="background:var(--gray-50);padding:12px 14px;border-radius:var(--radius);">
                    <div style="font-size:10.5px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Total em Cotas</div>
                    <div id="histTotalCotas" style="font-size:16px;font-weight:700;color:var(--blue-800);margin-top:2px;">0,00 Kz</div>
                </div>
                <div style="background:var(--gray-50);padding:12px 14px;border-radius:var(--radius);">
                    <div style="font-size:10.5px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Nº de Pagamentos</div>
                    <div id="histQtdPagamentos" style="font-size:16px;font-weight:700;color:var(--gray-800);margin-top:2px;">0</div>
                </div>
                <div id="histBoxContribuicao" style="background:#fdf1d9;padding:12px 14px;border-radius:var(--radius);">
                    <div style="font-size:10.5px;color:#8a6317;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Contrib. Fim de Ano</div>
                    <div id="histTotalContrib" style="font-size:16px;font-weight:700;color:#8a6317;margin-top:2px;">0,00 Kz</div>
                </div>
                <div style="background:#eaf3ec;padding:12px 14px;border-radius:var(--radius);">
                    <div style="font-size:10.5px;color:#3f7d4e;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Total Geral</div>
                    <div id="histTotalGeral" style="font-size:16px;font-weight:700;color:#3f7d4e;margin-top:2px;">0,00 Kz</div>
                </div>
            </div>

            <!-- Meses pagos / em dívida -->
            <div id="histMesesStatusWrap" style="margin-bottom:16px;"></div>

            <!-- Filtros -->
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:14px;padding:12px;background:var(--gray-50);border-radius:var(--radius);">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Ano do Pagamento</label>
                    <select id="histFiltroAno" onchange="aplicarFiltrosHistorico()" style="padding:8px 12px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Mês de Referência</label>
                    <select id="histFiltroMes" onchange="aplicarFiltrosHistorico()" style="padding:8px 12px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="">Todos</option>
                        <option value="1">Janeiro</option>
                        <option value="2">Fevereiro</option>
                        <option value="3">Março</option>
                        <option value="4">Abril</option>
                        <option value="5">Maio</option>
                        <option value="6">Junho</option>
                        <option value="7">Julho</option>
                        <option value="8">Agosto</option>
                        <option value="9">Setembro</option>
                        <option value="10">Outubro</option>
                        <option value="11">Novembro</option>
                        <option value="12">Dezembro</option>
                    </select>
                </div>
                <button type="button" onclick="limparFiltrosHistorico()" style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);font-weight:600;font-size:12.5px;cursor:pointer;font-family:'Inter',sans-serif;background:#fff;">
                    <i class="fas fa-undo"></i> Limpar Filtros
                </button>
                <span id="histFiltroContagem" style="font-size:12px;color:var(--gray-400);margin-left:auto;align-self:center;"></span>
            </div>

            <!-- Tabela de cotas diárias -->
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-100);">
                            <th style="padding:8px 10px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Data do Pagamento</th>
                            <th style="padding:8px 10px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Mês de Referência</th>
                            <th style="padding:8px 10px;text-align:right;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Valor (Kz)</th>
                        </tr>
                    </thead>
                    <tbody id="histTabelaCorpo"></tbody>
                </table>
                <div id="histSemResultados" style="display:none;text-align:center;padding:30px;color:var(--gray-400);">
                    <i class="fas fa-inbox" style="font-size:26px;display:block;margin-bottom:8px;opacity:.4;"></i>
                    Nenhum pagamento encontrado para este filtro.
                </div>
            </div>

            <!-- Contribuição de fim de ano (lista separada, se houver) -->
            <div id="histContribuicaoWrap" style="display:none;margin-top:20px;">
                <h4 style="font-size:13px;font-weight:700;color:var(--blue-800);margin-bottom:8px;">
                    <i class="fas fa-gift"></i> Contribuição de Fim de Ano
                </h4>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:var(--gray-100);">
                                <th style="padding:8px 10px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Ano</th>
                                <th style="padding:8px 10px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Data do Pagamento</th>
                                <th style="padding:8px 10px;text-align:right;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Valor (Kz)</th>
                            </tr>
                        </thead>
                        <tbody id="histTabelaContribCorpo"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:20px;">
            <button type="button" onclick="fecharHistoricoPagamentos()" style="padding:10px 22px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);font-weight:600;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;background:#fff;">
                Fechar
            </button>
        </div>
    </div>
</div>

<script>
let _histDadosAtuais = null;

function abrirHistoricoPagamentos(membroId, nomeMembro) {
    document.getElementById('modalHistorico').style.display = 'flex';
    document.getElementById('histNomeMembro').textContent = nomeMembro;
    document.getElementById('histCarregando').style.display = 'block';
    document.getElementById('histErro').style.display = 'none';
    document.getElementById('histConteudo').style.display = 'none';

    fetch('<?= url('modules/members/historico-pagamentos.php') ?>?membro_id=' + encodeURIComponent(membroId))
        .then(response => response.json())
        .then(data => {
            document.getElementById('histCarregando').style.display = 'none';
            if (!data.success) {
                document.getElementById('histErroMsg').textContent = data.message || 'Não foi possível carregar o histórico.';
                document.getElementById('histErro').style.display = 'block';
                return;
            }
            _histDadosAtuais = data;
            montarHistoricoPagamentos(data);
            document.getElementById('histConteudo').style.display = 'block';
        })
        .catch(() => {
            document.getElementById('histCarregando').style.display = 'none';
            document.getElementById('histErroMsg').textContent = 'Erro de ligação ao carregar o histórico.';
            document.getElementById('histErro').style.display = 'block';
        });
}

function fecharHistoricoPagamentos() {
    document.getElementById('modalHistorico').style.display = 'none';
    _histDadosAtuais = null;
}

function montarHistoricoPagamentos(data) {
    // Resumo
    document.getElementById('histTotalCotas').textContent = data.totais.total_cotas_fmt + ' Kz';
    document.getElementById('histQtdPagamentos').textContent = data.totais.quantidade_cotas;
    document.getElementById('histTotalContrib').textContent = data.totais.total_contribuicoes_fmt + ' Kz';
    document.getElementById('histTotalGeral').textContent = data.totais.total_geral_fmt + ' Kz';
    document.getElementById('histBoxContribuicao').style.display = data.contribuicoes.length ? 'block' : 'none';

    // Popula filtro de anos com base nos dados devolvidos
    const selectAno = document.getElementById('histFiltroAno');
    selectAno.innerHTML = '<option value="">Todos</option>';
    (data.anos_disponiveis || []).forEach(ano => {
        const opt = document.createElement('option');
        opt.value = ano;
        opt.textContent = ano;
        selectAno.appendChild(opt);
    });
    document.getElementById('histFiltroMes').value = '';

    // Contribuição de fim de ano
    const corpoContrib = document.getElementById('histTabelaContribCorpo');
    corpoContrib.innerHTML = '';
    data.contribuicoes.forEach(c => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--gray-100)';
        tr.innerHTML = `
            <td style="padding:8px 10px;font-weight:600;">${c.ano}</td>
            <td style="padding:8px 10px;color:var(--gray-600);">${c.data_pagamento_fmt}</td>
            <td style="padding:8px 10px;text-align:right;font-weight:700;color:#8a6317;">${c.valor_fmt}</td>
        `;
        corpoContrib.appendChild(tr);
    });
    document.getElementById('histContribuicaoWrap').style.display = data.contribuicoes.length ? 'block' : 'none';

    renderizarMesesStatus(data.meses_status || []);
    renderizarTabelaHistorico(data.pagamentos);
}

/**
 * Mostra, por ano, um "selo" para cada mês já contabilizado dizendo se
 * está Pago (mes_referencia atingiu 1.000 Kz) ou Em Dívida - com base no
 * mesmo cálculo usado nas Listas, não no mês do calendário do pagamento.
 */
function renderizarMesesStatus(mesesStatus) {
    const wrap = document.getElementById('histMesesStatusWrap');
    wrap.innerHTML = '';

    if (!mesesStatus.length) return;

    mesesStatus.forEach(bloco => {
        const secao = document.createElement('div');
        secao.style.marginBottom = '10px';

        const cabecalho = document.createElement('div');
        cabecalho.style.cssText = 'font-size:11.5px;font-weight:700;color:var(--gray-500);margin-bottom:6px;text-transform:uppercase;letter-spacing:.03em;';
        cabecalho.textContent = bloco.ano + ' — ' + bloco.qtd_pagos + ' mês(es) pago(s), ' + bloco.qtd_em_divida + ' em dívida';
        secao.appendChild(cabecalho);

        const grid = document.createElement('div');
        grid.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;';

        bloco.meses.forEach(m => {
            const selo = document.createElement('span');
            if (m.quitado) {
                selo.style.cssText = 'padding:5px 10px;border-radius:20px;font-size:11.5px;font-weight:700;background:#eaf3ec;color:#3f7d4e;';
                selo.innerHTML = '<i class="fas fa-check-circle"></i> ' + m.mes_nome;
            } else {
                selo.style.cssText = 'padding:5px 10px;border-radius:20px;font-size:11.5px;font-weight:700;background:#fdecec;color:#b5412f;';
                selo.title = 'Faltam ' + m.falta.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' Kz';
                selo.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + m.mes_nome
                    + (m.total > 0 ? ' (' + m.total_fmt + ' Kz)' : '');
            }
            grid.appendChild(selo);
        });

        secao.appendChild(grid);
        wrap.appendChild(secao);
    });
}

function aplicarFiltrosHistorico() {
    if (!_histDadosAtuais) return;
    const ano = document.getElementById('histFiltroAno').value;
    const mes = document.getElementById('histFiltroMes').value;

    const filtrados = _histDadosAtuais.pagamentos.filter(p => {
        if (ano && String(p.ano_pagamento) !== String(ano)) return false;
        if (mes && String(p.mes_referencia_mes) !== String(mes)) return false;
        return true;
    });

    renderizarTabelaHistorico(filtrados);
}

function limparFiltrosHistorico() {
    document.getElementById('histFiltroAno').value = '';
    document.getElementById('histFiltroMes').value = '';
    aplicarFiltrosHistorico();
}

function renderizarTabelaHistorico(pagamentos) {
    const corpo = document.getElementById('histTabelaCorpo');
    corpo.innerHTML = '';

    const semResultados = document.getElementById('histSemResultados');
    const contagem = document.getElementById('histFiltroContagem');

    if (!pagamentos.length) {
        semResultados.style.display = 'block';
        contagem.textContent = '0 pagamentos';
        return;
    }
    semResultados.style.display = 'none';

    let totalFiltrado = 0;
    pagamentos.forEach(p => {
        totalFiltrado += p.valor;
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--gray-100)';
        tr.innerHTML = `
            <td style="padding:8px 10px;font-weight:600;color:var(--gray-800);">${p.data_pagamento_fmt}</td>
            <td style="padding:8px 10px;color:var(--gray-600);">${p.mes_referencia_fmt}</td>
            <td style="padding:8px 10px;text-align:right;font-weight:700;color:#3f7d4e;">${p.valor_fmt}</td>
        `;
        corpo.appendChild(tr);
    });

    const totalFmt = totalFiltrado.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    contagem.textContent = pagamentos.length + ' pagamento(s) · ' + totalFmt + ' Kz';
}

// Fecha o modal clicando fora da caixa
document.addEventListener('click', function (e) {
    const modal = document.getElementById('modalHistorico');
    if (e.target === modal) {
        fecharHistoricoPagamentos();
    }
});
</script>
