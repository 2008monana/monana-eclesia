<?php
/** @var array $user */
/** @var array $evento */
/** @var array $tipos */
/** @var array $membros */
/** @var array $errors */

$isEdit = !empty($evento['id']);
$action = $isEdit ? url('eventos/' . $evento['id']) : url('eventos');
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc($isEdit ? url('eventos/' . $evento['id']) : url('eventos')) ?>"
           class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title"><?= $isEdit ? 'Editar Evento' : 'Novo Evento' ?></h1>
    </div>
</div>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:800px">
        <h2 style="font-size:15px;margin-bottom:18px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-calendar-plus" aria-hidden="true" style="color:var(--gold-600)"></i>
            Dados do Evento
        </h2>

        <div style="display:grid;gap:14px">
            <div>
                <label class="lb" for="titulo">Título *</label>
                <input id="titulo" name="titulo" class="input" required
                       placeholder="Ex: Culto de Oração"
                       value="<?= esc($evento['titulo'] ?? '') ?>">
                <?php if ($e = $errors['titulo'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
            </div>

            <div>
                <label class="lb" for="descricao">Descrição</label>
                <textarea id="descricao" name="descricao" class="input" rows="3"
                          placeholder="Detalhes do evento..."
                          style="height:auto;padding:10px 14px;resize:vertical"><?= esc($evento['descricao'] ?? '') ?></textarea>
            </div>

            <div class="grid" style="grid-template-columns:1fr 1fr 1fr;gap:14px">
                <div>
                    <label class="lb" for="tipo">Tipo</label>
                    <select id="tipo" name="tipo" class="select">
                        <option value="">—</option>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= esc($t) ?>" <?= ($evento['tipo'] ?? '') === $t ? 'selected' : '' ?>>
                                <?= esc($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="lb" for="data">Data *</label>
                    <input id="data" name="data" type="date" class="input" required
                           value="<?= esc($evento['data'] ?? date('Y-m-d')) ?>">
                    <?php if ($e = $errors['data'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
                </div>
                <div>
                    <label class="lb" for="hora">Hora</label>
                    <input id="hora" name="hora" type="time" class="input"
                           value="<?= esc(substr((string) ($evento['hora'] ?? '18:00'), 0, 5)) ?>">
                </div>
            </div>

            <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px">
                <div>
                    <label class="lb" for="local">Local</label>
                    <input id="local" name="local" class="input"
                           placeholder="Ex: Templo Principal"
                           value="<?= esc($evento['local'] ?? '') ?>">
                </div>
                <div>
                    <label class="lb" for="responsavel_id">Responsável</label>
                    <select id="responsavel_id" name="responsavel_id" class="select">
                        <option value="">—</option>
                        <?php foreach ($membros as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"
                                    <?= (int) ($evento['responsavel_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                                <?= esc($m['nome']) ?> (<?= esc($m['codigo']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px">
            <a href="<?= esc($isEdit ? url('eventos/' . $evento['id']) : url('eventos')) ?>"
               class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <?= $isEdit ? 'Guardar alterações' : 'Criar evento' ?>
            </button>
        </div>
    </div>
</form>