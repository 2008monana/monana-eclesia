<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Core\View;

final class SuperadminPlanoController extends Controller
{
    public function index(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $planos = Database::select(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM `subscriptions` s WHERE s.plano_id = p.id AND s.status IN ("activa","trial","expirando")) AS total_assinaturas
             FROM `planos` p
             ORDER BY p.ordem'
        );

        View::render('superadmin/planos/index', [
            'title'      => 'Planos — Superadmin',
            'supervisor' => $supervisor,
            'planos'     => $planos,
        ], 'layouts/superadmin');
    }

    public function create(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        View::render('superadmin/planos/form', [
            'title'      => 'Novo Plano — Superadmin',
            'supervisor' => $supervisor,
            'plano'      => [],
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
          ->required('slug', 'Slug')
          ->maxLen('nome', 50, 'Nome')
          ->maxLen('slug', 50, 'Slug');

        $existe = Database::first('SELECT id FROM `planos` WHERE slug = :s LIMIT 1', ['s' => $data['slug']]);
        if ($existe) {
            $v->required('__dup', 'Dup');
            $GLOBALS['__extra_errors'] = ['slug' => 'Este slug já está em uso.'];
        }

        if ($v->fails()) {
            $errors = array_merge($v->errors(), $GLOBALS['__extra_errors'] ?? []);
            unset($GLOBALS['__extra_errors']);
            View::render('superadmin/planos/form', [
                'title'      => 'Novo Plano — Superadmin',
                'supervisor' => $supervisor,
                'plano'      => $data,
                'isCreate'   => true,
                'errors'     => $errors,
            ], 'layouts/superadmin');
            return;
        }

        try {
            $id = Database::insert(
                'INSERT INTO `planos`
                    (`nome`, `slug`, `descricao`, `preco_kz`, `periodicidade_dias`,
                     `limite_membros`, `limite_users`, `limite_storage_mb`, `ativo`, `ordem`)
                 VALUES (:nome, :slug, :desc, :preco, :period,
                         :lm, :lu, :ls, :ativo, :ordem)',
                [
                    'nome'   => $data['nome'],
                    'slug'   => $data['slug'],
                    'desc'   => $data['descricao'] ?: null,
                    'preco'  => (float) str_replace(',', '.', $data['preco_kz']),
                    'period' => (int) $data['periodicidade_dias'],
                    'lm'     => $data['limite_membros'] !== '' ? (int) $data['limite_membros'] : null,
                    'lu'     => $data['limite_users'] !== '' ? (int) $data['limite_users'] : null,
                    'ls'     => $data['limite_storage_mb'] !== '' ? (int) $data['limite_storage_mb'] : null,
                    'ativo'  => $data['ativo'],
                    'ordem'  => (int) $data['ordem'],
                ]
            );

            Audit::log('superadmin', 'criar_plano', 'planos', (string) $id, null, $data);
            flash('success', 'Plano criado.');
            redirect('/superadmin/planos');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/superadmin/planos/criar');
        }
    }

    public function edit(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $plano = $this->buscarPlano((int) $id);
        if (!$plano) {
            flash('error', 'Plano não encontrado.');
            redirect('/superadmin/planos');
        }

        View::render('superadmin/planos/form', [
            'title'      => 'Editar Plano — Superadmin',
            'supervisor' => $supervisor,
            'plano'      => $plano,
            'isCreate'   => false,
            'errors'     => [],
        ], 'layouts/superadmin');
    }

    public function update(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $plano = $this->buscarPlano((int) $id);
        if (!$plano) {
            flash('error', 'Plano não encontrado.');
            redirect('/superadmin/planos');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->required('slug', 'Slug')
          ->maxLen('nome', 50, 'Nome')
          ->maxLen('slug', 50, 'Slug');

        $existe = Database::first(
            'SELECT id FROM `planos` WHERE slug = :s AND id != :id LIMIT 1',
            ['s' => $data['slug'], 'id' => $plano['id']]
        );
        if ($existe) {
            $v->required('__dup', 'Dup');
            $GLOBALS['__extra_errors'] = ['slug' => 'Este slug já está em uso.'];
        }

        if ($v->fails()) {
            $errors = array_merge($v->errors(), $GLOBALS['__extra_errors'] ?? []);
            unset($GLOBALS['__extra_errors']);
            View::render('superadmin/planos/form', [
                'title'      => 'Editar Plano — Superadmin',
                'supervisor' => $supervisor,
                'plano'      => array_merge($plano, $data),
                'isCreate'   => false,
                'errors'     => $errors,
            ], 'layouts/superadmin');
            return;
        }

        try {
            Database::execute(
                'UPDATE `planos` SET
                    `nome` = :nome,
                    `slug` = :slug,
                    `descricao` = :desc,
                    `preco_kz` = :preco,
                    `periodicidade_dias` = :period,
                    `limite_membros` = :lm,
                    `limite_users` = :lu,
                    `limite_storage_mb` = :ls,
                    `ativo` = :ativo,
                    `ordem` = :ordem
                 WHERE `id` = :id',
                [
                    'id'     => $plano['id'],
                    'nome'   => $data['nome'],
                    'slug'   => $data['slug'],
                    'desc'   => $data['descricao'] ?: null,
                    'preco'  => (float) str_replace(',', '.', $data['preco_kz']),
                    'period' => (int) $data['periodicidade_dias'],
                    'lm'     => $data['limite_membros'] !== '' ? (int) $data['limite_membros'] : null,
                    'lu'     => $data['limite_users'] !== '' ? (int) $data['limite_users'] : null,
                    'ls'     => $data['limite_storage_mb'] !== '' ? (int) $data['limite_storage_mb'] : null,
                    'ativo'  => $data['ativo'],
                    'ordem'  => (int) $data['ordem'],
                ]
            );

            Audit::log('superadmin', 'editar_plano', 'planos', (string) $plano['id'], $plano, $data);
            flash('success', 'Plano atualizado.');
            redirect('/superadmin/planos');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/superadmin/planos/' . $plano['id'] . '/editar');
        }
    }

    public function destroy(string $id): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $plano = $this->buscarPlano((int) $id);
        if (!$plano) {
            flash('error', 'Plano não encontrado.');
            redirect('/superadmin/planos');
        }

        // Não eliminar se houver assinaturas ativas
        $emUso = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `subscriptions`
             WHERE `plano_id` = :p AND `status` IN ("activa","trial","expirando")',
            ['p' => $plano['id']]
        )['n'] ?? 0);

        if ($emUso > 0) {
            flash('error', "Não é possível eliminar: existem {$emUso} assinatura(s) ativa(s) neste plano.");
            redirect('/superadmin/planos');
        }

        Database::execute('UPDATE `planos` SET `ativo` = 0 WHERE `id` = :id', ['id' => $plano['id']]);
        Audit::log('superadmin', 'desativar_plano', 'planos', (string) $plano['id'], $plano, ['ativo' => 0]);
        flash('success', 'Plano desativado.');
        redirect('/superadmin/planos');
    }

    private function buscarPlano(int $id): ?array
    {
        return Database::first('SELECT * FROM `planos` WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    private function dadosDoFormulario(): array
    {
        return [
            'nome'               => trim((string) ($_POST['nome'] ?? '')),
            'slug'               => strtolower(trim((string) ($_POST['slug'] ?? ''))),
            'descricao'          => trim((string) ($_POST['descricao'] ?? '')),
            'preco_kz'           => trim((string) ($_POST['preco_kz'] ?? '0')),
            'periodicidade_dias' => (int) ($_POST['periodicidade_dias'] ?? 0),
            'limite_membros'     => trim((string) ($_POST['limite_membros'] ?? '')),
            'limite_users'       => trim((string) ($_POST['limite_users'] ?? '')),
            'limite_storage_mb'  => trim((string) ($_POST['limite_storage_mb'] ?? '')),
            'ativo'              => isset($_POST['ativo']) ? (int) $_POST['ativo'] : 1,
            'ordem'              => (int) ($_POST['ordem'] ?? 0),
        ];
    }
}