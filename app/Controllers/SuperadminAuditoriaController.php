<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

final class SuperadminAuditoriaController extends Controller
{
    public function index(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $modulo     = trim((string) ($_GET['modulo'] ?? ''));
        $acao       = trim((string) ($_GET['acao'] ?? ''));
        $igrejaId   = (int) ($_GET['igreja'] ?? 0);
        $supervisorId = (int) ($_GET['supervisor'] ?? 0);
        $de         = (string) ($_GET['de'] ?? '');
        $ate        = (string) ($_GET['ate'] ?? '');
        $q          = trim((string) ($_GET['q'] ?? ''));
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $perPage    = 40;
        $offset     = ($page - 1) * $perPage;

        $where  = ['1=1'];
        $params = [];

        if ($modulo !== '') {
            $where[] = 'a.modulo = :modulo';
            $params['modulo'] = $modulo;
        }
        if ($acao !== '') {
            $where[] = 'a.acao = :acao';
            $params['acao'] = $acao;
        }
        if ($igrejaId > 0) {
            $where[] = 'a.igreja_id = :ig';
            $params['ig'] = $igrejaId;
        }
        if ($supervisorId > 0) {
            $where[] = 'a.supervisor_id = :sup';
            $params['sup'] = $supervisorId;
        }
        if ($de !== '' && strtotime($de)) {
            $where[] = 'a.criado_em >= :de';
            $params['de'] = $de . ' 00:00:00';
        }
        if ($ate !== '' && strtotime($ate)) {
            $where[] = 'a.criado_em <= :ate';
            $params['ate'] = $ate . ' 23:59:59';
        }
        if ($q !== '') {
            $where[] = '(a.entidade LIKE :q OR a.entidade_id LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::first(
            "SELECT COUNT(*) AS n FROM `audit_logs` a WHERE {$whereSql}",
            $params
        )['n'] ?? 0);

        $logs = Database::select(
            "SELECT a.*,
                    u.nome AS user_nome,
                    s.nome AS supervisor_nome,
                    i.nome AS igreja_nome
             FROM `audit_logs` a
             LEFT JOIN `users` u ON u.id = a.user_id
             LEFT JOIN `supervisores` s ON s.id = a.supervisor_id
             LEFT JOIN `igrejas` i ON i.id = a.igreja_id
             WHERE {$whereSql}
             ORDER BY a.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $pages = max(1, (int) ceil($total / $perPage));

        // Listas para filtros
        $modulos = Database::select('SELECT DISTINCT modulo FROM `audit_logs` ORDER BY modulo');
        $acoes   = Database::select('SELECT DISTINCT acao FROM `audit_logs` ORDER BY acao');
        $igrejas = Database::select('SELECT id, nome FROM `igrejas` WHERE deletado_em IS NULL ORDER BY nome');
        $supervisores = Database::select('SELECT id, nome FROM `supervisores` ORDER BY nome');

        View::render('superadmin/auditoria/index', [
            'title'         => 'Auditoria Global — Superadmin',
            'supervisor'    => $supervisor,
            'logs'          => $logs,
            'total'         => $total,
            'page'          => $page,
            'pages'         => $pages,
            'perPage'       => $perPage,
            'modulo'        => $modulo,
            'acao'          => $acao,
            'igrejaId'      => $igrejaId,
            'supervisorId'  => $supervisorId,
            'de'            => $de,
            'ate'           => $ate,
            'q'             => $q,
            'modulos'       => $modulos,
            'acoes'         => $acoes,
            'igrejas'       => $igrejas,
            'supervisores'  => $supervisores,
        ], 'layouts/superadmin');
    }

    public function exportar(): never
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        // Mesmos filtros (simplificado)
        $modulo   = trim((string) ($_GET['modulo'] ?? ''));
        $acao     = trim((string) ($_GET['acao'] ?? ''));
        $igrejaId = (int) ($_GET['igreja'] ?? 0);
        $de       = (string) ($_GET['de'] ?? '');
        $ate      = (string) ($_GET['ate'] ?? '');

        $where  = ['1=1'];
        $params = [];

        if ($modulo !== '') { $where[] = 'a.modulo = :modulo'; $params['modulo'] = $modulo; }
        if ($acao !== '')   { $where[] = 'a.acao = :acao';     $params['acao'] = $acao; }
        if ($igrejaId > 0)  { $where[] = 'a.igreja_id = :ig';  $params['ig'] = $igrejaId; }
        if ($de !== '' && strtotime($de)) { $where[] = 'a.criado_em >= :de';  $params['de'] = $de . ' 00:00:00'; }
        if ($ate !== '' && strtotime($ate)) { $where[] = 'a.criado_em <= :ate'; $params['ate'] = $ate . ' 23:59:59'; }

        $whereSql = implode(' AND ', $where);

        $logs = Database::select(
            "SELECT a.criado_em, a.modulo, a.acao, a.entidade, a.entidade_id,
                    a.ip, a.user_agent,
                    u.nome AS user_nome, s.nome AS supervisor_nome, i.nome AS igreja_nome
             FROM `audit_logs` a
             LEFT JOIN `users` u ON u.id = a.user_id
             LEFT JOIN `supervisores` s ON s.id = a.supervisor_id
             LEFT JOIN `igrejas` i ON i.id = a.igreja_id
             WHERE {$whereSql}
             ORDER BY a.id DESC
             LIMIT 10000",
            $params
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="auditoria-global-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['Data','Módulo','Ação','Entidade','ID','Igreja','Utilizador','Supervisor','IP','User-Agent'], ';');
        foreach ($logs as $l) {
            fputcsv($out, [
                $l['criado_em'],
                $l['modulo'],
                $l['acao'],
                $l['entidade'] ?? '',
                $l['entidade_id'] ?? '',
                $l['igreja_nome'] ?? '',
                $l['user_nome'] ?? '',
                $l['supervisor_nome'] ?? '',
                $l['ip'] ?? '',
                $l['user_agent'] ?? '',
            ], ';');
        }

        fclose($out);
        exit;
    }
}