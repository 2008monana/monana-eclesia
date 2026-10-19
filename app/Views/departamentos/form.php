<?php
/** @var array $user */
/** @var array $departamento */
/** @var array $membros */
/** @var array $errors */

$isEdit = !empty($departamento['id']);
$action = $isEdit ? url('departamentos/' . $departamento['id']) : url('departamentos');
$cor    = $departamento['cor'] ?? '#b8862b';
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc($isEdit ? url('departamentos/' . $departamento['id']) : url('departamentos')) ?>"
           class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title"><?= $isEdit ? 'Editar Departamento' : 'Novo Departamento' ?></h1>
    </div>
</div>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>

    <div class="card" style="max-width:800px">
        <h2 style="font-size:15px;margin-bottom:18px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-network-wired" aria-hidden="true" style="color:var(--gold-600)"></i>
            Dados do Departamento
        </h2>

        <div class="grid" style="grid-template-columns:2fr 1fr;gap:16px">
            <div>
                <label class="lb" for="nome">Nome *</label>
                <input id="nome" name="nome" class="input" required
                       placeholder="Ex: Mulheres, Jovens, Evangelismo..."
                       value="<?= esc($departamento['nome'] ?? '') ?>">
                <?php if ($e = $errors['nome'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
            </div>
            <div>
                <label class="lb" for="cor">Cor</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input id="cor" name="cor" type="color" class="input"
                           style="width:60px;height:40px;padding:4px;cursor:pointer"
                           value="<?= esc($cor) ?>">
                    <input id="cor_text" class="input" disabled
                           value="<?= esc($cor) ?>" style="flex:1;background:var(--ivory-bg)">
                </div>
            </div>
        </div>

        <div style="margin-top:16px">
            <label class="lb" for="descricao">Descrição</label>
            <input id="descricao" name="descricao" class="input" maxlength="255"
                   value="<?= esc($departamento['descricao'] ?? '') ?>">
        </div>

        <h2 style="font-size:15px;margin:24px 0 18px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-users" aria-hidden="true" style="color:var(--gold-600)"></i>
            Liderança
        </h2>

        <div class="grid" style="grid-template-columns:1fr 1fr;gap:16px">
            <div>
                <label class="lb" for="lider_id">Líder</label>
                <select id="lider_id" name="lider_id" class="select">
                    <option value="">—</option>
                    <?php foreach ($membros as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= (int) ($departamento['lider_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                            <?= esc($m['nome']) ?> (<?= esc($m['codigo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lb" for="vice_id">Vice-líder</label>
                <select id="vice_id" name="vice_id" class="select">
                    <option value="">—</option>
                    <?php foreach ($membros as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= (int) ($departamento['vice_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                            <?= esc($m['nome']) ?> (<?= esc($m['codigo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="margin-top:18px">
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" class="chk"
                       <?= (int) ($departamento['ativo'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span style="font-size:13.5px;color:var(--brown-800)">Departamento ativo</span>
            </label>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px">
            <a href="<?= esc($isEdit ? url('departamentos/' . $departamento['id']) : url('departamentos')) ?>"
               class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <?= $isEdit ? 'Guardar alterações' : 'Criar departamento' ?>
            </button>
        </div>
    </div>
</form>

<script>
    const cor   = document.getElementById('cor');
    const corTx = document.getElementById('cor_text');
    cor?.addEventListener('input', () => { corTx.value = cor.value; });
</script>