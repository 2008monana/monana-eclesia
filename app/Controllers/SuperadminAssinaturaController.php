<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Core\View;

final class SuperadminAssinaturaController extends Controller
{
    // ======================================================
    // LISTA
    // ======================================================

    public function index(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $q      = trim((string) ($_GET['q'] ?? ''));
        $status = (string) ($_GET['status'] ?? '');
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $where  = ['1=1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(i.nome LIKE :q OR i.slug LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($status !== '') {
            $where[] = 's.status = :st';
            $params['st'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::first(
            "SELECT COUNT(*) AS n FROM `subscriptions` s
             JOIN `igrejas` i ON i.id = s.igreja_id
             WHERE {$whereSql}",
            $params
        )['n'] ?? 0);

        $assinaturas = Database::select(
            "SELECT s.*, i.nome AS igreja_nome, i.slug AS igreja_slug, p.nome AS plano_nome
             FROM `subscriptions` s
             JOIN `igrejas` i ON i.id = s.igreja_id
             JOIN `planos` p ON p.id = s.plano_id
             WHERE {$whereSql}
             ORDER BY s.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $pages = max(1, (int) ceil($total / $perPage));

        View::render('superadmin/assinaturas/index', [
            'title'       => 'Assinaturas — Superadmin',
            'supervisor'  => $supervisor,
            'assinaturas' => $assinaturas,
            'q'           => $q,
            'status'      => $status,
            'total'       => $total,
            'page'        => $page,
            'pages'       => $pages,
            'perPage'     => $perPage,
        ], 'layouts/superadmin');
    }

    // ======================================================
    // CRIAR (manual)
    // ======================================================

    public function create(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $igrejas = Database::select(
            'SELECT id, nome, slug FROM `igrejas`
             WHERE deletado_em IS NULL AND status IN ("em_teste","activa")
             ORDER BY nome'
        );

        $planos = Database::select('SELECT * FROM `planos` WHERE ativo = 1 ORDER BY ordem');

        View::render('superadmin/assinaturas/form', [
            'title'      => 'Nova Assinatura — Superadmin',
            'supervisor' => $supervisor,
            'igrejas'    => $igrejas,
            'planos'     => $planos,
            'errors'     => [],
        ], 'layouts/superadmin');
    }

    public function store(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $igrejaId = (int) ($_POST['igreja_id'] ?? 0);
        $planoId  = (int) ($_POST['plano_id'] ?? 0);
        $dias     = (int) ($_POST['dias'] ?? 30);

        $v = new Validator(['igreja_id' => $igrejaId, 'plano_id' => $planoId]);
        $v->required('igreja_id', 'Igreja')->required('plano_id', 'Plano');

        if ($v->fails() || $igrejaId <= 0 || $planoId <= 0) {
            flash('error', 'Escolha igreja e plano.');
            redirect('/superadmin/assinaturas/criar');
        }

        $igreja = Database::first('SELECT * FROM `igrejas` WHERE id = :id AND deletado_em IS NULL LIMIT 1',
            ['id' => $igrejaId]);
        $plano = Database::first('SELECT * FROM `planos` WHERE id = :id LIMIT 1', ['id' => $planoId]);

        if (!$igreja || !$plano) {
            flash('error', 'Igreja ou plano inválidos.');
            redirect('/superadmin/assinaturas/criar');
        }

        if ($dias <= 0) {
            $dias = (int) $plano['periodicidade_dias'] ?: 30;
        }

        try {
            $id = Database::insert(
                'INSERT INTO `subscriptions`
                    (`igreja_id`, `plano_id`, `inicio`, `expiracao`, `status`, `renovacao_automatica`)
                 VALUES (:i, :p, CURDATE(), DATE_ADD(CURDATE(), INTERVAL :d DAY), "activa", 0)',
                ['i' => $igrejaId, 'p' => $planoId, 'd' => $dias]
            );

            // Atualiza plano da igreja
            Database::execute('UPDATE `igrejas` SET `plano_id` = :p WHERE `id` = :i',
                ['p' => $planoId, 'i' => $igrejaId]);

            Audit::log('superadmin', 'criar_assinatura', 'subscriptions', (string) $id, null, [
                'igreja_id' => $igrejaId, 'plano_id' => $planoId, 'dias' => $dias,
            ]);

            flash('success', 'Assinatura criada e ativa.');
            redirect('/superadmin/assinaturas');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/superadmin/assinaturas/criar');
        }
    }

    // ======================================================
    // DETALHES
    // ======================================================

    public function show(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $assinatura = Database::first(
            'SELECT s.*, i.nome AS igreja_nome, i.slug AS igreja_slug, p.nome AS plano_nome, p.preco_kz
             FROM `subscriptions` s
             JOIN `igrejas` i ON i.id = s.igreja_id
             JOIN `planos` p ON p.id = s.plano_id
             WHERE s.id = :id LIMIT 1',
            ['id' => (int) $id]
        );

        if (!$assinatura) {
            flash('error', 'Assinatura não encontrada.');
            redirect('/superadmin/assinaturas');
        }

        $pagamentos = Database::select(
            'SELECT * FROM `payments` WHERE subscription_id = :s ORDER BY id DESC',
            ['s' => $assinatura['id']]
        );

        $notificacoes = Database::select(
            'SELECT * FROM `subscription_notifications` WHERE subscription_id = :s ORDER BY id DESC LIMIT 20',
            ['s' => $assinatura['id']]
        );

        View::render('superadmin/assinaturas/show', [
            'title'        => 'Assinatura #' . $assinatura['id'] . ' — Superadmin',
            'supervisor'   => $supervisor,
            'assinatura'   => $assinatura,
            'pagamentos'   => $pagamentos,
            'notificacoes' => $notificacoes,
        ], 'layouts/superadmin');
    }

    // ======================================================
    // AÇÕES
    // ======================================================

    public function renovar(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $assinatura = $this->buscar((int) $id);
        if (!$assinatura) {
            flash('error', 'Assinatura não encontrada.');
            redirect('/superadmin/assinaturas');
        }

        $plano = Database::first('SELECT * FROM `planos` WHERE id = :id LIMIT 1', ['id' => $assinatura['plano_id']]);
        $dias = (int) ($plano['periodicidade_dias'] ?? 30) ?: 30;

        $base = $assinatura['expiracao'] && strtotime($assinatura['expiracao']) > time()
            ? $assinatura['expiracao']
            : date('Y-m-d');

        Database::execute(
            'UPDATE `subscriptions` SET
                `expiracao` = DATE_ADD(:base, INTERVAL :d DAY),
                `status` = "activa",
                `actualizado_em` = NOW()
             WHERE `id` = :id',
            ['id' => $assinatura['id'], 'base' => $base, 'd' => $dias]
        );

        Audit::log('superadmin', 'renovar_assinatura', 'subscriptions', (string) $assinatura['id'],
            $assinatura, ['expiracao_base' => $base, 'dias' => $dias]);

        flash('success', "Assinatura renovada por {$dias} dias.");
        redirect('/superadmin/assinaturas/' . $assinatura['id']);
    }

    public function suspender(string $id): void
    {
        $this->alterarStatus((int) $id, 'suspensa', 'Assinatura suspensa.');
    }

    public function reativar(string $id): void
    {
        $this->alterarStatus((int) $id, 'activa', 'Assinatura reativada.');
    }

    public function cancelar(string $id): void
    {
        $this->alterarStatus((int) $id, 'cancelada', 'Assinatura cancelada.');
    }

    private function alterarStatus(int $id, string $novoStatus, string $msg): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $assinatura = $this->buscar($id);
        if (!$assinatura) {
            flash('error', 'Assinatura não encontrada.');
            redirect('/superadmin/assinaturas');
        }

        Database::execute(
            'UPDATE `subscriptions` SET `status` = :s WHERE `id` = :id',
            ['s' => $novoStatus, 'id' => $assinatura['id']]
        );

        Audit::log('superadmin', 'alterar_status_assinatura', 'subscriptions', (string) $assinatura['id'],
            ['status_anterior' => $assinatura['status']], ['status' => $novoStatus]);

        flash('success', $msg);
        redirect('/superadmin/assinaturas/' . $assinatura['id']);
    }

    private function buscar(int $id): ?array
    {
        return Database::first('SELECT * FROM `subscriptions` WHERE id = :id LIMIT 1', ['id' => $id]);
    }
}