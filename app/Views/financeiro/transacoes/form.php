<?php
/** @var array $user */
/** @var array $transacao */
/** @var array $categorias */
/** @var array $membros */
/** @var array $metodos */
/** @var array $errors */

$isEdit = !empty($transacao['id']);
$action = $isEdit ? url('financeiro/transacoes/' . $transacao['id']) : url('financeiro/transacoes');

$metodoIcones = [
    'dinheiro'      => 'fa-money-bill',
    'transferencia' => 'fa-building-columns',
    'multicaixa'    => 'fa-credit-card',
    'deposito'      => 'fa-vault',
    'outro'         => 'fa-circle-dot',
];
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('financeiro/transacoes')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title"><?= $isEdit ? 'Editar Transação' : 'Nova Transação' ?></h1>
    </div>
</div>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:750px">
        <div style="display:grid;gap:14px">
            <!-- Tipo -->
            <div>
                <label class="lb">Tipo *</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <label style="cursor:pointer;padding:14px;border:2px solid <?= ($transacao['tipo'] ?? '') === 'entrada' ? 'var(--success)' : 'var(--line)' ?>;border-radius:8px;text-align:center;background:<?= ($transacao['tipo'] ?? '') === 'entrada' ? 'var(--success-bg)' : 'var(--ivory-bg)' ?>"
                           onclick="selectTipo('entrada')">
                        <input type="radio" name="tipo" value="entrada" style="display:none"
                               <?= ($transacao['tipo'] ?? '') === 'entrada' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-arrow-up" aria-hidden="true" style="color:var(--success);font-size:20px"></i>
                        <div style="margin-top:6px;font-weight:600;color:var(--success)">Entrada</div>
                    </label>
                    <label style="cursor:pointer;padding:14px;border:2px solid <?= ($transacao['tipo'] ?? '') === 'despesa' ? 'var(--danger)' : 'var(--line)' ?>;border-radius:8px;text-align:center;background:<?= ($transacao['tipo'] ?? '') === 'despesa' ? 'var(--danger-bg)' : 'var(--ivory-bg)' ?>"
                           onclick="selectTipo('despesa')">
                        <input type="radio" name="tipo" value="despesa" style="display:none"
                               <?= ($transacao['tipo'] ?? '') === 'despesa' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-arrow-down" aria-hidden="true" style="color:var(--danger);font-size:20px"></i>
                        <div style="margin-top:6px;font-weight:600;color:var(--danger)">Despesa</div>
                    </label>
                </div>
                <?php if ($e = $errors['tipo'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
            </div>

            <div class="grid" style="grid-template-columns:2fr 1fr;gap:14px">
                <div>
                    <label class="lb" for="descricao">Descrição *</label>
                    <input id="descricao" name="descricao" class="input" required
                           placeholder="Ex: Dízimos da semana 1"
                           value="<?= esc($transacao['descricao'] ?? '') ?>">
                    <?php if ($e = $errors['descricao'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
                </div>
                <div>
                    <label class="lb" for="valor_kz">Valor (Kz) *</label>
                    <input id="valor_kz" name="valor_kz" type="number" step="0.01" min="0.01" class="input" required
                           value="<?= esc($transacao['valor_kz'] ?? '') ?>">
                    <?php if ($e = $errors['valor_kz'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px">
                <div>
                    <label class="lb" for="data">Data *</label>
                    <input id="data" name="data" type="date" class="input" required
                           value="<?= esc($transacao['data'] ?? date('Y-m-d')) ?>">
                    <?php if ($e = $errors['data'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
                </div>
                <div>
                    <label class="lb" for="categoria_id">Categoria</label>
                    <select id="categoria_id" name="categoria_id" class="select">
                        <option value="">—</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) ($transacao['categoria_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= esc($c['nome']) ?> (<?= esc($c['tipo']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid" style="grid-template-columns:1fr 1fr 1fr;gap:14px">
                <div>
                    <label class="lb" for="metodo">Método</label>
                    <select id="metodo" name="metodo" class="select">
                        <option value="">—</option>
                        <?php foreach ($metodos as $m): ?>
                            <option value="<?= $m ?>" <?= ($transacao['metodo'] ?? '') === $m ? 'selected' : '' ?>>
                                <?= ucfirst($m) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lb" for="referencia">Referência</label>
                    <input id="referencia" name="referencia" class="input"
                           placeholder="Ex: TRX-0001"
                           value="<?= esc($transacao['referencia'] ?? '') ?>">
                </div>
                <div>
                    <label class="lb" for="member_id">Membro (opcional)</label>
                    <select id="member_id" name="member_id" class="select">
                        <option value="">—</option>
                        <?php foreach ($membros as $m): ?>
                            <option value="<?= (int) $m['id'] ?>" <?= (int) ($transacao['member_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                                <?= esc($m['nome']) ?> (<?= esc($m['codigo']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px">
            <a href="<?= esc(url('financeiro/transacoes')) ?>" class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <?= $isEdit ? 'Guardar' : 'Registar transação' ?>
            </button>
        </div>
    </div>
</form>

<script>
    function selectTipo(tipo) {
        document.querySelectorAll('input[name="tipo"]').forEach(i => {
            i.checked = i.value === tipo;
        });
        document.querySelectorAll('input[name="tipo"]').forEach(i => {
            const label = i.closest('label');
            const isSelected = i.checked;
            const isEntrada = i.value === 'entrada';
            label.style.borderColor = isSelected ? (isEntrada ? 'var(--success)' : 'var(--danger)') : 'var(--line)';
            label.style.background = isSelected ? (isEntrada ? 'var(--success-bg)' : 'var(--danger-bg)') : 'var(--ivory-bg)';
        });
    }
</script>