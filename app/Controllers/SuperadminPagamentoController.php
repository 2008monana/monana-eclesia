<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Subscription;
use App\Core\View;

final class SuperadminPagamentoController extends Controller
{
    public function index(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $estado = (string) ($_GET['estado'] ?? '');
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $where  = ['1=1'];
        $params = [];

        if ($estado !== '' && in_array($estado, ['pendente','confirmado','rejeitado','reembolsado'], true)) {
            $where[] = 'p.estado = :e';
            $params['e'] = $estado;
        }
        if (!empty($_GET['assinatura'])) {
            $where[] = 'p.subscription_id = :s';
            $params['s'] = (int) $_GET['assinatura'];
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::first(
            "SELECT COUNT(*) AS n FROM `payments` p WHERE {$whereSql}",
            $params
        )['n'] ?? 0);

        $pagamentos = Database::select(
            "SELECT p.*, i.nome AS igreja_nome, pl.nome AS plano_nome, s.nome AS supervisor_nome
             FROM `payments` p             JOIN `igrejas` i ON i.id = p.igreja_id
             LEFT JOIN `subscriptions` sub ON sub.id = p.subscription_id
             LEFT JOIN `planos` pl ON pl.id = sub.plano_id
             LEFT JOIN `supervisores` s ON s.id = p.confirmado_por
             WHERE {$whereSql}
             ORDER BY p.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $pages = max(1, (int) ceil($total / $perPage));

        View::render('superadmin/pagamentos/index', [
            'title'      => 'Pagamentos — Superadmin',
            'supervisor' => $supervisor,
            'pagamentos' => $pagamentos,
            'estado'     => $estado,
            'total'      => $total,
            'page'       => $page,
            'pages'      => $pages,
            'perPage'    => $perPage,
        ], 'layouts/superadmin');
    }

    public function show(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $pagamento = Database::first(
            'SELECT p.*, i.nome AS igreja_nome, i.slug AS igreja_slug,
                    pl.nome AS plano_nome, pl.preco_kz,
                    s.nome AS supervisor_nome, s.email AS supervisor_email
             FROM `payments` p
             JOIN `igrejas` i ON i.id = p.igreja_id
             LEFT JOIN `subscriptions` sub ON sub.id = p.subscription_id
             LEFT JOIN `planos` pl ON pl.id = sub.plano_id
             LEFT JOIN `supervisores` s ON s.id = p.confirmado_por
             WHERE p.id = :id LIMIT 1',
            ['id' => (int) $id]
        );

        if (!$pagamento) {
            flash('error', 'Pagamento não encontrado.');
            redirect('/superadmin/pagamentos');
        }

        View::render('superadmin/pagamentos/show', [
            'title'      => 'Pagamento #' . $pagamento['id'] . ' — Superadmin',
            'supervisor' => $supervisor,
            'pagamento'  => $pagamento,
        ], 'layouts/superadmin');
    }

    public function confirmar(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $pagamento = Database::first('SELECT * FROM `payments` WHERE id = :id LIMIT 1', ['id' => (int) $id]);
        if (!$pagamento) {
            flash('error', 'Pagamento não encontrado.');
            redirect('/superadmin/pagamentos');
        }

        if ($pagamento['estado'] === 'confirmado') {
            flash('info', 'Este pagamento já estava confirmado.');
            redirect('/superadmin/pagamentos/' . $pagamento['id']);
        }

        Database::transaction(function () use ($pagamento, $supervisor) {
            // 1. Marca pagamento como confirmado
            Database::execute(
                'UPDATE `payments` SET `estado` = "confirmado",
                    `pago_em` = NOW(), `confirmado_por` = :s
                 WHERE `id` = :id',
                ['s' => $supervisor['id'], 'id' => $pagamento['id']]
            );

            // 2. Renova a assinatura
            $assinatura = Database::first(
                'SELECT * FROM `subscriptions` WHERE id = :id LIMIT 1',
                ['id' => $pagamento['subscription_id']]
            );
            if (!$assinatura) return;

            $plano = Database::first('SELECT * FROM `planos` WHERE id = :id LIMIT 1', ['id' => $assinatura['plano_id']]);
            $dias = (int) ($plano['periodicidade_dias'] ?? 30) ?: 30;

            $base = $assinatura['expiracao'] && strtotime($assinatura['expiracao']) > time()
                ? $assinatura['expiracao']
                : date('Y-m-d');

            Database::execute(
                'UPDATE `subscriptions` SET
                    `expiracao` = DATE_ADD(:base, INTERVAL :d DAY),
                    `status` = "activa"
                 WHERE `id` = :id',
                ['id' => $assinatura['id'], 'base' => $base, 'd' => $dias]
            );

            // 3. Atualiza plano da igreja
            Database::execute(
                'UPDATE `igrejas` SET `plano_id` = :p WHERE `id` = :i',
                ['p' => $assinatura['plano_id'], 'i' => $pagamento['igreja_id']]
            );

            Subscription::clearCache();
        });

        Audit::log('superadmin', 'confirmar_pagamento', 'payments', (string) $pagamento['id'],
            $pagamento, ['estado' => 'confirmado']);

        flash('success', 'Pagamento confirmado. Assinatura renovada.');
        redirect('/superadmin/pagamentos/' . $pagamento['id']);
    }

    public function rejeitar(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $pagamento = Database::first('SELECT * FROM `payments` WHERE id = :id LIMIT 1', ['id' => (int) $id]);
        if (!$pagamento) {
            flash('error', 'Pagamento não encontrado.');
            redirect('/superadmin/pagamentos');
        }

        $motivo = trim((string) ($_POST['motivo'] ?? ''));

        Database::execute(
            'UPDATE `payments` SET `estado` = "rejeitado" WHERE `id` = :id',
            ['id' => $pagamento['id']]
        );

        Audit::log('superadmin', 'rejeitar_pagamento', 'payments', (string) $pagamento['id'],
            $pagamento, ['estado' => 'rejeitado', 'motivo' => $motivo]);

        flash('success', 'Pagamento rejeitado.');
        redirect('/superadmin/pagamentos/' . $pagamento['id']);
    }
}