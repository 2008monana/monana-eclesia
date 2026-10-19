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

final class AssinaturaController extends Controller
{
    public function index(): void
    {
        if (!Rbac::can('assinaturas.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();

        // Assinatura ativa
        $assinatura = Database::first(
            'SELECT s.*, p.nome AS plano_nome, p.descricao AS plano_desc,
                    p.preco_kz, p.periodicidade_dias,
                    p.limite_membros, p.limite_users, p.limite_storage_mb
             FROM `subscriptions` s
             JOIN `planos` p ON p.id = s.plano_id
             WHERE s.igreja_id = :i
             ORDER BY s.id DESC LIMIT 1',
            ['i' => $igrejaId]
        );

        // Histórico de pagamentos
        $pagamentos = Database::select(
            'SELECT pg.*, s.plano_id, p.nome AS plano_nome
             FROM `payments` pg
             JOIN `subscriptions` s ON s.id = pg.subscription_id
             JOIN `planos` p ON p.id = s.plano_id
             WHERE pg.igreja_id = :i
             ORDER BY pg.id DESC',
            ['i' => $igrejaId]
        );

        // Planos disponíveis
        $planos = Database::select(
            'SELECT * FROM `planos` WHERE ativo = 1 ORDER BY ordem'
        );

        // Uso atual (para mostrar limites)
        $uso = [
            'membros' => (int) (Database::first(
                'SELECT COUNT(*) AS n FROM `members` WHERE igreja_id = :i AND deletado_em IS NULL',
                ['i' => $igrejaId]
            )['n'] ?? 0),
            'users' => (int) (Database::first(
                'SELECT COUNT(*) AS n FROM `users` WHERE igreja_id = :i AND deletado_em IS NULL',
                ['i' => $igrejaId]
            )['n'] ?? 0),
        ];

        View::render('assinatura/index', [
            'title'       => 'Assinatura — Monana Eclesia',
            'user'        => Auth::user(),
            'assinatura'  => $assinatura,
            'pagamentos'  => $pagamentos,
            'planos'      => $planos,
            'uso'         => $uso,
        ], 'layouts/app');
    }

    public function pagar(): void
    {
        if (!Rbac::can('assinaturas.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/assinatura');
        }

        $igrejaId = Tenant::id();

        $assinatura = Database::first(
            'SELECT * FROM `subscriptions` WHERE igreja_id = :i ORDER BY id DESC LIMIT 1',
            ['i' => $igrejaId]
        );
        if (!$assinatura) {
            flash('error', 'Sem assinatura ativa. Contacte o suporte.');
            redirect('/assinatura');
        }

        $valor = (float) str_replace(',', '.', (string) ($_POST['valor_kz'] ?? 0));
        $metodo = (string) ($_POST['metodo'] ?? '');
        $referencia = trim((string) ($_POST['referencia'] ?? ''));

        if ($valor <= 0 || !in_array($metodo, ['transferencia','multicaixa','deposito','dinheiro','outro'], true)) {
            flash('error', 'Preencha o valor e o método.');
            redirect('/assinatura');
        }

        // Upload do comprovativo (opcional mas recomendado)
        $comprovativoPath = null;
        if (!empty($_FILES['comprovativo']) && $_FILES['comprovativo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['comprovativo'];
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'application/pdf' => 'pdf'];
            $mime = mime_content_type($file['tmp_name']);
            if (isset($allowed[$mime]) && $file['size'] <= 5 * 1024 * 1024) {
                $ext = $allowed[$mime];
                $nome = 'comp-' . $igrejaId . '-' . time() . '.' . $ext;
                $dest = PUBLIC_PATH . '/uploads/comprovativos/' . $nome;
                if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0775, true);
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $comprovativoPath = 'comprovativos/' . $nome;
                }
            }
        }

        Database::insert(
            'INSERT INTO `payments`
                (`subscription_id`, `igreja_id`, `valor_kz`, `metodo`, `referencia`,
                 `comprovativo`, `estado`, `pago_em`)
             VALUES (:s, :i, :v, :m, :r, :c, "pendente", NOW())',
            [
                's' => $assinatura['id'],
                'i' => $igrejaId,
                'v' => $valor,
                'm' => $metodo,
                'r' => $referencia ?: null,
                'c' => $comprovativoPath,
            ]
        );

        Audit::log('assinatura', 'registar_pagamento', 'payments', null, null, [
            'valor' => $valor, 'metodo' => $metodo,
        ]);

        flash('success', 'Pagamento registado. Aguarda confirmação do superadmin.');
        redirect('/assinatura');
    }
}