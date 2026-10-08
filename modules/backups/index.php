<?php
// modules/backups/index.php
session_start();

if (!isset($_SESSION['user_id'])) {
    redirect('login');
    exit;
}

// Apenas admin pode acessar
if ($_SESSION['user_perfil'] != 'admin') {
    redirect('dashboard');
    exit;
}

$page_title = 'Backups - I.P.F.V.A Calemba 2';

require_once '../../config/database.php';
require_once '../../config/url.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../modules/audit/functions.php';
require_once 'functions.php';

$conn = getConnection();

$stats = getEstatisticasBackup($conn);
$ultimoBackup = getUltimoBackup($conn);
?>

<main class="main">
    <?php require_once '../../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-head">
            <h2><i class="fas fa-database"></i> Backups</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button onclick="baixarBackup('sql')" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,var(--blue-600),var(--blue-400));color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-file-code"></i> Baixar Backup SQL
                </button>
                <button onclick="baixarBackup('excel')" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;text-decoration:none;border-radius:var(--radius);background:linear-gradient(90deg,#3f7d4e,#34d399);color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;">
                    <i class="fas fa-file-excel"></i> Baixar Backup Excel
                </button>
            </div>
        </div>

        <!-- ==========================================
        RESUMO (CARDS)
        ========================================== -->
        <div class="grid-stats" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--blue-100);color:var(--blue-600);"><i class="fas fa-table"></i></div>
                <div class="stat-num"><?= $stats['total_tabelas'] ?></div>
                <div class="stat-label">Módulos/Tabelas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eaf3ec;color:#3f7d4e;"><i class="fas fa-list-ol"></i></div>
                <div class="stat-num"><?= number_format($stats['total_registos']) ?></div>
                <div class="stat-label">Total de Registos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fdeee3;color:#b9770e;"><i class="fas fa-hdd"></i></div>
                <div class="stat-num"><?= formatarBytes($stats['tamanho_bytes']) ?></div>
                <div class="stat-label">Tamanho da Base de Dados</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fcf8ec;color:#b08020;"><i class="fas fa-clock"></i></div>
                <div class="stat-num" style="font-size:16px;">
                    <?= $ultimoBackup ? date('d/m/Y H:i', strtotime($ultimoBackup['criado_em'])) : '—' ?>
                </div>
                <div class="stat-label">Último Backup</div>
            </div>
        </div>

        <!-- ==========================================
        INFORMAÇÃO
        ========================================== -->
        <div class="panel" style="margin-bottom:20px;background:#fcf8ec;border:1px solid #c7d2fe;">
            <div style="display:flex;gap:12px;align-items:flex-start;">
                <i class="fas fa-info-circle" style="color:#b08020;font-size:18px;margin-top:2px;"></i>
                <div style="font-size:13px;color:var(--gray-700);line-height:1.6;">
                    <strong>Backup SQL:</strong> ficheiro <code>.sql</code> com a estrutura e todos os dados das tabelas, pronto a importar no phpMyAdmin em caso de restauro.<br>
                    <strong>Backup Excel:</strong> ficheiro <code>.xlsx</code> com uma folha por módulo, cada uma contendo os respectivos registos — ideal para consulta, arquivo ou partilha.
                </div>
            </div>
        </div>

        <!-- ==========================================
        TABELA DE MÓDULOS
        ========================================== -->
        <div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 style="font-size:14.5px;font-weight:700;color:var(--blue-800);">
                    <i class="fas fa-list"></i> Módulos Incluídos no Backup
                </h3>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-100);">
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Módulo</th>
                            <th style="padding:10px 12px;text-align:left;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Tabela</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:700;color:var(--gray-600);border-bottom:2px solid var(--gray-200);">Registos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['tabelas'] as $tabela): ?>
                        <tr style="border-bottom:1px solid var(--gray-100);">
                            <td style="padding:10px 12px;font-weight:600;color:var(--gray-800);">
                                <i class="fas <?= getTabelaIcon($tabela) ?>" style="color:var(--blue-500);margin-right:6px;"></i>
                                <?= htmlspecialchars(getTabelaLabel($tabela)) ?>
                            </td>
                            <td style="padding:10px 12px;color:var(--gray-500);font-family:monospace;font-size:12px;">
                                <?= htmlspecialchars($tabela) ?>
                            </td>
                            <td style="padding:10px 12px;text-align:center;color:var(--gray-700);">
                                <?= number_format($stats['por_tabela'][$tabela] ?? 0) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once '../../includes/footer.php'; ?>
    </div>
</main>

<?php require_once '../../includes/footer-scripts.php'; ?>

<script>
function baixarBackup(tipo) {
    const url = tipo === 'sql'
        ? '<?= url('modules/backups/export-sql.php') ?>'
        : '<?= url('modules/backups/export-excel.php') ?>';
    window.location.href = url;
}
</script>
