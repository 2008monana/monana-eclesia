<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>
    
    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-plus-circle"></i> Novo Registo de Cota</h2>
            <a href="<?= url('modules/registry/index.php') ?>" style="display:inline-flex;align-items:center;gap:6px;color:var(--gray-500);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <?php if ($erro): ?>
        <div style="background:#f9e9e5;border:1px solid #f5c6c6;color:#721c24;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px;">
            <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
        </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
        <div style="background:#eaf3ec;border:1px solid #a5d6a7;color:#2e7d32;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px;">
            <i class="fas fa-check-circle"></i> <?= $sucesso ?>
        </div>
        <?php endif; ?>

        <div class="panel" style="max-width:700px;margin:0 auto;">
            <div style="background:#fcf8ec;border:1px solid #eddba0;color:var(--blue-800);padding:10px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:12.5px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-info-circle"></i>
                O <strong>mês de referência</strong> é atribuído automaticamente ao mês mais antigo em dívida do membro - não precisas de o escolher. Se o valor pago cobrir mais de um mês (ex: 2000 Kz para uma cota de 1000 Kz), o pagamento fica registado inteiro nesse mês mais antigo e os meses seguintes aparecem como quitados por adiantamento nas listas - sem dividir o registo em vários.
            </div>

            <form method="POST" action="">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                    <!-- Pesquisa de membro (autocomplete) -->
                    <div style="grid-column:1/3;position:relative;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Membro <span style="color:var(--danger);">*</span>
                        </label>
                        <div style="display:grid;grid-template-columns:2fr 1fr;gap:8px;">
                            <input type="text" id="busca_membro" autocomplete="off"
                                   placeholder="Clique e digite para pesquisar o nome..."
                                   oninput="onBuscaMembroInput()"
                                   onfocus="mostrarListaMembros()"
                                   style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                            <select id="filtro_categoria" onchange="filtrarMembros(); mostrarListaMembros();"
                                    style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                                <option value="">Todas as categorias</option>
                                <option value="mama">Mamã</option>
                                <option value="papa">Papá</option>
                                <option value="jovem">Jovem</option>
                                <option value="crianca">Criança</option>
                            </select>
                        </div>

                        <!-- Campo real enviado no formulário -->
                        <input type="hidden" name="membro_id" id="membro_id" required>

                        <!-- Lista de sugestões: só aparece enquanto o campo de pesquisa está ativo -->
                        <div id="lista_membros" style="display:none;position:absolute;z-index:20;top:100%;left:0;right:0;margin-top:4px;max-height:220px;overflow-y:auto;background:#fff;border:1.5px solid var(--gray-200);border-radius:var(--radius);box-shadow:0 8px 24px rgba(0,0,0,.12);">
                            <?php foreach ($membros as $m): ?>
                            <div class="opcao-membro"
                                 data-id="<?= $m['id'] ?>"
                                 data-nome="<?= htmlspecialchars(mb_strtolower($m['nome_completo'])) ?>"
                                 data-categoria="<?= $m['categoria'] ?>"
                                 data-nome-original="<?= htmlspecialchars($m['nome_completo']) ?>"
                                 onclick="selecionarMembro(this)"
                                 style="padding:10px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--gray-100);">
                                <?= htmlspecialchars($m['nome_completo']) ?>
                                <span style="color:var(--gray-400);font-size:12px;"> (<?= $categoria_labels_form[$m['categoria']] ?? ucfirst($m['categoria']) ?>)</span>
                            </div>
                            <?php endforeach; ?>
                            <div id="nenhum_membro" style="display:none;padding:10px 14px;font-size:13px;color:var(--gray-400);">
                                Nenhum membro encontrado.
                            </div>
                        </div>
                    </div>

                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Data do Pagamento <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Valor (Kz) <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="number" name="valor" step="0.01" min="0.01" required
                               placeholder="1000.00"
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Observação (opcional)
                        </label>
                        <textarea name="observacao" rows="2"
                                  style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;resize:vertical;"
                                  placeholder="Informações adicionais sobre o pagamento..."></textarea>
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary" style="padding:12px 30px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                    <a href="<?= url('modules/registry/index.php') ?>" style="padding:12px 24px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:14px;transition:.2s;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>

<script>
function mostrarListaMembros() {
    document.getElementById('lista_membros').style.display = 'block';
    filtrarMembros();
}

function esconderListaMembros() {
    document.getElementById('lista_membros').style.display = 'none';
}

function onBuscaMembroInput() {
    mostrarListaMembros();
    // Se o utilizador voltar a escrever depois de já ter escolhido um membro,
    // a seleção anterior deixa de ser válida até escolher novamente da lista.
    document.getElementById('membro_id').value = '';
}

function filtrarMembros() {
    const busca = document.getElementById('busca_membro').value.trim().toLowerCase();
    const categoria = document.getElementById('filtro_categoria').value;
    const opcoes = document.querySelectorAll('#lista_membros .opcao-membro');

    let algumaVisivel = false;
    opcoes.forEach(function (opt) {
        const nomeOk = !busca || opt.dataset.nome.includes(busca);
        const catOk = !categoria || opt.dataset.categoria === categoria;
        const visivel = nomeOk && catOk;
        opt.style.display = visivel ? '' : 'none';
        if (visivel) algumaVisivel = true;
    });

    document.getElementById('nenhum_membro').style.display = algumaVisivel ? 'none' : 'block';
}

function selecionarMembro(el) {
    document.getElementById('membro_id').value = el.dataset.id;
    document.getElementById('busca_membro').value = el.dataset.nomeOriginal;
    esconderListaMembros();
}

// Fecha a lista ao clicar fora do campo de pesquisa / lista de sugestões
document.addEventListener('click', function (event) {
    const dentroDoCampo = event.target.closest('#busca_membro, #filtro_categoria, #lista_membros');
    if (!dentroDoCampo) {
        esconderListaMembros();
    }
});

// Impede que submeter o formulário sem escolher um membro da lista
document.querySelector('form').addEventListener('submit', function (e) {
    if (!document.getElementById('membro_id').value) {
        e.preventDefault();
        alert('Selecione um membro da lista antes de guardar.');
        document.getElementById('busca_membro').focus();
    }
});
</script>
