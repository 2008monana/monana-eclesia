<?php
/** @var array $user */
/** @var array $backups */
/** @var bool $podeExecutar */
/** @var bool $podeRestaurar */

$estadoLabel = [
    'pendente'  => 'Pendente',
    'em_curso'  => 'Em curso',
    'concluido' => 'Concluído',
    'falhou'    => 'Falhou',
];
$estadoClass = [
    'pendente'  => 'off',
    'em_curso'  => 'off',
    'concluido' => 'ok',
    'falhou'    => 'off',
];

$totalConcluidos = 0;
foreach ($backups as $b) {
    if ($b['estado'] === 'concluido') $totalConcluidos++;
}
?>
<div class="page-head">
    <div>
        <h1 class="page-title">Backups</h1>
        <p class="muted" style="margin:4px 0 0;font-size:13px">
            <?= count($backups) ?> registo(s) · <?= $totalConcluidos ?> concluído(s)
        </p>
    </div>
    <?php if ($podeExecutar): ?>
        <form method="post" action="<?= esc(url('backups/executar')) ?>"
              style="margin:0" onsubmit="return confirm('Criar um novo backup agora?')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i>
                Executar backup agora
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if (!$podeExecutar && !$podeRestaurar): ?>
    <div class="card" style="margin-bottom:16px;background:var(--warn-bg);border-color:var(--gold-300);padding:14px 18px">
        <div style="display:flex;gap:10px;align-items:center;font-size:13px;color:var(--gold-900)">
            <i class="fa-solid fa-eye" aria-hidden="true"></i>
            <span>Modo de leitura — sem permissão para criar ou restaurar backups.</span>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <?php if (empty($backups)): ?>
        <div style="text-align:center;padding:40px">
            <i class="fa-solid fa-database" aria-hidden="true" style="font-size:48px;color:var(--gold-300)"></i>
            <p class="muted" style="margin:12px 0 0">Nenhum backup criado ainda.</p>
            <?php if ($podeExecutar): ?>
                <p class="muted" style="margin:6px 0 0;font-size:12.5px">
                    Clique em <strong>Executar backup agora</strong> para criar o primeiro.
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ficheiro</th>
                        <th>Tipo</th>
                        <th>Tamanho</th>
                        <th>Estado</th>
                        <th>Criado</th>
                        <th style="text-align:right">Acções</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $b):
                        $isConcluido = $b['estado'] === 'concluido';
                        $tamanhoFormatado = $b['tamanho_bytes'] ? $this->formatBytes((int) $b['tamanho_bytes']) : '—';
                    ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <i class="fa-solid fa-file-code" aria-hidden="true" style="color:var(--gold-600)"></i>
                                    <span style="font-family:monospace;font-size:12px">
                                        <?= esc($b['ficheiro'] ?: '(em criação...)') ?>
                                    </span>
                                </div>
                                <?php if ($b['mensagem']): ?>
                                    <div style="font-size:11px;color:var(--danger);margin-top:4px">
                                        <?= esc(mb_strimwidth($b['mensagem'], 0, 80, '...')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size:12px;background:var(--gold-100);color:var(--gold-700);padding:2px 8px;border-radius:999px">
                                    <?= esc(ucfirst($b['tipo'])) ?>
                                </span>
                            </td>
                            <td class="muted" style="font-size:12.5px"><?= $tamanhoFormatado ?></td>
                            <td>
                                <span class="status <?= $estadoClass[$b['estado']] ?? 'off' ?>">
                                    <?= esc($estadoLabel[$b['estado']] ?? $b['estado']) ?>
                                </span>
                            </td>
                            <td class="muted" style="font-size:12.5px">
                                <?= esc(date('d/m/Y H:i', strtotime($b['iniciado_em']))) ?>
                            </td>
                            <td style="text-align:right;white-space:nowrap">
                                <?php if ($isConcluido): ?>
                                    <a href="<?= esc(url('backups/' . $b['id'] . '/download')) ?>"
                                       class="icon-btn" title="Descarregar" style="display:inline-grid">
                                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($podeRestaurar && $isConcluido): ?>
                                    <button type="button"
                                            onclick="abrirRestauro(<?= (int) $b['id'] ?>, '<?= esc($b['ficheiro']) ?>')"
                                            class="icon-btn" title="Restaurar" style="display:inline-grid">
                                        <i class="fa-solid fa-rotate-left" aria-hidden="true" style="color:var(--danger)"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($podeExecutar): ?>
                                    <form method="post" action="<?= esc(url('backups/' . $b['id'] . '/eliminar')) ?>"
                                          style="margin:0;display:inline-grid"
                                          onsubmit="return confirm('Eliminar este backup?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn" title="Eliminar">
                                            <i class="fa-solid fa-trash" aria-hidden="true" style="color:var(--danger)"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de restauro -->
<div id="modal-restauro" style="display:none;position:fixed;inset:0;background:rgba(46,27,14,.55);z-index:100;align-items:center;justify-content:center;padding:20px">
    <div class="card" style="max-width:520px;width:100%;padding:28px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
            <span style="width:44px;height:44px;border-radius:50%;background:var(--danger-bg);display:grid;place-items:center;color:var(--danger)">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" style="font-size:20px"></i>
            </span>
            <div>
                <h2 style="font-size:18px;color:var(--brown-900);margin:0">Restaurar backup?</h2>
                <p class="muted" style="margin:2px 0 0;font-size:12.5px">
                    Esta ação é irreversível.
                </p>
            </div>
        </div>

        <p style="font-size:13px;color:var(--brown-800);line-height:1.6">
            Os dados atuais da igreja serão <strong>substituídos</strong> pelo conteúdo do backup
            <code id="nome-backup" style="background:var(--gold-100);padding:2px 6px;border-radius:4px;font-size:12px"></code>.
        </p>
        <p style="font-size:13px;color:var(--brown-800);line-height:1.6">
            Para confirmar, escreva <strong>RESTAURAR</strong> no campo abaixo:
        </p>

        <form id="form-restauro" method="post" action="" style="margin-top:14px">
            <?= csrf_field() ?>
            <input type="text" name="confirmar" class="input" required
                   placeholder="RESTAURAR" autocomplete="off"
                   style="text-transform:uppercase">
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
                <button type="button" class="btn btn-outline" onclick="fecharRestauro()">Cancelar</button>
                <button type="submit" class="btn btn-gold" style="background:linear-gradient(180deg,#dc2626,#991b1b)">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                    Restaurar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirRestauro(id, nome) {
        document.getElementById('nome-backup').textContent = nome;
        document.getElementById('form-restauro').action = '<?= esc(url('backups')) ?>/' + id + '/restaurar';
        document.getElementById('modal-restauro').style.display = 'flex';
    }
    function fecharRestauro() {
        document.getElementById('modal-restauro').style.display = 'none';
    }
</script>