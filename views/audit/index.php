<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-shield-alt"></i> Auditoria</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button onclick="gerarPDF()" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-file-pdf"></i> Gerar PDF
                </button>
            </div>
        </div>

        <!-- ==========================================
        RESUMO (CARDS)
        ========================================== -->
        <div class="grid-stats" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-list"></i></div>
                <div class="stat-num"><?= number_format($total_geral) ?></div>
                <div class="stat-label">Total de Atividades</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-user-check"></i></div>
                <div class="stat-num"><?= count($logs) ?></div>
                <div class="stat-label">Últimos 500 Registos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fdeee3;color:#b9770e;"><i class="fas fa-users"></i></div>
                <div class="stat-num"><?= count($usuarios) ?></div>
                <div class="stat-label">Utilizadores</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-clock"></i></div>
                <div class="stat-num">
                    <?php if ($ultimo_login): ?>
                        <?= date('d/m/Y H:i', strtotime($ultimo_login['criado_em'])) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
                <div class="stat-label">Último Login</div>
            </div>
        </div>

        <!-- ==========================================
        FILTROS
        ========================================== -->
        <div class="panel" style="margin-bottom:20px;">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Utilizador</label>
                    <select name="usuario_id" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="0">Todos</option>
                        <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $usuario_id == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nome_completo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Módulo</label>
                    <select name="modulo" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="">Todos</option>
                        <?php foreach ($modulos as $m): ?>
                        <option value="<?= $m ?>" <?= $modulo == $m ? 'selected' : '' ?>>
                            <?= getModuloLabel($m) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Ação</label>
                    <select name="acao" style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                        <option value="">Todas</option>
                        <?php foreach ($acoes as $a): ?>
                        <option value="<?= $a ?>" <?= $acao == $a ? 'selected' : '' ?>>
                            <?= ucfirst($a) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Data Início</label>
                    <input type="date" name="data_inicio" value="<?= $data_inicio ?>"
                           style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:2px;">Data Fim</label>
                    <input type="date" name="data_fim" value="<?= $data_fim ?>"
                           style="padding:8px 14px;border-radius:var(--radius);border:1.5px solid var(--gray-200);font-family:'Inter',sans-serif;font-size:13px;">
                </div>
                <div>
                    <button type="submit" class="btn-primary" style="padding:8px 20px;border:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:'Inter',sans-serif;">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
                <div>
                    <a href="<?= url('modules/audit/index.php') ?>" style="padding:8px 16px;border-radius:var(--radius);border:1.5px solid var(--gray-200);color:var(--gray-600);text-decoration:none;font-weight:600;font-size:13px;transition:.2s;display:inline-block;">
                        <i class="fas fa-undo"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- ==========================================
        TABELA DE LOGS
        ========================================== -->
        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 style="font-size:14.5px;font-weight:700;color:var(--blue-800);">
                    <i class="fas fa-list"></i> Histórico de Atividades
                    <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(<?= $total_logs ?> registros)</span>
                </h3>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-100);">
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">#</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Data/Hora</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Utilizador</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Ação</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Módulo</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Descrição</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);">
                                <i class="fas fa-shield-alt" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.3;"></i>
                                Nenhuma atividade registada.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($logs as $index => $log): 
                            $acao_info = getAcaoLabel($log['acao']);
                        ?>
                        <tr style="border-bottom:1px solid var(--gray-100);">
                            <td style="padding:10px 12px;color:var(--gray-500);"><?= $index + 1 ?></td>
                            <td style="padding:10px 12px;color:var(--gray-700);font-size:12px;">
                                <?= date('d/m/Y H:i:s', strtotime($log['criado_em'])) ?>
                            </td>
                            <td style="padding:10px 12px;font-weight:600;color:var(--gray-800);">
                                <?= htmlspecialchars($log['usuario_nome']) ?>
                            </td>
                            <td style="padding:10px 12px;">
                                <span style="display:inline-block;padding:2px 12px;border-radius:20px;background:<?= $acao_info['color'] ?>20;color:<?= $acao_info['color'] ?>;font-size:11px;font-weight:600;">
                                    <i class="fas <?= $acao_info['icon'] ?>"></i> <?= $acao_info['label'] ?>
                                </span>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-600);font-size:12px;">
                                <?= getModuloLabel($log['modulo']) ?>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-600);font-size:12px;max-width:300px;word-wrap:break-word;">
                                <?= htmlspecialchars($log['descricao']) ?>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-500);font-size:11px;">
                                <?= htmlspecialchars($log['ip']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>

<script>
function gerarPDF() {
    const params = new URLSearchParams(window.location.search);
    window.open('<?= url('modules/audit/generate-pdf.php') ?>?' + params.toString(), '_blank');
}
</script>
