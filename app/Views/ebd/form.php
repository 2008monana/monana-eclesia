<?php
/** @var array $user */
/** @var array $classe */
/** @var array $errors */

$isEdit = !empty($classe['id']);
$action = $isEdit ? url('ebd/' . $classe['id']) : url('ebd');
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc($isEdit ? url('ebd/' . $classe['id']) : url('ebd')) ?>"
           class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title"><?= $isEdit ? 'Editar Classe EBD' : 'Nova Classe EBD' ?></h1>
    </div>
</div>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:700px">
        <h2 style="font-size:15px;margin-bottom:18px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-book-open" aria-hidden="true" style="color:var(--gold-600)"></i>
            Dados da Classe
        </h2>

        <div style="display:grid;gap:14px">
            <div>
                <label class="lb" for="nome">Nome da classe *</label>
                <input id="nome" name="nome" class="input" required
                       placeholder="Ex: Berçário, Infantil, Juvenis, Adultos..."
                       value="<?= esc($classe['nome'] ?? '') ?>">
                <?php if ($e = $errors['nome'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
            </div>

            <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px">
                <div>
                    <label class="lb" for="faixa_etaria">Faixa etária</label>
                    <input id="faixa_etaria" name="faixa_etaria" class="input"
                           placeholder="Ex: 4–6 anos"
                           value="<?= esc($classe['faixa_etaria'] ?? '') ?>">
                </div>
                <div>
                    <label class="lb" for="sala">Sala</label>
                    <input id="sala" name="sala" class="input"
                           placeholder="Ex: Sala 2"
                           value="<?= esc($classe['sala'] ?? '') ?>">
                </div>
            </div>

            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;margin-top:6px">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" class="chk"
                       <?= (int) ($classe['ativo'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span style="font-size:13.5px;color:var(--brown-800)">Classe ativa</span>
            </label>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px">
            <a href="<?= esc($isEdit ? url('ebd/' . $classe['id']) : url('ebd')) ?>"
               class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <?= $isEdit ? 'Guardar alterações' : 'Criar classe' ?>
            </button>
        </div>
    </div>
</form>