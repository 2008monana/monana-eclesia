<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Rbac;
use App\Core\Tenant;
use App\Core\Validator;
use App\Core\View;

final class UsuarioController extends Controller
{
    // ======================================================
    // LISTA
    // ======================================================

    public function index(): void
    {
        if (!Rbac::can('utilizadores.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();
        $q        = trim((string) ($_GET['q'] ?? ''));

        $where  = ['u.igreja_id = :i', 'u.deletado_em IS NULL'];
        $params = ['i' => $igrejaId];

        if ($q !== '') {
            $where[] = '(u.nome LIKE :q OR u.email LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);

        $usuarios = Database::select(
            "SELECT u.*,
                    (SELECT GROUP_CONCAT(r.nome SEPARATOR ', ')
                     FROM user_roles ur
                     JOIN roles r ON r.id = ur.role_id
                     WHERE ur.user_id = u.id) AS roles_nomes
             FROM `users` u
             WHERE {$whereSql}
             ORDER BY u.ativo DESC, u.nome ASC",
            $params
        );

        View::render('usuarios/index', [
            'title'    => 'Usuários — Monana Eclesia',
            'user'     => Auth::user(),
            'usuarios' => $usuarios,
            'q'        => $q,
        ], 'layouts/app');
    }

    // ======================================================
    // CRIAR
    // ======================================================

    public function create(): void
    {
        if (!Rbac::can('utilizadores.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/usuarios');
        }

        View::render('usuarios/form', [
            'title'     => 'Novo Usuário — Monana Eclesia',
            'user'      => Auth::user(),
            'usuario'   => [],
            'roles'     => $this->listarRoles(),
            'isCreate'  => true,
            'errors'    => [],
        ], 'layouts/app');
    }

    public function store(): void
    {
        if (!Rbac::can('utilizadores.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/usuarios');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->required('email', 'E-mail')
          ->email('email', 'E-mail')
          ->maxLen('nome', 150, 'Nome')
          ->maxLen('email', 180, 'E-mail');

        if (strlen((string) ($_POST['senha'] ?? '')) < 8) {
            $v->required('senha', 'Senha'); // força erro
            $GLOBALS['__extra_errors'] = ['senha' => 'A senha deve ter pelo menos 8 caracteres.'];
        }

        // Verifica unicidade do email dentro da igreja
        $existe = Database::first(
            'SELECT id FROM `users` WHERE `igreja_id` = :i AND LOWER(`email`) = :e AND `deletado_em` IS NULL LIMIT 1',
            ['i' => Tenant::id(), 'e' => strtolower($data['email'])]
        );
        if ($existe) {
            $v->required('__dup', 'Dup'); // força erro
            $GLOBALS['__extra_errors'] = ['email' => 'Este e-mail já está registado.'];
        }

        if ($v->fails()) {
            $errors = array_merge($v->errors(), $GLOBALS['__extra_errors'] ?? []);
            unset($GLOBALS['__extra_errors']);
            View::render('usuarios/form', [
                'title'    => 'Novo Usuário — Monana Eclesia',
                'user'     => Auth::user(),
                'usuario'  => $data,
                'roles'    => $this->listarRoles(),
                'isCreate' => true,
                'errors'   => $errors,
            ], 'layouts/app');
            return;
        }

        try {
            $userId = Database::insert(
                'INSERT INTO `users`
                    (`igreja_id`, `nome`, `email`, `senha_hash`, `telefone`,
                     `ativo`, `must_change_password`)
                 VALUES (:i, :nome, :email, :hash, :tel, :ativo, 1)',
                [
                    'i'     => Tenant::id(),
                    'nome'  => $data['nome'],
                    'email' => strtolower($data['email']),
                    'hash'  => password_hash((string) $_POST['senha'], PASSWORD_ARGON2ID),
                    'tel'   => $data['telefone'] ?: null,
                    'ativo' => $data['ativo'],
                ]
            );

            // Perfil
            Database::execute(
                'INSERT INTO `user_profiles` (`user_id`) VALUES (:id)
                 ON DUPLICATE KEY UPDATE `user_id` = VALUES(`user_id`)',
                ['id' => $userId]
            );

            // Roles selecionadas
            $roleIds = array_map('intval', (array) ($_POST['roles'] ?? []));
            foreach ($roleIds as $rid) {
                Database::execute(
                    'INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES (:u, :r)',
                    ['u' => $userId, 'r' => $rid]
                );
            }

            Audit::log('utilizadores', 'criar', 'users', (string) $userId, null, $data);
            flash('success', 'Usuário criado. No 1.º login será pedida a troca de senha.');
            redirect('/usuarios/' . $userId);
        } catch (\Throwable $e) {
            logger('error', 'Erro ao criar usuário', ['msg' => $e->getMessage()]);
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/usuarios/criar');
        }
    }

    // ======================================================
    // VER (roles + permissions + sessões)
    // ======================================================

    public function show(string $id): void
    {
        if (!Rbac::can('utilizadores.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $usuario = $this->buscarUsuario((int) $id);
        if (!$usuario) {
            flash('error', 'Usuário não encontrado.');
            redirect('/usuarios');
        }

        $rolesDoUsuario = Database::select(
            'SELECT r.* FROM `roles` r
             JOIN `user_roles` ur ON ur.role_id = r.id
             WHERE ur.user_id = :u',
            ['u' => $usuario['id']]
        );

        $rolesDisponiveis = $this->listarRoles();
        $rolesAtuaisIds   = array_map(fn ($r) => (int) $r['id'], $rolesDoUsuario);

        $overrides = Database::select(
            'SELECT up.*, p.modulo, p.acao, p.descricao
             FROM `user_permissions` up
             JOIN `permissions` p ON p.id = up.permission_id
             WHERE up.user_id = :u
             ORDER BY p.modulo, p.acao',
            ['u' => $usuario['id']]
        );

        $sessoes = Database::select(
            'SELECT * FROM `user_sessions`
             WHERE `user_id` = :u AND `ativo` = 1 AND `expira_em` > NOW()
             ORDER BY `criado_em` DESC',
            ['u' => $usuario['id']]
        );

        $todasPermissoes = Database::select(
            'SELECT * FROM `permissions` ORDER BY `modulo`, `acao`'
        );

        View::render('usuarios/show', [
            'title'              => $usuario['nome'] . ' — Monana Eclesia',
            'user'               => Auth::user(),
            'usuario'            => $usuario,
            'rolesDoUsuario'     => $rolesDoUsuario,
            'rolesDisponiveis'   => $rolesDisponiveis,
            'rolesAtuaisIds'     => $rolesAtuaisIds,
            'overrides'          => $overrides,
            'sessoes'            => $sessoes,
            'todasPermissoes'    => $todasPermissoes,
        ], 'layouts/app');
    }

    // ======================================================
    // EDITAR
    // ======================================================

    public function edit(string $id): void
    {
        if (!Rbac::can('utilizadores.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/usuarios');
        }

        $usuario = $this->buscarUsuario((int) $id);
        if (!$usuario) {
            flash('error', 'Usuário não encontrado.');
            redirect('/usuarios');
        }

        View::render('usuarios/form', [
            'title'    => 'Editar Usuário — Monana Eclesia',
            'user'     => Auth::user(),
            'usuario'  => $usuario,
            'roles'    => $this->listarRoles(),
            'isCreate' => false,
            'errors'   => [],
        ], 'layouts/app');
    }

    public function update(string $id): void
    {
        if (!Rbac::can('utilizadores.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/usuarios');
        }

        $usuario = $this->buscarUsuario((int) $id);
        if (!$usuario) {
            flash('error', 'Usuário não encontrado.');
            redirect('/usuarios');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->required('email', 'E-mail')
          ->email('email', 'E-mail')
          ->maxLen('nome', 150, 'Nome')
          ->maxLen('email', 180, 'E-mail');

        // Verifica unicidade do email (outro usuário)
        $existe = Database::first(
            'SELECT id FROM `users`
             WHERE `igreja_id` = :i AND LOWER(`email`) = :e AND `id` != :id AND `deletado_em` IS NULL LIMIT 1',
            ['i' => Tenant::id(), 'e' => strtolower($data['email']), 'id' => $usuario['id']]
        );
        if ($existe) {
            $v->required('__dup', 'Dup');
            $GLOBALS['__extra_errors'] = ['email' => 'Este e-mail já está registado por outro usuário.'];
        }

        if ($v->fails()) {
            $errors = array_merge($v->errors(), $GLOBALS['__extra_errors'] ?? []);
            unset($GLOBALS['__extra_errors']);
            View::render('usuarios/form', [
                'title'    => 'Editar Usuário — Monana Eclesia',
                'user'     => Auth::user(),
                'usuario'  => array_merge($usuario, $data),
                'roles'    => $this->listarRoles(),
                'isCreate' => false,
                'errors'   => $errors,
            ], 'layouts/app');
            return;
        }

        try {
            $sets = [
                '`nome` = :nome',
                '`email` = :email',
                '`telefone` = :tel',
                '`ativo` = :ativo',
            ];
            $params = [
                'id'    => $usuario['id'],
                'nome'  => $data['nome'],
                'email' => strtolower($data['email']),
                'tel'   => $data['telefone'] ?: null,
                'ativo' => $data['ativo'],
            ];

            // Se nova senha foi preenchida
            if (!empty($_POST['senha'])) {
                if (strlen((string) $_POST['senha']) < 8) {
                    flash('error', 'A nova senha deve ter pelo menos 8 caracteres.');
                    redirect('/usuarios/' . $usuario['id'] . '/editar');
                }
                $sets[] = '`senha_hash` = :hash';
                $sets[] = '`must_change_password` = 1';
                $sets[] = '`tentativas_login` = 0';
                $sets[] = '`bloqueado_ate` = NULL';
                $params['hash'] = password_hash((string) $_POST['senha'], PASSWORD_ARGON2ID);
            }

            $sql = 'UPDATE `users` SET ' . implode(', ', $sets)
                 . ' WHERE `id` = :id AND `igreja_id` = :igreja_id';
            $params['igreja_id'] = Tenant::id();

            Database::execute($sql, $params);

            Audit::log('utilizadores', 'editar', 'users', (string) $usuario['id'], $usuario, $data);
            flash('success', 'Usuário atualizado.');
            redirect('/usuarios/' . $usuario['id']);
        } catch (\Throwable $e) {
            logger('error', 'Erro ao editar usuário', ['msg' => $e->getMessage()]);
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/usuarios/' . $usuario['id'] . '/editar');
        }
    }

    // ======================================================
    // ELIMINAR (soft)
    // ======================================================

    public function destroy(string $id): void
    {
        if (!Rbac::can('utilizadores.eliminar')) {
            flash('error', 'Sem permissão.');
            redirect('/usuarios');
        }

        $usuario = $this->buscarUsuario((int) $id);
        if (!$usuario) {
            flash('error', 'Usuário não encontrado.');
            redirect('/usuarios');
        }

        // Impede apagar a si mesmo
        if ((int) ($usuario['id']) === (int) (Auth::id() ?? 0)) {
            flash('error', 'Não pode eliminar a sua própria conta.');
            redirect('/usuarios');
        }

        Database::execute(
            'UPDATE `users` SET `deletado_em` = NOW(), `ativo` = 0 WHERE `id` = :id AND `igreja_id` = :i',
            ['id' => $usuario['id'], 'i' => Tenant::id()]
        );

        Audit::log('utilizadores', 'eliminar', 'users', (string) $usuario['id'], $usuario, null);
        flash('success', 'Usuário eliminado.');
        redirect('/usuarios');
    }

    // ======================================================
    // ROLES
    // ======================================================

    public function saveRoles(string $id): void
    {
        if (!Rbac::can('utilizadores.atribuir_permissoes')) {
            flash('error', 'Sem permissão.');
            redirect('/usuarios');
        }

        $usuario = $this->buscarUsuario((int) $id);
        if (!$usuario) {
            flash('error', 'Usuário não encontrado.');
            redirect('/usuarios');
        }

        $roleIds = array_map('intval', (array) ($_POST['roles'] ?? []));

        Database::execute('DELETE FROM `user_roles` WHERE `user_id` = :u', ['u' => $usuario['id']]);
        foreach ($roleIds as $rid) {
            if ($rid > 0) {
                Database::execute(
                    'INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES (:u, :r)',
                    ['u' => $usuario['id'], 'r' => $rid]
                );
            }
        }

        Rbac::clearCache();
        Audit::log('utilizadores', 'atribuir_roles', 'user_roles', (string) $usuario['id'], null, ['roles' => $roleIds]);
        flash('success', 'Roles atualizadas.');
        redirect('/usuarios/' . $usuario['id']);
    }

    // ======================================================
    // PERMISSIONS (override)
    // ======================================================

    public function addPermission(string $id): void
    {
        if (!Rbac::can('utilizadores.atribuir_permissoes')) {
            flash('error', 'Sem permissão.');
            redirect('/usuarios');
        }

        $usuario = $this->buscarUsuario((int) $id);
        if (!$usuario) {
            flash('error', 'Usuário não encontrado.');
            redirect('/usuarios');
        }

        $permissionId = (int) ($_POST['permission_id'] ?? 0);
        $tipo         = (string) ($_POST['tipo'] ?? '');

        if (!$permissionId || !in_array($tipo, ['grant', 'deny'], true)) {
            flash('error', 'Dados inválidos.');
            redirect('/usuarios/' . $usuario['id']);
        }

        Database::execute(
            'INSERT INTO `user_permissions` (`user_id`, `permission_id`, `tipo`)
             VALUES (:u, :p, :t)
             ON DUPLICATE KEY UPDATE `tipo` = VALUES(`tipo`)',
            ['u' => $usuario['id'], 'p' => $permissionId, 't' => $tipo]
        );

        Rbac::clearCache();
        Audit::log('utilizadores', 'override_permission', 'user_permissions', (string) $usuario['id'], null, [
            'permission_id' => $permissionId,
            'tipo'          => $tipo,
        ]);
        flash('success', 'Permissão atualizada.');
        redirect('/usuarios/' . $usuario['id']);
    }

    public function removePermission(string $id, string $pid): void
    {
        if (!Rbac::can('utilizadores.atribuir_permissoes')) {
            flash('error', 'Sem permissão.');
            redirect('/usuarios');
        }

        $usuario = $this->buscarUsuario((int) $id);
        if (!$usuario) {
            flash('error', 'Usuário não encontrado.');
            redirect('/usuarios');
        }

        Database::execute(
            'DELETE FROM `user_permissions` WHERE `user_id` = :u AND `permission_id` = :p',
            ['u' => $usuario['id'], 'p' => (int) $pid]
        );

        Rbac::clearCache();
        Audit::log('utilizadores', 'remove_override', 'user_permissions', (string) $usuario['id'], [
            'permission_id' => (int) $pid,
        ], null);
        flash('success', 'Override removido.');
        redirect('/usuarios/' . $usuario['id']);
    }

    // ======================================================
    // SESSÕES
    // ======================================================

    public function revokeSession(string $id, string $sid): void
    {
        if (!Rbac::can('utilizadores.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/usuarios');
        }

        $usuario = $this->buscarUsuario((int) $id);
        if (!$usuario) {
            flash('error', 'Usuário não encontrado.');
            redirect('/usuarios');
        }

        Database::execute(
            'UPDATE `user_sessions` SET `ativo` = 0 WHERE `id` = :s AND `user_id` = :u',
            ['s' => (int) $sid, 'u' => $usuario['id']]
        );

        Audit::log('utilizadores', 'revogar_sessao', 'user_sessions', $sid, null, null);
        flash('success', 'Sessão revogada.');
        redirect('/usuarios/' . $usuario['id']);
    }

    // ======================================================
    // HELPERS
    // ======================================================

    private function buscarUsuario(int $id): ?array
    {
        return Database::first(
            'SELECT * FROM `users`
             WHERE `id` = :id AND `igreja_id` = :i AND `deletado_em` IS NULL LIMIT 1',
            ['id' => $id, 'i' => Tenant::id()]
        );
    }

    private function listarRoles(): array
    {
        return Database::select(
            'SELECT * FROM `roles`
             WHERE (`igreja_id` IS NULL OR `igreja_id` = :i)
             ORDER BY `sistema` DESC, `nome`',
            ['i' => Tenant::id()]
        );
    }

    private function dadosDoFormulario(): array
    {
        return [
            'nome'     => trim((string) ($_POST['nome'] ?? '')),
            'email'    => trim((string) ($_POST['email'] ?? '')),
            'telefone' => trim((string) ($_POST['telefone'] ?? '')),
            'ativo'    => isset($_POST['ativo']) ? (int) $_POST['ativo'] : 1,
        ];
    }
}