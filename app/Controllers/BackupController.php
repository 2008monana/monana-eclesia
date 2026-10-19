<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Rbac;
use App\Core\Tenant;
use App\Core\View;

final class BackupController extends Controller
{
    // ======================================================
    // LISTA
    // ======================================================

    public function index(): void
    {
        if (!Rbac::can('backup.ver')) {
            flash('error', 'Sem permissão para ver backups.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();

        $backups = Database::select(
            'SELECT b.*, u.nome AS criador_nome
             FROM `backups` b
             LEFT JOIN `users` u ON u.id = b.igreja_id
             WHERE b.igreja_id = :i
             ORDER BY b.id DESC
             LIMIT 50',
            ['i' => $igrejaId]
        );

        // Marca como falhado os backups "pendente" com mais de 5 minutos
        foreach ($backups as $b) {
            if ($b['estado'] === 'pendente' && strtotime($b['iniciado_em']) < time() - 300) {
                Database::execute(
                    'UPDATE `backups` SET `estado` = "falhou", `mensagem` = "Timeout" WHERE `id` = :id',
                    ['id' => $b['id']]
                );
            }
        }

        $podeExecutar  = Rbac::can('backup.executar');
        $podeRestaurar = Rbac::can('backup.restaurar');

        View::render('backups/index', [
            'title'         => 'Backups — Monana Eclesia',
            'user'          => Auth::user(),
            'backups'       => $backups,
            'podeExecutar'  => $podeExecutar,
            'podeRestaurar' => $podeRestaurar,
        ], 'layouts/app');
    }

    // ======================================================
    // EXECUTAR
    // ======================================================

    public function executar(): void
    {
        if (!Rbac::can('backup.executar')) {
            flash('error', 'Sem permissão.');
            redirect('/backups');
        }

        $igrejaId = Tenant::id();
        $user     = Auth::user();

        // Cria registo "em_curso"
        $id = Database::insert(
            'INSERT INTO `backups`
                (`igreja_id`, `tipo`, `ficheiro`, `estado`, `mensagem`)
             VALUES (:i, "manual", "", "em_curso", "A gerar...")',
            ['i' => $igrejaId]
        );

        try {
            $result = $this->gerarBackup($igrejaId);

            Database::execute(
                'UPDATE `backups` SET
                    `ficheiro` = :f,
                    `tamanho_bytes` = :t,
                    `hash_sha256` = :h,
                    `estado` = "concluido",
                    `mensagem` = NULL,
                    `concluido_em` = NOW(),
                    `expira_em` = DATE_ADD(NOW(), INTERVAL 30 DAY)
                 WHERE `id` = :id',
                [
                    'id' => $id,
                    'f'  => $result['ficheiro'],
                    't'  => $result['tamanho'],
                    'h'  => $result['hash'],
                ]
            );

            Audit::log('backup', 'executar', 'backups', (string) $id, null, [
                'ficheiro' => $result['ficheiro'],
                'tamanho'  => $result['tamanho'],
            ]);
            flash('success', 'Backup criado com sucesso.');
        } catch (\Throwable $e) {
            Database::execute(
                'UPDATE `backups` SET `estado` = "falhou", `mensagem` = :m WHERE `id` = :id',
                ['id' => $id, 'm' => substr($e->getMessage(), 0, 1000)]
            );
            logger('error', 'Falha no backup', ['msg' => $e->getMessage()]);
            flash('error', 'Erro ao criar backup: ' . $e->getMessage());
        }

        redirect('/backups');
    }

    // ======================================================
    // DOWNLOAD
    // ======================================================

    public function download(string $id): never
    {
        if (!Rbac::can('backup.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/backups');
        }

        $backup = Database::first(
            'SELECT * FROM `backups` WHERE `id` = :id AND `igreja_id` = :i LIMIT 1',
            ['id' => (int) $id, 'i' => Tenant::id()]
        );

        if (!$backup || $backup['estado'] !== 'concluido') {
            flash('error', 'Backup não encontrado ou não concluído.');
            redirect('/backups');
        }

        $caminho = STORAGE_PATH . '/backups/' . $backup['ficheiro'];
        if (!is_file($caminho)) {
            flash('error', 'Ficheiro não encontrado no servidor.');
            redirect('/backups');
        }

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($caminho) . '"');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
        exit;
    }

    // ======================================================
    // RESTAURAR
    // ======================================================

    public function restaurar(string $id): void
    {
        if (!Rbac::can('backup.restaurar')) {
            flash('error', 'Sem permissão para restaurar backups.');
            redirect('/backups');
        }

        $igrejaId = Tenant::id();
        $backup = Database::first(
            'SELECT * FROM `backups` WHERE `id` = :id AND `igreja_id` = :i LIMIT 1',
            ['id' => (int) $id, 'i' => $igrejaId]
        );

        if (!$backup || $backup['estado'] !== 'concluido') {
            flash('error', 'Backup não encontrado ou inválido.');
            redirect('/backups');
        }

        $caminho = STORAGE_PATH . '/backups/' . $backup['ficheiro'];
        if (!is_file($caminho)) {
            flash('error', 'Ficheiro de backup não encontrado.');
            redirect('/backups');
        }

        // Confirmação dupla
        if (($_POST['confirmar'] ?? '') !== 'RESTAURAR') {
            flash('error', 'Confirmação inválida. Escreva RESTAURAR.');
            redirect('/backups');
        }

        try {
            $this->restaurarFicheiro($caminho);
            Audit::log('backup', 'restaurar', 'backups', (string) $backup['id'], null, [
                'ficheiro' => $backup['ficheiro'],
            ]);
            flash('success', 'Backup restaurado com sucesso. Recomenda-se sair e entrar novamente.');
        } catch (\Throwable $e) {
            logger('error', 'Falha ao restaurar backup', ['msg' => $e->getMessage()]);
            flash('error', 'Erro ao restaurar: ' . $e->getMessage());
        }

        redirect('/backups');
    }

    // ======================================================
    // ELIMINAR
    // ======================================================

    public function eliminar(string $id): void
    {
        if (!Rbac::can('backup.executar')) {
            flash('error', 'Sem permissão.');
            redirect('/backups');
        }

        $backup = Database::first(
            'SELECT * FROM `backups` WHERE `id` = :id AND `igreja_id` = :i LIMIT 1',
            ['id' => (int) $id, 'i' => Tenant::id()]
        );

        if (!$backup) {
            flash('error', 'Backup não encontrado.');
            redirect('/backups');
        }

        $caminho = STORAGE_PATH . '/backups/' . $backup['ficheiro'];
        if (is_file($caminho)) {
            @unlink($caminho);
        }

        Database::execute('DELETE FROM `backups` WHERE `id` = :id', ['id' => $backup['id']]);
        Audit::log('backup', 'eliminar', 'backups', (string) $backup['id'], $backup, null);
        flash('success', 'Backup eliminado.');
        redirect('/backups');
    }

    // ======================================================
    // HELPERS
    // ======================================================

    /**
     * Gera um dump SQL APENAS dos dados da igreja (multi-tenant).
     * Filtra por igreja_id nas tabelas de negócio.
     */
    private function gerarBackup(int $igrejaId): array
    {
        $dir = STORAGE_PATH . '/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $nome = 'backup-igreja-' . $igrejaId . '-' . date('Ymd-His') . '.sql';
        $caminho = $dir . '/' . $nome;

        $out = fopen($caminho, 'w');
        if (!$out) {
            throw new \RuntimeException('Não foi possível criar o ficheiro de backup.');
        }

        fwrite($out, "-- Monana Eclesia — Backup da igreja #{$igrejaId}\n");
        fwrite($out, "-- Gerado em " . date('Y-m-d H:i:s') . "\n\n");
        fwrite($out, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($out, "START TRANSACTION;\n");
        fwrite($out, "SET time_zone = \"+00:00\";\n\n");

        $pdo = Database::connection();

        // Tabelas com igreja_id direto
        $tabelasDiretas = [
            'members', 'member_families', 'departments', 'department_members',
            'cells', 'cell_members', 'ebd_classes', 'ebd_students', 'ebd_teachers',
            'events', 'event_attendances', 'categories', 'transactions',
            'monthly_closures', 'settings',
        ];

        foreach ($tabelasDiretas as $tabela) {
            $this->dumpTabela($out, $pdo, $tabela, $igrejaId);
        }

        // Tabelas indiretas (via cell_id / class_id)
        $this->dumpIndireta($out, $pdo, 'cell_meetings', 'cell_id', 'cells', $igrejaId);
        $this->dumpIndireta($out, $pdo, 'ebd_students', 'class_id', 'ebd_classes', $igrejaId);
        $this->dumpIndireta($out, $pdo, 'ebd_teachers', 'class_id', 'ebd_classes', $igrejaId);
        $this->dumpIndireta($out, $pdo, 'event_attendances', 'event_id', 'events', $igrejaId);

        // Tabelas pivot (já incluídas acima via "tabelasDiretas" quando aplicável)
        // Nota: department_members e cell_members não têm igreja_id —
        // são filtradas pelos ids dos departamentos/células da igreja.

        fwrite($out, "\nCOMMIT;\n");
        fclose($out);

        $tamanho = filesize($caminho);
        $hash = hash_file('sha256', $caminho);

        return [
            'ficheiro' => $nome,
            'tamanho'  => $tamanho,
            'hash'     => $hash,
        ];
    }

    /**
     * Faz dump de uma tabela que tem a coluna `igreja_id`.
     */
    private function dumpTabela($out, \PDO $pdo, string $tabela, int $igrejaId): void
    {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `{$tabela}` WHERE `igreja_id` = :i");
            $stmt->execute(['i' => $igrejaId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return;
            }

            fwrite($out, "-- Tabela: {$tabela} (" . count($rows) . " linhas)\n");
            $cols = array_keys($rows[0]);
            $colsSql = '`' . implode('`,`', $cols) . '`';

            foreach ($rows as $row) {
                $vals = [];
                foreach ($row as $v) {
                    if ($v === null) {
                        $vals[] = 'NULL';
                    } elseif (is_int($v) || is_float($v)) {
                        $vals[] = (string) $v;
                    } else {
                        $vals[] = $pdo->quote((string) $v);
                    }
                }
                fwrite($out, "INSERT INTO `{$tabela}` ({$colsSql}) VALUES (" . implode(',', $vals) . ");\n");
            }
            fwrite($out, "\n");
        } catch (\Throwable $e) {
            fwrite($out, "-- [ERRO] {$tabela}: " . $e->getMessage() . "\n\n");
        }
    }

    /**
     * Dump de tabelas que dependem de outra (via FK).
     */
    private function dumpIndireta($out, \PDO $pdo, string $tabela, string $fk, string $pai, int $igrejaId): void
    {
        try {
            $sql = "SELECT * FROM `{$tabela}` WHERE `{$fk}` IN (SELECT id FROM `{$pai}` WHERE igreja_id = :i)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['i' => $igrejaId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return;
            }

            fwrite($out, "-- Tabela: {$tabela} (" . count($rows) . " linhas)\n");
            $cols = array_keys($rows[0]);
            $colsSql = '`' . implode('`,`', $cols) . '`';

            foreach ($rows as $row) {
                $vals = [];
                foreach ($row as $v) {
                    if ($v === null) {
                        $vals[] = 'NULL';
                    } elseif (is_int($v) || is_float($v)) {
                        $vals[] = (string) $v;
                    } else {
                        $vals[] = $pdo->quote((string) $v);
                    }
                }
                fwrite($out, "INSERT INTO `{$tabela}` ({$colsSql}) VALUES (" . implode(',', $vals) . ");\n");
            }
            fwrite($out, "\n");
        } catch (\Throwable $e) {
            fwrite($out, "-- [ERRO] {$tabela}: " . $e->getMessage() . "\n\n");
        }
    }

    /**
     * Executa um ficheiro .sql no banco de dados.
     */
    private function restaurarFicheiro(string $caminho): void
    {
        $conteudo = file_get_contents($caminho);
        if ($conteudo === false) {
            throw new \RuntimeException('Não foi possível ler o ficheiro.');
        }

        $pdo = Database::connection();

        // Remove comentários linha-a-linha e divide por ;
        $linhas = preg_split("/\r\n|\n|\r/", $conteudo);
        $stmtBuffer = '';

        foreach ($linhas as $linha) {
            $trim = trim($linha);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }
            $stmtBuffer .= $linha . "\n";
            if (str_ends_with(rtrim($trim), ';')) {
                try {
                    $pdo->exec($stmtBuffer);
                } catch (\Throwable $e) {
                    logger('warning', 'Restauro: falha em statement', [
                        'msg' => $e->getMessage(),
                        'sql' => substr($stmtBuffer, 0, 300),
                    ]);
                }
                $stmtBuffer = '';
            }
        }
    }
}