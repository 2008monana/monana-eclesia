<?php
/** @var array $supervisor */
/** @var array $igreja */
/** @var array $planos */
/** @var bool $isCreate */
/** @var array $errors */

$action = $isCreate ? url('superadmin/igrejas') : url('superadmin/igrejas/' . $igreja['id']);
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('superadmin/igrejas')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title"><?= $isCreate ? 'Nova Igreja' : 'Editar Igreja' ?></h1>
    </div>
</div>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>

    <div class="grid row-2" style="gap:16px">
        <!-- Dados da igreja -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <i class="fa-solid fa-church" aria-hidden="true"></i>
                    Dados da Igreja
                </div>
            </div>

            <div style="display:grid;gap:14px">
                <div>
                    <label class="lb">Nome *</label>
                    <input name="nome" class="input" required value="<?= esc($igreja['nome'] ?? '') ?>">
                    <?php if ($e = $errors['nome'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
                </div>

                <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="lb">Slug *</label>
                        <input name="slug" class="input" required value="<?= esc($igreja['slug'] ?? '') ?>"
                               placeholder="ex: nova-alianca">
                        <?php if ($e = $errors['slug'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label class="lb">NIF</label>
                        <input name="nif" class="input" value="<?= esc($igreja['nif'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label class="lb">E-mail *</label>
                    <input name="email" type="email" class="input" required value="<?= esc($igreja['email'] ?? '') ?>">
                    <?php if ($e = $errors['email'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
                </div>

                <div>
                    <label class="lb">Telefone</label>
                    <input name="telefone" class="input" value="<?= esc($igreja['telefone'] ?? '') ?>">
                </div>

                <div>
                    <label class="lb">Endereço</label>
                    <input name="endereco" class="input" value="<?= esc($igreja['endereco'] ?? '') ?>">
                </div>

                <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="lb">Cidade</label>
                        <input name="cidade" class="input" value="<?= esc($igreja['cidade'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="lb">Província</label>
                        <input name="provincia" class="input" value="<?= esc($igreja['provincia'] ?? '') ?>">
                    </div>
                </div>

                <div class="grid" style="grid-template-columns:1fr 1fr 1fr;gap:12px">
                    <div>
                        <label class="lb">País</label>
                        <input name="pais" class="input" value="<?= esc($igreja['pais'] ?? 'Angola') ?>">
                    </div>
                    <div>
                        <label class="lb">Fuso horário</label>
                        <input name="fuso_horario" class="input" value="<?= esc($igreja['fuso_horario'] ?? 'Africa/Luanda') ?>">
                    </div>
                    <div>
                        <label class="lb">Moeda</label>
                        <input name="moeda" class="input" value="<?= esc($igreja['moeda'] ?? 'AOA') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Plano + Admin -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <i class="fa-solid fa-file-contract" aria-hidden="true"></i>
                    Plano e Administrador
                </div>
            </div>

            <div style="display:grid;gap:14px">
                <div>
                    <label class="lb">Plano</label>
                    <select name="plano_id" class="select">
                        <option value="">—</option>
                        <?php foreach ($planos as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"
                                <?= (int) ($igreja['plano_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= esc($p['nome']) ?> — <?= fmtKz($p['preco_kz']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!$isCreate): ?>
                    <div>
                        <label class="lb">Status</label>
                        <select name="status" class="select">
                            <option value="em_teste" <?= ($igreja['status'] ?? '') === 'em_teste' ? 'selected' : '' ?>>Em teste</option>
                            <option value="activa" <?= ($igreja['status'] ?? '') === 'activa' ? 'selected' : '' ?>>Ativa</option>
                            <option value="suspensa" <?= ($igreja['status'] ?? '') === 'suspensa' ? 'selected' : '' ?>>Suspensa</option>
                            <option value="cancelada" <?= ($igreja['status'] ?? '') === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($isCreate): ?>
                    <div style="background:var(--gold-50);border-radius:8px;padding:14px;border:1px dashed var(--gold-300);margin-top:8px">
                        <div style="font-size:12px;color:var(--gold-800);margin-bottom:8px;font-weight:600">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            Administrador da Igreja
                        </div>
                        <p class="muted" style="font-size:11.5px;margin:0 0 12px;line-height:1.5">
                            Será criado um utilizador admin e enviadas as credenciais por e-mail.
                            Se deixar o e-mail em branco, usa o e-mail da igreja.
                        </p>

                        <div style="display:grid;gap:10px">
                            <div>
                                <label class="lb">Nome do admin</label>
                                <input name="admin_nome" class="input" value="<?= esc($igreja['admin_nome'] ?? 'Administrador') ?>">
                            </div>
                            <div>
                                <label class="lb">E-mail do admin</label>
                                <input name="admin_email" type="email" class="input" value="<?= esc($igreja['admin_email'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px">
        <a href="<?= esc(url('superadmin/igrejas')) ?>" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-gold">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            <?= $isCreate ? 'Criar igreja e enviar credenciais' : 'Guardar alterações' ?>
        </button>
    </div>
</form>