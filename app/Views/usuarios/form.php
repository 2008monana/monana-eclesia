<?php
/** @var array $user */
/** @var array $usuario */
/** @var array $roles */
/** @var bool $isCreate */
/** @var array $errors */

$isEdit = !$isCreate;
$action = $isEdit ? url('usuarios/' . $usuario['id']) : url('usuarios');

// Roles já atribuídas (só no caso de criação usamos o form completo; na edição é na página show)
$rolesAtuais = $usuario['roles_atuais'] ?? [];
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc($isEdit ? url('usuarios/' . $usuario['id']) : url('usuarios')) ?>"
           class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title"><?= $isEdit ? 'Editar Usuário' : 'Novo Usuário' ?></h1>
    </div>
</div>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>

    <div class="card" style="max-width:800px">
        <h2 style="font-size:15px;margin-bottom:18px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-user" aria-hidden="true" style="color:var(--gold-600)"></i>
            Dados do Usuário
        </h2>

        <div class="grid" style="grid-template-columns:1fr 1fr;gap:16px">
            <div>
                <label class="lb" for="nome">Nome completo *</label>
                <input id="nome" name="nome" class="input" required
                       value="<?= esc($usuario['nome'] ?? '') ?>">
                <?php if ($e = $errors['nome'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
            </div>
            <div>
                <label class="lb" for="email">E-mail *</label>
                <input id="email" name="email" type="email" class="input" required
                       value="<?= esc($usuario['email'] ?? '') ?>">
                <?php if ($e = $errors['email'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
            </div>
            <div>
                <label class="lb" for="telefone">Telefone</label>
                <input id="telefone" name="telefone" class="input"
                       value="<?= esc($usuario['telefone'] ?? '') ?>">
            </div>
            <div>
                <label class="lb" for="senha">
                    Senha <?= $isCreate ? '*' : '(deixe em branco para manter)' ?>
                </label>
                <input id="senha" name="senha" type="password" class="input"
                       <?= $isCreate ? 'required minlength="8"' : 'minlength="8"' ?>
                       placeholder="Mínimo 8 caracteres">
                <?php if ($e = $errors['senha'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
            </div>
        </div>

        <div style="margin-top:18px">
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" class="chk"
                       <?= (int) ($usuario['ativo'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span style="font-size:13.5px;color:var(--brown-800)">Usuário ativo</span>
            </label>
        </div>

        <?php if ($isCreate && !empty($roles)): ?>
            <h2 style="font-size:15px;margin:24px 0 18px;color:var(--brown-900);display:flex;align-items:center;gap:8px">
                <i class="fa-solid fa-shield-halved" aria-hidden="true" style="color:var(--gold-600)"></i>
                Roles iniciais
            </h2>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <?php foreach ($roles as $r): ?>
                    <label style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--ivory-bg);border-radius:8px;cursor:pointer;font-size:13px">
                        <input type="checkbox" name="roles[]" value="<?= (int) $r['id'] ?>" class="chk">
                        <span>
                            <strong><?= esc($r['nome']) ?></strong>
                            <?php if ($r['sistema']): ?>
                                <span class="muted" style="font-size:11px">(sistema)</span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px">
            <a href="<?= esc($isEdit ? url('usuarios/' . $usuario['id']) : url('usuarios')) ?>"
               class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <?= $isEdit ? 'Guardar alterações' : 'Criar usuário' ?>
            </button>
        </div>
    </div>
</form>