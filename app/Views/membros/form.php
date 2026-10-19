<?php
/** @var array $user */
/** @var array $membro */
/** @var array $departamentos */
/** @var array $errors */

$isEdit = !empty($membro['id']);
$action = $isEdit ? url('membros/' . $membro['id']) : url('membros');
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc($isEdit ? url('membros/' . $membro['id']) : url('membros')) ?>"
           class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title"><?= $isEdit ? 'Editar Membro' : 'Novo Membro' ?></h1>
    </div>
</div>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>

    <div class="card" style="max-width:900px">
        <h2 style="font-size:15px;margin-bottom:18px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-user" aria-hidden="true" style="color:var(--gold-600)"></i>
            Dados Pessoais
        </h2>

        <div class="grid" style="grid-template-columns:1fr 1fr;gap:16px">
            <div>
                <label class="lb" for="codigo">Código *</label>
                <input id="codigo" name="codigo" class="input" required
                       placeholder="MEM-0001"
                       value="<?= esc($membro['codigo'] ?? 'MEM-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT)) ?>">
                <?php if ($e = $errors['codigo'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
            </div>
            <div>
                <label class="lb" for="nome">Nome completo *</label>
                <input id="nome" name="nome" class="input" required
                       value="<?= esc($membro['nome'] ?? '') ?>">
                <?php if ($e = $errors['nome'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
            </div>
            <div>
                <label class="lb" for="telefone">Telefone</label>
                <input id="telefone" name="telefone" class="input"
                       placeholder="+244 9xx xxx xxx"
                       value="<?= esc($membro['telefone'] ?? '') ?>">
            </div>
            <div>
                <label class="lb" for="email">E-mail</label>
                <input id="email" name="email" type="email" class="input"
                       value="<?= esc($membro['email'] ?? '') ?>">
                <?php if ($e = $errors['email'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
            </div>
            <div>
                <label class="lb" for="data_nascimento">Data de nascimento</label>
                <input id="data_nascimento" name="data_nascimento" type="date" class="input"
                       value="<?= esc($membro['data_nascimento'] ?? '') ?>">
            </div>
            <div>
                <label class="lb" for="genero">Género</label>
                <select id="genero" name="genero" class="select">
                    <option value="">—</option>
                    <option value="M" <?= ($membro['genero'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
                    <option value="F" <?= ($membro['genero'] ?? '') === 'F' ? 'selected' : '' ?>>Feminino</option>
                    <option value="outro" <?= ($membro['genero'] ?? '') === 'outro' ? 'selected' : '' ?>>Outro</option>
                </select>
            </div>
            <div>
                <label class="lb" for="estado_civil">Estado civil</label>
                <select id="estado_civil" name="estado_civil" class="select">
                    <option value="">—</option>
                    <?php foreach (['solteiro' => 'Solteiro(a)', 'casado' => 'Casado(a)', 'divorciado' => 'Divorciado(a)', 'viuvo' => 'Viúvo(a)', 'uniao_facto' => 'União de facto'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($membro['estado_civil'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lb" for="cargo">Cargo / Função</label>
                <input id="cargo" name="cargo" class="input"
                       placeholder="Líder, Diácono, Membro..."
                       value="<?= esc($membro['cargo'] ?? '') ?>">
            </div>
        </div>

        <h2 style="font-size:15px;margin:24px 0 18px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-location-dot" aria-hidden="true" style="color:var(--gold-600)"></i>
            Morada
        </h2>

        <div class="grid" style="grid-template-columns:1fr 1fr;gap:16px">
            <div style="grid-column:1 / -1">
                <label class="lb" for="endereco">Endereço</label>
                <input id="endereco" name="endereco" class="input"
                       value="<?= esc($membro['endereco'] ?? '') ?>">
            </div>
            <div>
                <label class="lb" for="cidade">Cidade</label>
                <input id="cidade" name="cidade" class="input"
                       value="<?= esc($membro['cidade'] ?? '') ?>">
            </div>
            <div>
                <label class="lb" for="provincia">Província</label>
                <input id="provincia" name="provincia" class="input"
                       value="<?= esc($membro['provincia'] ?? '') ?>">
            </div>
        </div>

        <h2 style="font-size:15px;margin:24px 0 18px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-cross" aria-hidden="true" style="color:var(--gold-600)"></i>
            Vida Cristã
        </h2>

        <div class="grid" style="grid-template-columns:1fr 1fr 1fr;gap:16px">
            <div>
                <label class="lb" for="data_conversao">Data de conversão</label>
                <input id="data_conversao" name="data_conversao" type="date" class="input"
                       value="<?= esc($membro['data_conversao'] ?? '') ?>">
            </div>
            <div>
                <label class="lb" for="data_baptismo">Data de batismo</label>
                <input id="data_baptismo" name="data_baptismo" type="date" class="input"
                       value="<?= esc($membro['data_baptismo'] ?? '') ?>">
            </div>
            <div>
                <label class="lb" for="membro_desde">Membro desde</label>
                <input id="membro_desde" name="membro_desde" type="date" class="input"
                       value="<?= esc($membro['membro_desde'] ?? '') ?>">
            </div>
            <div>
                <label class="lb" for="status">Status</label>
                <select id="status" name="status" class="select">
                    <?php foreach (MEMBER_STATUS as $s): ?>
                        <option value="<?= $s ?>" <?= ($membro['status'] ?? 'activo') === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="margin-top:18px">
            <label class="lb" for="observacoes">Observações</label>
            <textarea id="observacoes" name="observacoes" class="input" rows="4"
                      style="height:auto;padding:10px 14px;resize:vertical"><?= esc($membro['observacoes'] ?? '') ?></textarea>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px">
            <a href="<?= esc($isEdit ? url('membros/' . $membro['id']) : url('membros')) ?>"
               class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <?= $isEdit ? 'Guardar alterações' : 'Criar membro' ?>
            </button>
        </div>
    </div>
</form>