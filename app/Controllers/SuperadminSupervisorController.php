<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Core\View;

final class SuperadminSupervisorController extends Controller
{
    // ======================================================
    // LISTA
    // ======================================================

    public function index(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $supervisores = Database::select(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM `supervisor_igrejas` si WHERE si.supervisor_id = s.id) AS total_igrejas
             FROM `supervisores` s
             ORDER BY s.ativo DESC, s.id DESC'
        );

        View::render('superadmin/supervisores/index', [
            'title'       => 'Supervisores — Superadmin',
            'supervisor'  => $supervisor,
            'supervisores'=> $supervisores,
        ], 'layouts/superadmin');
    }

    // ======================================================
    // CRIAR
    // ======================================================

    public function create(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        View::render('superadmin/supervisores/form', [
            'title'      => 'Novo Supervisor — Superadmin',
            'supervisor' => $supervisor,
            'alvo'       => [],
            'isCreate'   => true,
            'errors'     => [],
        ], 'layouts/superadmin');
    }

    public function store(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->required('email', 'E-mail')
          ->required('nivel', 'Nível')
          ->email('email', 'E-mail')
          ->maxLen('nome', 150, 'Nome')
          ->in('nivel', ['superadmin', 'supervisor'], 'Nível');

        if (strlen((string) ($_POST['senha'] ?? '')) < 8) {
            $v->required('__senha', 'Senha');
            $GLOBALS['__extra_errors'] = ['senha' => 'A senha deve ter pelo menos 8 caracteres.'];
        }

        // Email único
        $existe = Database::first(
            'SELECT id FROM `supervisores` WHERE LOWER(`email`) = :e LIMIT 1',
            ['e' => strtolower($data['email'])]
        );
        if ($existe) {
            $v->required('__dup', 'Dup');
            $GLOBALS['__extra_errors'] = ['email' => 'Este e-mail já está registado.'];
        }

        if ($v->fails()) {
            $errors = array_merge($v->errors(), $GLOBALS['__extra_errors'] ?? []);
            unset($GLOBALS['__extra_errors']);
            View::render('superadmin/supervisores/form', [
                'title'      => 'Novo Supervisor — Superadmin',
                'supervisor' => $supervisor,
                'alvo'       => $data,
                'isCreate'   => true,
                'errors'     => $errors,
            ], 'layouts/superadmin');
            return;
        }

        try {
            $id = Database::insert(
                'INSERT INTO `supervisores`
                    (`nome`, `email`, `senha_hash`, `nivel`, `telefone`, `ativo`, `must_change_password`)
                 VALUES (:nome, :email, :hash, :nivel, :tel, :ativo, 1)',
                [
                    'nome'  => $data['nome'],
                    'email' => strtolower($data['email']),
                    'hash'  => password_hash((string) $_POST['senha'], PASSWORD_ARGON2ID),
                    'nivel' => $data['nivel'],
                    'tel'   => $data['telefone'] ?: null,
                    'ativo' => $data['ativo'],
                ]
            );

            Audit::log('superadmin', 'criar_supervisor', 'supervisores', (string) $id, null, $data);
            flash('success', 'Supervisor criado. No 1.º login será pedida a troca de senha.');
            redirect('/superadmin/supervisores');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/superadmin/supervisores/criar');
        }
    }

    // ======================================================
    // EDITAR
    // ======================================================

    public function edit(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $alvo = $this->buscarSupervisor((int) $id);
        if (!$alvo) {
            flash('error', 'Supervisor não encontrado.');
            redirect('/superadmin/supervisores');
        }

        $igrejasDoSupervisor = Database::select(
            'SELECT igreja_id FROM `supervisor_igrejas` WHERE supervisor_id = :s',
            ['s' => $alvo['id']]
        );
        $idsIgrejasAtuais = array_map(fn ($r) => (int) $r['igreja_id'], $igrejasDoSupervisor);

        $todasIgrejas = Database::select(
            'SELECT id, nome, slug, status FROM `igrejas`
             WHERE deletado_em IS NULL ORDER BY nome'
        );

        View::render('superadmin/supervisores/form', [
            'title'                => 'Editar Supervisor — Superadmin',
            'supervisor'           => $supervisor,
            'alvo'                 => $alvo,
            'isCreate'             => false,
            'errors'               => [],
            'todasIgrejas'         => $todasIgrejas,
            'idsIgrejasAtuais'     => $idsIgrejasAtuais,
        ], 'layouts/superadmin');
    }

    public function update(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $alvo = $this->buscarSupervisor((int) $id);
        if (!$alvo) {
            flash('error', 'Supervisor não encontrado.');
            redirect('/superadmin/supervisores');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->required('email', 'E-mail')
          ->required('nivel', 'Nível')
          ->email('email', 'E-mail')
          ->in('nivel', ['superadmin', 'supervisor'], 'Nível');

        $existe = Database::first(
            'SELECT id FROM `supervisores` WHERE LOWER(`email`) = :e AND id != :id LIMIT 1',
            ['e' => strtolower($data['email']), 'id' => $alvo['id']]
        );
        if ($existe) {
            $v->required('__dup', 'Dup');
            $GLOBALS['__extra_errors'] = ['email' => 'Este e-mail já está registado por outro supervisor.'];
        }

        if ($v->fails()) {
            $errors = array_merge($v->errors(), $GLOBALS['__extra_errors'] ?? []);
            unset($GLOBALS['__extra_errors']);
            View::render('superadmin/supervisores/form', [
                'title'      => 'Editar Supervisor — Superadmin',
                'supervisor' => $supervisor,
                'alvo'       => array_merge($alvo, $data),
                'isCreate'   => false,
                'errors'     => $errors,
            ], 'layouts/superadmin');
            return;
        }

        try {
            $sets = [
                '`nome` = :nome',
                '`email` = :email',
                '`nivel` = :nivel',
                '`telefone` = :tel',
                '`ativo` = :ativo',
            ];
            $params = [
                'id'    => $alvo['id'],
                'nome'  => $data['nome'],
                'email' => strtolower($data['email']),
                'nivel' => $data['nivel'],
                'tel'   => $data['telefone'] ?: null,
                'ativo' => $data['ativo'],
            ];

            if (!empty($_POST['senha'])) {
                if (strlen((string) $_POST['senha']) < 8) {
                    flash('error', 'A nova senha deve ter pelo menos 8 caracteres.');
                    redirect('/superadmin/supervisores/' . $alvo['id'] . '/editar');
                }
                $sets[] = '`senha_hash` = :hash';
                $sets[] = '`must_change_password` = 1';
                $sets[] = '`tentativas_login` = 0';
                $sets[] = '`bloqueado_ate` = NULL';
                $params['hash'] = password_hash((string) $_POST['senha'], PASSWORD_ARGON2ID);
            }

            $sql = 'UPDATE `supervisores` SET ' . implode(', ', $sets) . ' WHERE `id` = :id';
            Database::execute($sql, $params);

            // Atribuição de igrejas
            $igrejasMarcadas = array_map('intval', (array) ($_POST['igrejas'] ?? []));
            Database::execute('DELETE FROM `supervisor_igrejas` WHERE `supervisor_id` = :s', ['s' => $alvo['id']]);
            foreach ($igrejasMarcadas as $igId) {
                if ($igId > 0) {
                    Database::execute(
                        'INSERT INTO `supervisor_igrejas` (`supervisor_id`, `igreja_id`) VALUES (:s, :i)',
                        ['s' => $alvo['id'], 'i' => $igId]
                    );
                }
            }

            Audit::log('superadmin', 'editar_supervisor', 'supervisores', (string) $alvo['id'], $alvo, $data);
            flash('success', 'Supervisor atualizado.');
            redirect('/superadmin/supervisores');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/superadmin/supervisores/' . $alvo['id'] . '/editar');
        }
    }

    // ======================================================
    // AÇÕES
    // ======================================================

    public function bloquear(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $alvo = $this->buscarSupervisor((int) $id);
        if (!$alvo) {
            flash('error', 'Supervisor não encontrado.');
            redirect('/superadmin/supervisores');
        }

        // Impede-se bloquear a si mesmo
        if ((int) $alvo['id'] === (int) $supervisor['id']) {
            flash('error', 'Não pode bloquear a sua própria conta.');
            redirect('/superadmin/supervisores');
        }

        $novoAtivo = $alvo['ativo'] ? 0 : 1;
        Database::execute(
            'UPDATE `supervisores` SET `ativo` = :a WHERE `id` = :id',
            ['a' => $novoAtivo, 'id' => $alvo['id']]
        );

        Audit::log('superadmin', $novoAtivo ? 'reativar_supervisor' : 'bloquear_supervisor',
            'supervisores', (string) $alvo['id'], $alvo, ['ativo' => $novoAtivo]);

        flash('success', $novoAtivo ? 'Supervisor reativado.' : 'Supervisor bloqueado.');
        redirect('/superadmin/supervisores');
    }

    public function destroy(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $alvo = $this->buscarSupervisor((int) $id);
        if (!$alvo) {
            flash('error', 'Supervisor não encontrado.');
            redirect('/superadmin/supervisores');
        }

        if ((int) $alvo['id'] === (int) $supervisor['id']) {
            flash('error', 'Não pode eliminar a sua própria conta.');
            redirect('/superadmin/supervisores');
        }

        Database::execute('DELETE FROM `supervisores` WHERE `id` = :id', ['id' => $alvo['id']]);
        Audit::log('superadmin', 'eliminar_supervisor', 'supervisores', (string) $alvo['id'], $alvo, null);
        flash('success', 'Supervisor eliminado.');
        redirect('/superadmin/supervisores');
    }

    // ======================================================
    // HELPERS
    // ======================================================

    private function buscarSupervisor(int $id): ?array
    {
        return Database::first(
            'SELECT * FROM `supervisores` WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    private function dadosDoFormulario(): array
    {
        return [
            'nome'     => trim((string) ($_POST['nome'] ?? '')),
            'email'    => trim((string) ($_POST['email'] ?? '')),
            'nivel'    => (string) ($_POST['nivel'] ?? 'supervisor'),
            'telefone' => trim((string) ($_POST['telefone'] ?? '')),
            'ativo'    => isset($_POST['ativo']) ? (int) $_POST['ativo'] : 1,
        ];
    }
}