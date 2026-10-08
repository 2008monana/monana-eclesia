<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-user-cog"></i> Meu Perfil</h2>
            <?php if (podeAcessarModulo('dashboard')): ?>
            <a href="<?= url('modules/dashboard/index.php') ?>" style="display:inline-flex;align-items:center;gap:6px;color:var(--gray-500);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;">
                <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
            </a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['sem_acesso'])): ?>
        <div style="background:#fff3cd;border:1px solid #ffe69c;color:#7a5b00;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:13.5px;">
            <i class="fas fa-info-circle"></i> Não tem acesso a essa página. Esta é a sua página de Perfil - fale com o administrador se precisar de acesso a outro módulo.
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div style="background:#f9e9e5;border:1px solid #f5c6c6;color:#721c24;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px;">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div style="background:#eaf3ec;border:1px solid #a5d6a7;color:#2e7d32;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px;">
            <i class="fas fa-check-circle"></i> <?= $success ?>
        </div>
        <?php endif; ?>

        <div class="panel" style="max-width:600px;margin:0 auto;">
            <!-- Cabeçalho do Perfil -->
            <div style="text-align:center;margin-bottom:24px;">
                <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--blue-500),var(--blue-700));display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:32px;color:#fff;font-weight:700;font-family:'Inter',sans-serif;">
                    <?= strtoupper(substr($usuario['nome_completo'], 0, 2)) ?>
                </div>
                <h2 style="font-size:20px;font-weight:700;color:var(--blue-800);"><?= htmlspecialchars($usuario['nome_completo']) ?></h2>
                <span style="display:inline-block;padding:2px 16px;border-radius:20px;background:<?= $usuario['perfil'] == 'admin' ? '#f9e9e5' : ($usuario['perfil'] == 'editor' ? '#fdf1d9' : '#eaf3ec') ?>;color:<?= $usuario['perfil'] == 'admin' ? '#b5412f' : ($usuario['perfil'] == 'editor' ? '#b9770e' : '#3f7d4e') ?>;font-size:12px;font-weight:600;">
                    <?= ucfirst($usuario['perfil']) ?>
                </span>
            </div>

            <form method="POST" action="">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Nome Completo <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome_completo']) ?>" required
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                    <div style="grid-column:1/3;">
                        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                            Email <span style="color:var(--danger);">*</span>
                        </label>
                        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required
                               style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                    </div>
                </div>

                <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);">
                    <div style="font-weight:700;color:var(--gray-700);margin-bottom:12px;font-size:14px;">
                        <i class="fas fa-lock"></i> Alterar Palavra-passe
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div style="grid-column:1/3;">
                            <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                                Senha Atual
                            </label>
                            <input type="password" name="senha_atual" placeholder="Digite a senha atual para alterar"
                                   style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                                Nova Senha
                            </label>
                            <input type="password" name="nova_senha" placeholder="Mínimo 6 caracteres" minlength="6"
                                   style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
                                Confirmar Nova Senha
                            </label>
                            <input type="password" name="confirmar_senha" placeholder="Confirme a nova senha"
                                   style="width:100%;padding:10px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:14px;transition:.2s;">
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:24px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary" style="padding:12px 30px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-save"></i> SALVAR ALTERAÇÕES
                    </button>
                    <a href="<?= url('modules/dashboard/index.php') ?>" style="padding:12px 24px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:14px;transition:.2s;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>

            <!-- Informações Adicionais -->
            <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div style="background:var(--gray-50);padding:12px 16px;border-radius:var(--radius);">
                    <div style="font-size:11px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">ID do Utilizador</div>
                    <div style="font-size:14px;font-weight:600;color:var(--gray-800);">#<?= str_pad($usuario['id'], 4, '0', STR_PAD_LEFT) ?></div>
                </div>
                <div style="background:var(--gray-50);padding:12px 16px;border-radius:var(--radius);">
                    <div style="font-size:11px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Data de Cadastro</div>
                    <div style="font-size:14px;font-weight:600;color:var(--gray-800);">
                        <?= date('d/m/Y H:i', strtotime($usuario['criado_em'])) ?>
                    </div>
                </div>
                <div style="grid-column:1/3;background:var(--gray-50);padding:12px 16px;border-radius:var(--radius);">
                    <div style="font-size:11px;color:var(--gray-400);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Último Login</div>
                    <div style="font-size:14px;font-weight:600;color:var(--gray-800);">
                        <?= $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca' ?>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>
