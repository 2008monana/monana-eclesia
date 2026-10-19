<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Mailer;
use App\Core\Validator;
use App\Core\View;

final class SuperadminIgrejaController extends Controller
{
    // ======================================================
    // LISTA
    // ======================================================

    public function index(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $q        = trim((string) ($_GET['q'] ?? ''));
        $status   = (string) ($_GET['status'] ?? '');
        $planoId  = (int) ($_GET['plano'] ?? 0);
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $perPage  = 20;
        $offset   = ($page - 1) * $perPage;

        $where  = ['i.deletado_em IS NULL'];
        $params = [];

        if ($q !== '') {
            $where[] = '(i.nome LIKE :q OR i.email LIKE :q OR i.slug LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($status !== '' && in_array($status, ['em_teste','activa','suspensa','cancelada'], true)) {
            $where[] = 'i.status = :st';
            $params['st'] = $status;
        }
        if ($planoId > 0) {
            $where[] = 'i.plano_id = :pl';
            $params['pl'] = $planoId;
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::first(
            "SELECT COUNT(*) AS n FROM `igrejas` i WHERE {$whereSql}",
            $params
        )['n'] ?? 0);

        $igrejas = Database::select(
            "SELECT i.*, p.nome AS plano_nome,
                    (SELECT COUNT(*) FROM `members` m WHERE m.igreja_id = i.id AND m.deletado_em IS NULL) AS total_membros,
                    (SELECT COUNT(*) FROM `users` u WHERE u.igreja_id = i.id AND u.deletado_em IS NULL) AS total_users,
                    s.status AS sub_status, s.expiracao AS sub_expiracao
             FROM `igrejas` i
             LEFT JOIN `planos` p ON p.id = i.plano_id
             LEFT JOIN `subscriptions` s ON s.igreja_id = i.id AND s.id = (
                SELECT MAX(id) FROM `subscriptions` WHERE igreja_id = i.id
             )
             WHERE {$whereSql}
             ORDER BY i.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $pages = max(1, (int) ceil($total / $perPage));

        $planos = Database::select('SELECT id, nome FROM `planos` WHERE ativo = 1 ORDER BY ordem');

        View::render('superadmin/igrejas/index', [
            'title'      => 'Igrejas — Superadmin',
            'supervisor' => $supervisor,
            'igrejas'    => $igrejas,
            'planos'     => $planos,
            'q'          => $q,
            'status'     => $status,
            'planoId'    => $planoId,
            'total'      => $total,
            'page'       => $page,
            'pages'      => $pages,
            'perPage'    => $perPage,
        ], 'layouts/superadmin');
    }

    // ======================================================
    // CRIAR — FORMULÁRIO
    // ======================================================

    public function create(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $planos = Database::select('SELECT * FROM `planos` WHERE ativo = 1 ORDER BY ordem');

        View::render('superadmin/igrejas/form', [
            'title'      => 'Nova Igreja — Superadmin',
            'supervisor' => $supervisor,
            'igreja'     => [],
            'planos'     => $planos,
            'isCreate'   => true,
            'errors'     => [],
        ], 'layouts/superadmin');
    }

    // ======================================================
    // CRIAR — GUARDAR (com onboarding)
    // ======================================================

    public function store(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->required('email', 'E-mail')
          ->required('slug', 'Slug')
          ->email('email', 'E-mail')
          ->maxLen('nome', 180, 'Nome')
          ->maxLen('slug', 180, 'Slug');

        // Slug único
        $slugExiste = Database::first(
            'SELECT id FROM `igrejas` WHERE `slug` = :s LIMIT 1',
            ['s' => $data['slug']]
        );
        if ($slugExiste) {
            $v->required('__dup', 'Dup');
            $GLOBALS['__extra_errors'] = ['slug' => 'Este slug já está em uso.'];
        }

        // Email único
        $emailExiste = Database::first(
            'SELECT id FROM `igrejas` WHERE LOWER(`email`) = :e AND `deletado_em` IS NULL LIMIT 1',
            ['e' => strtolower($data['email'])]
        );
        if ($emailExiste) {
            $v->required('__dup2', 'Dup');
            $GLOBALS['__extra_errors'] = ['email' => 'Este e-mail já está registado noutra igreja.'];
        }

        if ($v->fails()) {
            $errors = array_merge($v->errors(), $GLOBALS['__extra_errors'] ?? []);
            unset($GLOBALS['__extra_errors']);
            $planos = Database::select('SELECT * FROM `planos` WHERE ativo = 1 ORDER BY ordem');
            View::render('superadmin/igrejas/form', [
                'title'      => 'Nova Igreja — Superadmin',
                'supervisor' => $supervisor,
                'igreja'     => $data,
                'planos'     => $planos,
                'isCreate'   => true,
                'errors'     => $errors,
            ], 'layouts/superadmin');
            return;
        }

        // Verifica se o admin já existe (impede duplicado)
        $adminEmail = strtolower($data['admin_email'] ?: $data['email']);

        try {
            $igrejaId = Database::transaction(function ($pdo) use ($data, $supervisor, $adminEmail) {
                // 1. Cria a igreja
                $igrejaId = Database::insert(
                    'INSERT INTO `igrejas`
                        (`nome`, `slug`, `nif`, `email`, `telefone`, `endereco`,
                         `cidade`, `provincia`, `pais`, `fuso_horario`, `moeda`,
                         `plano_id`, `status`, `criado_por`)
                     VALUES (:nome, :slug, :nif, :email, :tel, :end, :cid, :prov,
                             :pais, :fuso, :moeda, :plano, :status, :criado)',
                    [
                        'nome'   => $data['nome'],
                        'slug'   => $data['slug'],
                        'nif'    => $data['nif'] ?: null,
                        'email'  => strtolower($data['email']),
                        'tel'    => $data['telefone'] ?: null,
                        'end'    => $data['endereco'] ?: null,
                        'cid'    => $data['cidade'] ?: null,
                        'prov'   => $data['provincia'] ?: null,
                        'pais'   => $data['pais'] ?: 'Angola',
                        'fuso'   => $data['fuso_horario'] ?: 'Africa/Luanda',
                        'moeda'  => $data['moeda'] ?: 'AOA',
                        'plano'  => $data['plano_id'] ?: null,
                        'status' => 'em_teste',
                        'criado' => $supervisor['id'],
                    ]
                );

                // 2. Cria assinatura trial (30 dias)
                if ($data['plano_id']) {
                    Database::insert(
                        'INSERT INTO `subscriptions`
                            (`igreja_id`, `plano_id`, `inicio`, `expiracao`, `status`, `renovacao_automatica`)
                         VALUES (:i, :p, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), "trial", 0)',
                        ['i' => $igrejaId, 'p' => (int) $data['plano_id']]
                    );
                }

                // 3. Cria o admin
                $senhaTemp = $this->gerarSenhaTemp();
                $adminNome = $data['admin_nome'] ?: 'Administrador';
                $userId = Database::insert(
                    'INSERT INTO `users`
                        (`igreja_id`, `nome`, `email`, `senha_hash`, `ativo`, `must_change_password`)
                     VALUES (:i, :nome, :email, :hash, 1, 1)',
                    [
                        'i'     => $igrejaId,
                        'nome'  => $adminNome,
                        'email' => $adminEmail,
                        'hash'  => password_hash($senhaTemp, PASSWORD_ARGON2ID),
                    ]
                );

                // 4. Perfil
                Database::execute(
                    'INSERT INTO `user_profiles` (`user_id`) VALUES (:id)
                     ON DUPLICATE KEY UPDATE `user_id` = VALUES(`user_id`)',
                    ['id' => $userId]
                );

                // 5. Atribui role admin_igreja
                $roleId = Database::first('SELECT id FROM `roles` WHERE slug = "admin_igreja" LIMIT 1');
                if ($roleId) {
                    Database::execute(
                        'INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES (:u, :r)',
                        ['u' => $userId, 'r' => $roleId['id']]
                    );
                }

                // 6. Audit
                Audit::log('superadmin', 'criar_igreja', 'igrejas', (string) $igrejaId, null, $data);

                return [
                    'igreja_id'  => $igrejaId,
                    'user_id'    => $userId,
                    'admin_email'=> $adminEmail,
                    'admin_nome' => $adminNome,
                    'senha_temp' => $senhaTemp,
                    'slug'       => $data['slug'],
                ];
            });

            // 7. Envia email de onboarding (fora da transação)
            $this->enviarCredenciais(
                $result['admin_email'],
                $result['admin_nome'],
                $result['slug'],
                $result['senha_temp']
            );

            flash('success', 'Igreja criada com sucesso. Credenciais enviadas para ' . $result['admin_email']);
            redirect('/superadmin/igrejas/' . $result['igreja_id']);
        } catch (\Throwable $e) {
            logger('error', 'Erro ao criar igreja', ['msg' => $e->getMessage()]);
            flash('error', 'Erro ao criar igreja: ' . $e->getMessage());
            redirect('/superadmin/igrejas/criar');
        }
    }

    // ======================================================
    // DETALHES
    // ======================================================

    public function show(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $igreja = Database::first(
            'SELECT i.*, p.nome AS plano_nome, p.preco_kz
             FROM `igrejas` i
             LEFT JOIN `planos` p ON p.id = i.plano_id
             WHERE i.id = :id AND i.deletado_em IS NULL LIMIT 1',
            ['id' => (int) $id]
        );

        if (!$igreja) {
            flash('error', 'Igreja não encontrada.');
            redirect('/superadmin/igrejas');
        }

        // Assinatura atual
        $assinatura = Database::first(
            'SELECT s.*, p.nome AS plano_nome
             FROM `subscriptions` s
             JOIN `planos` p ON p.id = s.plano_id
             WHERE s.igreja_id = :i
             ORDER BY s.id DESC LIMIT 1',
            ['i' => $igreja['id']]
        );

        // Estatísticas da igreja
        $stats = [
            'membros' => (int) (Database::first(
                'SELECT COUNT(*) AS n FROM `members` WHERE igreja_id = :i AND deletado_em IS NULL',
                ['i' => $igreja['id']]
            )['n'] ?? 0),
            'users' => (int) (Database::first(
                'SELECT COUNT(*) AS n FROM `users` WHERE igreja_id = :i AND deletado_em IS NULL',
                ['i' => $igreja['id']]
            )['n'] ?? 0),
            'departamentos' => (int) (Database::first(
                'SELECT COUNT(*) AS n FROM `departments` WHERE igreja_id = :i',
                ['i' => $igreja['id']]
            )['n'] ?? 0),
            'eventos' => (int) (Database::first(
                'SELECT COUNT(*) AS n FROM `events` WHERE igreja_id = :i',
                ['i' => $igreja['id']]
            )['n'] ?? 0),
            'transacoes' => (int) (Database::first(
                'SELECT COUNT(*) AS n FROM `transactions` WHERE igreja_id = :i',
                ['i' => $igreja['id']]
            )['n'] ?? 0),
        ];

        // Últimos pagamentos
        $pagamentos = Database::select(
            'SELECT * FROM `payments` WHERE igreja_id = :i ORDER BY id DESC LIMIT 10',
            ['i' => $igreja['id']]
        );

        // Últimos utilizadores
        $usuarios = Database::select(
            'SELECT u.*, GROUP_CONCAT(r.nome SEPARATOR ", ") AS roles
             FROM `users` u
             LEFT JOIN `user_roles` ur ON ur.user_id = u.id
             LEFT JOIN `roles` r ON r.id = ur.role_id
             WHERE u.igreja_id = :i AND u.deletado_em IS NULL
             GROUP BY u.id
             ORDER BY u.id DESC LIMIT 10',
            ['i' => $igreja['id']]
        );

        View::render('superadmin/igrejas/show', [
            'title'      => $igreja['nome'] . ' — Superadmin',
            'supervisor' => $supervisor,
            'igreja'     => $igreja,
            'assinatura' => $assinatura,
            'stats'      => $stats,
            'pagamentos' => $pagamentos,
            'usuarios'   => $usuarios,
        ], 'layouts/superadmin');
    }

    // ======================================================
    // EDITAR
    // ======================================================

    public function edit(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $igreja = Database::first(
            'SELECT * FROM `igrejas` WHERE id = :id AND deletado_em IS NULL LIMIT 1',
            ['id' => (int) $id]
        );

        if (!$igreja) {
            flash('error', 'Igreja não encontrada.');
            redirect('/superadmin/igrejas');
        }

        $planos = Database::select('SELECT * FROM `planos` WHERE ativo = 1 ORDER BY ordem');

        View::render('superadmin/igrejas/form', [
            'title'      => 'Editar Igreja — Superadmin',
            'supervisor' => $supervisor,
            'igreja'     => $igreja,
            'planos'     => $planos,
            'isCreate'   => false,
            'errors'     => [],
        ], 'layouts/superadmin');
    }

    public function update(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $igreja = Database::first(
            'SELECT * FROM `igrejas` WHERE id = :id AND deletado_em IS NULL LIMIT 1',
            ['id' => (int) $id]
        );

        if (!$igreja) {
            flash('error', 'Igreja não encontrada.');
            redirect('/superadmin/igrejas');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->required('email', 'E-mail')
          ->required('slug', 'Slug')
          ->email('email', 'E-mail')
          ->maxLen('nome', 180, 'Nome')
          ->maxLen('slug', 180, 'Slug');

        $slugExiste = Database::first(
            'SELECT id FROM `igrejas` WHERE slug = :s AND id != :id LIMIT 1',
            ['s' => $data['slug'], 'id' => $igreja['id']]
        );
        if ($slugExiste) {
            $v->required('__dup', 'Dup');
            $GLOBALS['__extra_errors'] = ['slug' => 'Este slug já está em uso.'];
        }

        if ($v->fails()) {
            $errors = array_merge($v->errors(), $GLOBALS['__extra_errors'] ?? []);
            unset($GLOBALS['__extra_errors']);
            $planos = Database::select('SELECT * FROM `planos` WHERE ativo = 1 ORDER BY ordem');
            View::render('superadmin/igrejas/form', [
                'title'      => 'Editar Igreja — Superadmin',
                'supervisor' => $supervisor,
                'igreja'     => array_merge($igreja, $data),
                'planos'     => $planos,
                'isCreate'   => false,
                'errors'     => $errors,
            ], 'layouts/superadmin');
            return;
        }

        try {
            Database::execute(
                'UPDATE `igrejas` SET
                    `nome` = :nome,
                    `slug` = :slug,
                    `nif` = :nif,
                    `email` = :email,
                    `telefone` = :tel,
                    `endereco` = :end,
                    `cidade` = :cid,
                    `provincia` = :prov,
                    `pais` = :pais,
                    `fuso_horario` = :fuso,
                    `moeda` = :moeda,
                    `plano_id` = :plano,
                    `status` = :status
                 WHERE `id` = :id',
                [
                    'id'    => $igreja['id'],
                    'nome'  => $data['nome'],
                    'slug'  => $data['slug'],
                    'nif'   => $data['nif'] ?: null,
                    'email' => strtolower($data['email']),
                    'tel'   => $data['telefone'] ?: null,
                    'end'   => $data['endereco'] ?: null,
                    'cid'   => $data['cidade'] ?: null,
                    'prov'  => $data['provincia'] ?: null,
                    'pais'  => $data['pais'] ?: 'Angola',
                    'fuso'  => $data['fuso_horario'] ?: 'Africa/Luanda',
                    'moeda' => $data['moeda'] ?: 'AOA',
                    'plano' => $data['plano_id'] ?: null,
                    'status'=> $data['status'],
                ]
            );

            Audit::log('superadmin', 'editar_igreja', 'igrejas', (string) $igreja['id'], $igreja, $data);
            flash('success', 'Igreja atualizada.');
            redirect('/superadmin/igrejas/' . $igreja['id']);
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/superadmin/igrejas/' . $igreja['id'] . '/editar');
        }
    }

    // ======================================================
    // AÇÕES (suspender, reativar, cancelar)
    // ======================================================

    public function suspender(string $id): void
    {
        $this->alterarStatus((int) $id, 'suspensa', 'Igreja suspensa.');
    }

    public function reativar(string $id): void
    {
        $this->alterarStatus((int) $id, 'activa', 'Igreja reativada.');
    }

    public function cancelar(string $id): void
    {
        $this->alterarStatus((int) $id, 'cancelada', 'Igreja cancelada.');
    }

    private function alterarStatus(int $id, string $novoStatus, string $mensagem): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $igreja = Database::first(
            'SELECT * FROM `igrejas` WHERE id = :id AND deletado_em IS NULL LIMIT 1',
            ['id' => $id]
        );

        if (!$igreja) {
            flash('error', 'Igreja não encontrada.');
            redirect('/superadmin/igrejas');
        }

        Database::execute(
            'UPDATE `igrejas` SET `status` = :s WHERE `id` = :id',
            ['s' => $novoStatus, 'id' => $igreja['id']]
        );

        Audit::log('superadmin', 'alterar_status_igreja', 'igrejas', (string) $igreja['id'], [
            'status_anterior' => $igreja['status'],
        ], ['status' => $novoStatus]);

        flash('success', $mensagem);
        redirect('/superadmin/igrejas/' . $igreja['id']);
    }

    // ======================================================
    // REENVIAR CREDENCIAIS
    // ======================================================

    public function reenviarCredenciais(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $igreja = Database::first(
            'SELECT * FROM `igrejas` WHERE id = :id AND deletado_em IS NULL LIMIT 1',
            ['id' => (int) $id]
        );

        if (!$igreja) {
            flash('error', 'Igreja não encontrada.');
            redirect('/superadmin/igrejas');
        }

        // Busca admin da igreja
        $admin = Database::first(
            'SELECT u.* FROM `users` u
             JOIN `user_roles` ur ON ur.user_id = u.id
             JOIN `roles` r ON r.id = ur.role_id
             WHERE u.igreja_id = :i AND u.deletado_em IS NULL AND r.slug = "admin_igreja"
             LIMIT 1',
            ['i' => $igreja['id']]
        );

        if (!$admin) {
            flash('error', 'Admin não encontrado.');
            redirect('/superadmin/igrejas/' . $igreja['id']);
        }

        // Gera nova senha temporária
        $novaSenha = $this->gerarSenhaTemp();

        Database::execute(
            'UPDATE `users` SET `senha_hash` = :h, `must_change_password` = 1,
             `tentativas_login` = 0, `bloqueado_ate` = NULL WHERE `id` = :id',
            ['h' => password_hash($novaSenha, PASSWORD_ARGON2ID), 'id' => $admin['id']]
        );

        $this->enviarCredenciais($admin['email'], $admin['nome'], $igreja['slug'], $novaSenha);

        Audit::log('superadmin', 'reenviar_credenciais', 'igrejas', (string) $igreja['id'], null, null);
        flash('success', 'Novas credenciais enviadas para ' . $admin['email']);
        redirect('/superadmin/igrejas/' . $igreja['id']);
    }

    // ======================================================
    // IMPERSONATE (ver como igreja)
    // ======================================================

    public function impersonate(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $igreja = Database::first(
            'SELECT * FROM `igrejas` WHERE id = :id AND deletado_em IS NULL LIMIT 1',
            ['id' => (int) $id]
        );

        if (!$igreja) {
            flash('error', 'Igreja não encontrada.');
            redirect('/superadmin/igrejas');
        }

        // Busca o admin da igreja
        $admin = Database::first(
            'SELECT u.* FROM `users` u
             JOIN `user_roles` ur ON ur.user_id = u.id
             JOIN `roles` r ON r.id = ur.role_id
             WHERE u.igreja_id = :i AND u.deletado_em IS NULL AND u.ativo = 1 AND r.slug = "admin_igreja"
             LIMIT 1',
            ['i' => $igreja['id']]
        );

        if (!$admin) {
            flash('error', 'Admin não encontrado.');
            redirect('/superadmin/igrejas/' . $igreja['id']);
        }

        // Guarda o supervisor na sessão como fallback
        $_SESSION['_superadmin_impersonate'] = [
            'supervisor_id' => $supervisor['id'],
            'igreja_id'     => $igreja['id'],
            'iniciado_em'   => time(),
        ];

        // Faz login como o admin da igreja
        $_SESSION['_auth_user'] = (int) $admin['id'];
        $_SESSION['igreja_id']  = (int) $igreja['id'];

        \App\Core\Tenant::set((int) $igreja['id']);

        Audit::log('superadmin', 'impersonate', 'igrejas', (string) $igreja['id'], null, [
            'admin_id' => $admin['id'],
        ]);

        flash('success', 'A visualizar a igreja: ' . $igreja['nome']);
        redirect('/dashboard');
    }

    // ======================================================
    // HELPERS
    // ======================================================

    private function dadosDoFormulario(): array
    {
        return [
            'nome'         => trim((string) ($_POST['nome'] ?? '')),
            'slug'         => strtolower(trim((string) ($_POST['slug'] ?? ''))),
            'nif'          => trim((string) ($_POST['nif'] ?? '')),
            'email'        => trim((string) ($_POST['email'] ?? '')),
            'telefone'     => trim((string) ($_POST['telefone'] ?? '')),
            'endereco'     => trim((string) ($_POST['endereco'] ?? '')),
            'cidade'       => trim((string) ($_POST['cidade'] ?? '')),
            'provincia'    => trim((string) ($_POST['provincia'] ?? '')),
            'pais'         => trim((string) ($_POST['pais'] ?? 'Angola')),
            'fuso_horario' => trim((string) ($_POST['fuso_horario'] ?? 'Africa/Luanda')),
            'moeda'        => trim((string) ($_POST['moeda'] ?? 'AOA')),
            'plano_id'     => (int) ($_POST['plano_id'] ?? 0) ?: null,
            'status'       => (string) ($_POST['status'] ?? 'em_teste'),
            'admin_nome'   => trim((string) ($_POST['admin_nome'] ?? '')),
            'admin_email'  => trim((string) ($_POST['admin_email'] ?? '')),
        ];
    }

    private function gerarSenhaTemp(): string
    {
        // Senha legível: ex. "Forte-4X7K-2026"
        $parte = strtoupper(bin2hex(random_bytes(3)));
        return 'Forte-' . $parte . '-' . date('Y');
    }

    private function enviarCredenciais(string $email, string $nome, string $slug, string $senha): bool
    {
        $link = rtrim((string) config('app.url'), '/') . '/login';

        $body = '
            <div style="font-family:Inter,system-ui,sans-serif;background:#f8f3e7;padding:32px;color:#2e1b0e">
                <div style="max-width:560px;margin:0 auto;background:#fffdf8;border-radius:12px;border:1px solid #ece2cc;overflow:hidden">
                    <div style="background:linear-gradient(180deg,#e3b64a,#b8862b);padding:24px;text-align:center">
                        <h1 style="font-family:Cinzel,serif;color:#fff;margin:0;font-size:22px;letter-spacing:.04em">MONANA ECLESIA</h1>
                    </div>
                    <div style="padding:28px">
                        <h2 style="font-family:Cormorant Garamond,serif;font-size:24px;color:#2e1b0e;margin:0 0 16px">
                            Bem-vindo(a), ' . esc($nome) . '!
                        </h2>
                        <p style="font-size:14px;line-height:1.6;color:#3a2412;margin:0 0 16px">
                            A sua igreja foi criada no <strong>Monana Eclesia</strong>.
                            Abaixo estão as suas credenciais de acesso:
                        </p>
                        <div style="background:#fbf6ea;border-radius:8px;padding:16px;margin:16px 0;font-family:monospace;font-size:13px">
                            <div><strong>E-mail:</strong> ' . esc($email) . '</div>
                            <div style="margin-top:6px"><strong>Senha:</strong> ' . esc($senha) . '</div>
                        </div>
                        <p style="font-size:13px;color:#7c6d58;margin:0 0 16px">
                            <i>Por segurança, será pedido que defina uma nova senha no primeiro login.</i>
                        </p>
                        <p style="text-align:center;margin:28px 0">
                            <a href="' . esc($link) . '"
                               style="display:inline-block;background:linear-gradient(180deg,#d9ab45,#b8862b);color:#fff;
                                      padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;
                                      font-size:14px">
                                Entrar no sistema
                            </a>
                        </p>
                    </div>
                    <div style="background:#fbf6ea;padding:16px;text-align:center;font-size:11px;color:#7c6d58">
                        Monana Eclesia · Plataforma Integrada de Gestão de Igrejas
                    </div>
                </div>
            </div>
        ';

        return Mailer::send($email, $nome, 'Bem-vindo(a) ao Monana Eclesia', $body);
    }
}