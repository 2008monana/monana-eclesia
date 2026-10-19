<?php
/** @var array $user */
/** @var array $categoria */
/** @var array $errors */

$isEdit = !empty($categoria['id']);
$action = $isEdit ? url('financeiro/categorias/' . $categoria['id']) : url('financeiro/categorias');

$icones = ['fa-tag', 'fa-coins', 'fa-hand-holding-dollar', 'fa-gift', 'fa-globe', 'fa-house', 'fa-heart', 'fa-handshake', 'fa-heart-handshake', 'fa-user-group', 'fa-users', 'fa-calendar', 'fa-bolt', 'fa-wrench', 'fa-circle-dollar-to-slot', 'fa-receipt', 'fa-chart-line', 'fa-utensils', 'fa-gas-pump', 'fa-bus'];
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('financeiro/categorias')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title"><?= $isEdit ? 'Editar Categoria' : 'Nova Categoria' ?></h1>
    </div>
</div>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:700px">
        <div style="display:grid;gap:14px">
            <div>
                <label class="lb" for="nome">Nome *</label>
                <input id="nome" name="nome" class="input" required
                       placeholder="Ex: Dízimos, Ofertas, Manutenção..."
                       value="<?= esc($categoria['nome'] ?? '') ?>">
                <?php if ($e = $errors['nome'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
            </div>

            <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px">
                <div>
                    <label class="lb" for="tipo">Tipo *</label>
                    <select id="tipo" name="tipo" class="select" required>
                        <option value="">—</option>
                        <option value="entrada" <?= ($categoria['tipo'] ?? '') === 'entrada' ? 'selected' : '' ?>>Entrada</option>
                        <option value="despesa" <?= ($categoria['tipo'] ?? '') === 'despesa' ? 'selected' : '' ?>>Despesa</option>
                    </select>
                </div>
                <div>
                    <label class="lb" for="cor">Cor</label>
                    <input id="cor" name="cor" type="color" class="input"
                           value="<?= esc($categoria['cor'] ?? '#b8862b') ?>"
                           style="padding:4px;cursor:pointer;height:40px">
                </div>
            </div>

            <div>
                <label class="lb">Ícone</label>
                <div style="display:grid;grid-template-columns:repeat(10, 1fr);gap:6px;margin-top:4px">
                    <?php foreach ($icones as $ic): ?>
                        <label style="cursor:pointer;display:grid;place-items:center;padding:8px;border:2px solid <?= ($categoria['icone'] ?? 'fa-tag') === $ic ? 'var(--gold-500)' : 'var(--line)' ?>;border-radius:6px;background:<?= ($categoria['icone'] ?? 'fa-tag') === $ic ? 'var(--gold-50)' : 'var(--ivory-bg)' ?>">
                            <input type="radio" name="icone" value="<?= esc($ic) ?>" style="display:none"
                                   <?= ($categoria['icone'] ?? 'fa-tag') === $ic ? 'checked' : '' ?>
                                   onchange="this.closest('div').querySelectorAll('label').forEach(l=>{l.style.borderColor='var(--line)';l.style.background='var(--ivory-bg)';});this.closest('label').style.borderColor='var(--gold-500)';this.closest('label').style.background='var(--gold-50)'">
                            <i class="fa-solid <?= esc($ic) ?>" aria-hidden="true" style="color:var(--brown-700)"></i>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;margin-top:6px">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" class="chk"
                       <?= (int) ($categoria['ativo'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span style="font-size:13.5px;color:var(--brown-800)">Categoria ativa</span>
            </label>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px">
            <a href="<?= esc(url('financeiro/categorias')) ?>" class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <?= $isEdit ? 'Guardar' : 'Criar categoria' ?>
            </button>
        </div>
    </div>
</form>