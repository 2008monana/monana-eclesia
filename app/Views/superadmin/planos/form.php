<?php
/** @var array $supervisor */
/** @var array $plano */
/** @var bool $isCreate */
/** @var array $errors */

$action = $isCreate ? url('superadmin/planos') : url('superadmin/planos/' . $plano['id']);
?>
<div class="page-head">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= esc(url('superadmin/planos')) ?>" class="icon-btn" style="text-decoration:none">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <h1 class="page-title"><?= $isCreate ? 'Novo Plano' : 'Editar Plano' ?></h1>
    </div>
</div>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>

    <div class="card" style="max-width:800px">
        <div class="card-head">
            <div class="card-title">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                Dados do Plano
            </div>
        </div>

        <div style="display:grid;gap:14px">
            <div class="grid" style="grid-template-columns:2fr 1fr;gap:12px">
                <div>
                    <label class="lb">Nome *</label>
                    <input name="nome" class="input" required value="<?= esc($plano['nome'] ?? '') ?>">
                    <?php if ($e = $errors['nome'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
                </div>
                <div>
                    <label class="lb">Slug *</label>
                    <input name="slug" class="input" required value="<?= esc($plano['slug'] ?? '') ?>">
                    <?php if ($e = $errors['slug'] ?? null): ?><div class="err"><?= esc($e) ?></div><?php endif; ?>
                </div>
            </div>

            <div>
                <label class="lb">Descrição</label>
                <input name="descricao" class="input" value="<?= esc($plano['descricao'] ?? '') ?>">
            </div>

            <div class="grid" style="grid-template-columns:1fr 1fr 1fr;gap:12px">
                <div>
                    <label class="lb">Preço (Kz)</label>
                    <input name="preco_kz" type="number" step="0.01" min="0" class="input"
                           value="<?= esc($plano['preco_kz'] ?? '0') ?>">
                </div>
                <div>
                    <label class="lb">Periodicidade (dias)</label>
                    <input name="periodicidade_dias" type="number" min="0" class="input"
                           value="<?= esc($plano['periodicidade_dias'] ?? '30') ?>">
                </div>
                <div>
                    <label class="lb">Ordem</label>
                    <input name="ordem" type="number" min="0" class="input"
                           value="<?= esc($plano['ordem'] ?? '0') ?>">
                </div>
            </div>

            <div class="grid" style="grid-template-columns:1fr 1fr 1fr;gap:12px">
                <div>
                    <label class="lb">Limite de membros</label>
                    <input name="limite_membros" type="number" min="0" class="input"
                           placeholder="(vazio = ilimitado)"
                           value="<?= esc($plano['limite_membros'] ?? '') ?>">
                </div>
                <div>
                    <label class="lb">Limite de users</label>
                    <input name="limite_users" type="number" min="0" class="input"
                           placeholder="(vazio = ilimitado)"
                           value="<?= esc($plano['limite_users'] ?? '') ?>">
                </div>
                <div>
                    <label class="lb">Limite de storage (MB)</label>
                    <input name="limite_storage_mb" type="number" min="0" class="input"
                           placeholder="(vazio = ilimitado)"
                           value="<?= esc($plano['limite_storage_mb'] ?? '') ?>">
                </div>
            </div>

            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" class="chk"
                       <?= (int) ($plano['ativo'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span style="font-size:13.5px;color:var(--brown-800)">Plano ativo</span>
            </label>
        </div>
    </div>

    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px">
        <a href="<?= esc(url('superadmin/planos')) ?>" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-gold">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            <?= $isCreate ? 'Criar plano' : 'Guardar alterações' ?>
        </button>
    </div>
</form>