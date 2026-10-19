<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Rbac;
use App\Core\Tenant;
use App\Core\View;

final class AuditoriaController extends Controller
{
    public function index(): void
    {
        if (!Rbac::can('auditoria.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();

        // ------------------------------------------------------
        // Filtros
        // ------------------------------------------------------
        $modulo = trim((string) ($_GET['modulo'] ?? ''));
        $acao   = trim((string) ($_GET['acao'] ?? ''));
        $de     = (string) ($_GET['de'] ?? '');
        $ate    = (string) ($_GET['ate'] ?? '');
        $q      = trim((string) ($_GET['q'] ?? ''));

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 30;
        $offset  = ($page - 1) * $perPage;

        // ------------------------------------------------------
        // WHERE com prefixo `a.` (audit_logs)
        // ------------------------------------------------------
        $where  = ['a.igreja_id = :i'];
        $params = ['i' => $igrejaId];

        if ($modulo !== '') {
            $where[] = 'a.modulo = :modulo';
            $params['modulo'] = $modulo;
        }
        if ($acao !== '') {
            $where[] = 'a.acao = :acao';
            $params['acao'] = $acao;
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

        // ------------------------------------------------------
        // Total
        // ------------------------------------------------------
        $total = (int) (Database::first(
            "SELECT COUNT(*) AS n FROM `audit_logs` a WHERE {$whereSql}",
            $params
        )['n'] ?? 0);

        // ------------------------------------------------------
        // Página atual
        // ------------------------------------------------------
        $logs = Database::select(
            "SELECT a.*,
                    u.`nome` AS user_nome,
                    u.`email` AS user_email
             FROM `audit_logs` a
             LEFT JOIN `users` u ON u.id = a.user_id
             WHERE {$whereSql}
             ORDER BY a.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $pages = max(1, (int) ceil($total / $perPage));

        // ------------------------------------------------------
        // Listas para os dropdowns de filtro
        // ------------------------------------------------------
        $modulos = Database::select(
            'SELECT DISTINCT a.`modulo`
             FROM `audit_logs` a
             WHERE a.`igreja_id` = :i
             ORDER BY a.`modulo`',
            ['i' => $igrejaId]
        );

        $acoes = Database::select(
            'SELECT DISTINCT a.`acao`
             FROM `audit_logs` a
             WHERE a.`igreja_id` = :i
             ORDER BY a.`acao`',
            ['i' => $igrejaId]
        );

        // ------------------------------------------------------
        // Render
        // ------------------------------------------------------
        View::render('auditoria/index', [
            'title'   => 'Auditoria — Monana Eclesia',
            'user'    => Auth::user(),
            'logs'    => $logs,
            'total'   => $total,
            'page'    => $page,
            'pages'   => $pages,
            'perPage' => $perPage,
            'modulo'  => $modulo,
            'acao'    => $acao,
            'de'      => $de,
            'ate'     => $ate,
            'q'       => $q,
            'modulos' => $modulos,
            'acoes'   => $acoes,
        ], 'layouts/app');
    }
}