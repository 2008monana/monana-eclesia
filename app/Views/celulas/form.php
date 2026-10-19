<?php
/** @var array $user */
/** @var array $celula */
/** @var array $membros */
/** @var array $dias */
/** @var array $errors */

$isEdit = !empty($celula['id']);
$action = $isEdit ? url('celulas/' . $celula['id']) : url('celulas');

$diasLabel = [
    'seg' => 'Segunda-feira', 'ter' => 'Terça-feira', 'qua' => 'Quarta-feira',
    'qui' => 'Quinta-feira', 'sex' => 'Sexta-feira', 'sab' => 'Sábado', 'dom' => 'Domingo',
];
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc($isEdit ? url('celulas/' . $celula['id']) : url('celulas')) ?>"
           class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title"><?= $isEdit ? 'Editar Célula' : 'Nova Célula' ?></h1>
    </div>
</div>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>

    <div class="card" style="max-width:800px">
        <h2 style="font-size:15px;margin-bottom:18px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-people-roof" aria-hidden="true" style="color:var(--gold-600)"></i>
            Dados da Célula
        </h2>

        <div class="grid" style="grid-template-columns:2fr 1fr 1fr;gap:16px">
            <div style="grid-column:1 / -1">
                <label class="lb" for="nome">Nome *</label>
                <input id="nome" name="nome" class="input" required
                       placeholder="Ex: Célula Esperança"
                       value="<?= esc($celula['nome'] ?? '') ?>">
                <?php if ($e = $errors['nome'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
            </div>
            <div>
                <label class="lb" for="dia_semana">Dia da semana</label>
                <select id="dia_semana" name="dia_semana" class="select">
                    <option value="">—</option>
                    <?php foreach ($dias as $d): ?>
                        <option value="<?= $d ?>" <?= ($celula['dia_semana'] ?? '') === $d ? 'selected' : '' ?>>
                            <?= esc($diasLabel[$d]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lb" for="hora">Hora</label>
                <input id="hora" name="hora" type="time" class="input"
                       value="<?= esc(substr((string) ($celula['hora'] ?? ''), 0, 5)) ?>">
            </div>
            <div>
                <label class="lb" for="lider_id">Líder</label>
                <select id="lider_id" name="lider_id" class="select">
                    <option value="">—</option>
                    <?php foreach ($membros as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= (int) ($celula['lider_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                            <?= esc($m['nome']) ?> (<?= esc($m['codigo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid" style="grid-template-columns:1fr 1fr;gap:16px;margin-top:16px">
            <div>
                <label class="lb" for="anfitriao_id">Anfitrião</label>
                <select id="anfitriao_id" name="anfitriao_id" class="select">
                    <option value="">—</option>
                    <?php foreach ($membros as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= (int) ($celula['anfitriao_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                            <?= esc($m['nome']) ?> (<?= esc($m['codigo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lb" for="endereco">Endereço</label>
                <input id="endereco" name="endereco" class="input"
                       placeholder="Bairro, Rua, Nº"
                       value="<?= esc($celula['endereco'] ?? '') ?>">
            </div>
        </div>

        <div style="margin-top:18px">
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" class="chk"
                       <?= (int) ($celula['ativo'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span style="font-size:13.5px;color:var(--brown-800)">Célula ativa</span>
            </label>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px">
            <a href="<?= esc($isEdit ? url('celulas/' . $celula['id']) : url('celulas')) ?>"
               class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <?= $isEdit ? 'Guardar alterações' : 'Criar célula' ?>
            </button>
        </div>
    </div>
</form>