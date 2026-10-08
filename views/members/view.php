<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>
    
    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-user"></i> Visualizar Membro</h2>
            <a href="<?= url('modules/members/index.php') ?>" style="display:inline-flex;align-items:center;gap:6px;color:var(--gray-500);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <div class="panel" style="max-width:700px;margin:0 auto;">
            <div style="text-align:center;margin-bottom:24px;">
                <div style="width:80px;height:80px;border-radius:50%;background:<?= $cat['color'] ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:32px;color:#fff;">
                    <i class="fas <?= $cat['icon'] ?>"></i>
                </div>
                <h2 style="font-size:22px;font-weight:800;color:var(--blue-800);"><?= htmlspecialchars($membro['nome_completo']) ?></h2>
                <span style="display:inline-block;padding:4px 16px;border-radius:20px;background:<?= $cat['color'] ?>;color:#fff;font-size:13px;font-weight:600;">
                    <?= $cat['label'] ?>
                </span>
                <span style="display:inline-block;padding:4px 16px;border-radius:20px;margin-left:8px;background:<?= $membro['ativo'] ? '#eaf3ec' : '#f9e9e5' ?>;color:<?= $membro['ativo'] ? '#3f7d4e' : '#b5412f' ?>;font-size:13px;font-weight:600;">
                    <i class="fas fa-circle" style="font-size:6px;margin-right:4px;"></i>
                    <?= $membro['ativo'] ? 'Ativo' : 'Inativo' ?>
                </span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <!-- Telefone -->
                <div style="background:var(--gray-50);padding:16px;border-radius:var(--radius);">
                    <div style="font-size:11px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Telefone</div>
                    <div style="font-size:16px;font-weight:600;color:var(--gray-800);margin-top:4px;">
                        <?php if (!empty($membro['telefone'])): ?>
                            <?= htmlspecialchars($membro['telefone']) ?>
                        <?php else: ?>
                            <span style="color:var(--gray-400);font-weight:400;">Não informado</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Data de Nascimento -->
                <div style="background:var(--gray-50);padding:16px;border-radius:var(--radius);">
                    <div style="font-size:11px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Data de Nascimento</div>
                    <div style="font-size:16px;font-weight:600;color:var(--gray-800);margin-top:4px;">
                        <?php if (!empty($membro['data_nascimento'])): ?>
                            <?= date('d/m/Y', strtotime($membro['data_nascimento'])) ?>
                        <?php else: ?>
                            <span style="color:var(--gray-400);font-weight:400;">Não informado</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Endereço -->
                <div style="grid-column:1/3;background:var(--gray-50);padding:16px;border-radius:var(--radius);">
                    <div style="font-size:11px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Endereço</div>
                    <div style="font-size:16px;font-weight:600;color:var(--gray-800);margin-top:4px;">
                        <?php if (!empty($membro['endereco'])): ?>
                            <?= htmlspecialchars($membro['endereco']) ?>
                        <?php else: ?>
                            <span style="color:var(--gray-400);font-weight:400;">Não informado</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Data de Cadastro -->
                <div style="background:var(--gray-50);padding:16px;border-radius:var(--radius);">
                    <div style="font-size:11px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Data de Cadastro</div>
                    <div style="font-size:16px;font-weight:600;color:var(--gray-800);margin-top:4px;">
                        <?= date('d/m/Y H:i', strtotime($membro['criado_em'])) ?>
                    </div>
                </div>

                <!-- ID do Membro -->
                <div style="background:var(--gray-50);padding:16px;border-radius:var(--radius);">
                    <div style="font-size:11px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">ID do Membro</div>
                    <div style="font-size:16px;font-weight:600;color:var(--gray-800);margin-top:4px;">
                        #<?= str_pad($membro['id'], 4, '0', STR_PAD_LEFT) ?>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:12px;margin-top:24px;flex-wrap:wrap;justify-content:center;">
                <a href="<?= url('modules/members/edit.php?id=' . $membro['id']) ?>" 
                   style="padding:12px 24px;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;text-decoration:none;font-weight:700;font-size:14px;display:inline-flex;align-items:center;gap:8px;">
                    <i class="fas fa-edit"></i> Editar
                </a>
                <a href="<?= url('modules/members/index.php') ?>" 
                   style="padding:12px 24px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:14px;display:inline-flex;align-items:center;gap:8px;">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>
