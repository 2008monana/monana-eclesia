<?php
/** @var array $supervisor */
/** @var array $alvo */
/** @var bool $isCreate */
/** @var array $errors */
/** @var array|null $todasIgrejas */
/** @var array|null $idsIgrejasAtuais */

$action = $isCreate ? url('superadmin/supervisores') : url('superadmin/supervisores/' . $alvo['id']);
$todasIgrejas = $todasIgrejas ?? [];
$idsIgrejasAtuais = $idsIgrejasAtuais ?? [];
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('superadmin/supervisores')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title"><?= $isCreate ? 'Novo Supervisor' : 'Editar Supervisor' ?></h1>
    </div>
</div>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>

    <div class="grid row-2" style="gap:16px">
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                    Dados
                </div>
            </div>

            <div style="display:grid;gap:14px">
                <div>
                    <label class="lb">Nome *</label>
                    <input name="nome" class="input" required value="<?= esc($alvo['nome'] ?? '') ?>">
                    <?php if ($e = $errors['nome'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
                </div>

                <div>
                    <label class="lb">E-mail *</label>
                    <input name="email" type="email" class="input" required value="<?= esc($alvo['email'] ?? '') ?>">
                    <?php if ($e = $errors['email'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
                </div>

                <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="lb">Nível *</label>
                        <select name="nivel" class="select" required>
                            <option value="supervisor" <?= ($alvo['nivel'] ?? 'supervisor') === 'supervisor' ? 'selected' : '' ?>>Supervisor</option>
                            <option value="superadmin" <?= ($alvo['nivel'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                        </select>
                    </div>
                    <div>
                        <label class="lb">Telefone</label>
                        <input name="telefone" class="input" value="<?= esc($alvo['telefone'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label class="lb">Senha <?= $isCreate ? '*' : '(deixe em branco para manter)' ?></label>
                    <input name="senha" type="password" class="input"
                           <?= $isCreate ? 'required minlength="8"' : 'minlength="8"' ?>
                           placeholder="Mínimo 8 caracteres">
                    <?php if ($e = $errors['senha'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
                </div>

                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="hidden" name="ativo" value="0">
                    <input type="checkbox" name="ativo" value="1" class="chk"
                           <?= (int) ($alvo['ativo'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span style="font-size:13.5px;color:var(--brown-800)">Supervisor ativo</span>
                </label>
            </div>
        </div>

        <?php if (!$isCreate): ?>
            <div class="card">
                <div class="card-head">
                    <div class="card-title">
                        <i class="fa-solid fa-church" aria-hidden="true"></i>
                        Igrejas atribuídas
                    </div>
                </div>
                <p class="muted" style="font-size:12.5px;margin:0 0 14px">
                    Este supervisor verá apenas as igrejas marcadas abaixo.
                </p>
                <?php if (empty($todasIgrejas)): ?>
                    <p class="muted">Sem igrejas registadas.</p>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:6px;max-height:400px;overflow-y:auto">
                        <?php foreach ($todasIgrejas as $ig): ?>
                            <label style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--ivory-bg);border-radius:8px;cursor:pointer;font-size:13px">
                                <input type="checkbox" name="igrejas[]" value="<?= (int) $ig['id'] ?>" class="chk"
                                       <?= in_array((int) $ig['id'], $idsIgrejasAtuais, true) ? 'checked' : '' ?>>
                                <span><strong><?= esc($ig['nome']) ?></strong>
                                    <span class="muted" style="font-size:11px">(<?= esc($ig['slug']) ?>)</span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px">
        <a href="<?= esc(url('superadmin/supervisores')) ?>" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-gold">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            <?= $isCreate ? 'Criar supervisor' : 'Guardar alterações' ?>
        </button>
    </div>
</form>