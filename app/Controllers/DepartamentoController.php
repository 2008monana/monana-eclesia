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

final class DepartamentoController extends Controller
{
    // ======================================================
    // LISTA
    // ======================================================

    public function index(): void
    {
        if (!Rbac::can('departamentos.ver')) {
            flash('error', 'Sem permissão para ver departamentos.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();
        $q        = trim((string) ($_GET['q'] ?? ''));

        $where  = ['d.igreja_id = :igreja_id'];
        $params = ['igreja_id' => $igrejaId];

        if ($q !== '') {
            $where[] = 'd.nome LIKE :q';
            $params['q'] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);

        $departamentos = Database::select(
            "SELECT d.*,
                    l.`nome` AS lider_nome,
                    v.`nome` AS vice_nome,
                    (SELECT COUNT(*) FROM `department_members` dm WHERE dm.department_id = d.id) AS total_membros
             FROM `departments` d
             LEFT JOIN `members` l ON l.id = d.lider_id
             LEFT JOIN `members` v ON v.id = d.vice_id
             WHERE {$whereSql}
             ORDER BY d.ativo DESC, d.nome ASC",
            $params
        );

        // Percentagens (para a barra)
        $totalMembros = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `members` WHERE `igreja_id` = :i AND `deletado_em` IS NULL',
            ['i' => $igrejaId]
        )['n'] ?? 0);

        View::render('departamentos/index', [
            'title'         => 'Departamentos — Monana Eclesia',
            'user'          => Auth::user(),
            'departamentos' => $departamentos,
            'q'             => $q,
            'totalMembros'  => max(1, $totalMembros),
        ], 'layouts/app');
    }

    // ======================================================
    // CRIAR
    // ======================================================

    public function create(): void
    {
        if (!Rbac::can('departamentos.criar')) {
            flash('error', 'Sem permissão para criar departamentos.');
            redirect('/departamentos');
        }

        View::render('departamentos/form', [
            'title'         => 'Novo Departamento — Monana Eclesia',
            'user'          => Auth::user(),
            'departamento'  => [],
            'membros'       => $this->listarMembros(),
            'errors'        => [],
        ], 'layouts/app');
    }

    public function store(): void
    {
        if (!Rbac::can('departamentos.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/departamentos');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->maxLen('nome', 120, 'Nome')
          ->maxLen('descricao', 255, 'Descrição')
          ->maxLen('cor', 20, 'Cor');

        if ($v->fails()) {
            View::render('departamentos/form', [
                'title'        => 'Novo Departamento — Monana Eclesia',
                'user'         => Auth::user(),
                'departamento' => $data,
                'membros'      => $this->listarMembros(),
                'errors'       => $v->errors(),
            ], 'layouts/app');
            return;
        }

        try {
            $id = Database::insert(
                'INSERT INTO `departments`
                    (`igreja_id`, `nome`, `descricao`, `cor`, `lider_id`, `vice_id`, `ativo`)
                 VALUES (:i, :nome, :desc, :cor, :lider, :vice, :ativo)',
                [
                    'i'      => Tenant::id(),
                    'nome'   => $data['nome'],
                    'desc'   => $data['descricao'] ?: null,
                    'cor'    => $data['cor'] ?: '#b8862b',
                    'lider'  => $data['lider_id'] ?: null,
                    'vice'   => $data['vice_id'] ?: null,
                    'ativo'  => $data['ativo'],
                ]
            );

            Audit::log('departamentos', 'criar', 'departments', (string) $id, null, $data);
            flash('success', 'Departamento criado com sucesso.');
            redirect('/departamentos/' . $id);
        } catch (\Throwable $e) {
            logger('error', 'Erro ao criar departamento', ['msg' => $e->getMessage()]);
            flash('error', 'Erro ao criar: ' . $e->getMessage());
            redirect('/departamentos/criar');
        }
    }

    // ======================================================
    // VER (membros do departamento)
    // ======================================================

    public function show(string $id): void
    {
        if (!Rbac::can('departamentos.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $dept = $this->buscarDepartamento((int) $id);
        if (!$dept) {
            flash('error', 'Departamento não encontrado.');
            redirect('/departamentos');
        }

        $membrosDoDept = Database::select(
            'SELECT m.*, dm.`funcao`, dm.`desde`
             FROM `members` m
             JOIN `department_members` dm ON dm.member_id = m.id
             WHERE dm.department_id = :d AND m.igreja_id = :i AND m.deletado_em IS NULL
             ORDER BY m.nome',
            ['d' => $dept['id'], 'i' => Tenant::id()]
        );

        $membrosDisponiveis = Database::select(
            'SELECT m.`id`, m.`nome`, m.`codigo`
             FROM `members` m
             WHERE m.`igreja_id` = :i AND m.`deletado_em` IS NULL
               AND m.id NOT IN (SELECT member_id FROM `department_members` WHERE department_id = :d)
             ORDER BY m.`nome`',
            ['i' => Tenant::id(), 'd' => $dept['id']]
        );

        View::render('departamentos/show', [
            'title'               => $dept['nome'] . ' — Monana Eclesia',
            'user'                => Auth::user(),
            'departamento'        => $dept,
            'membrosDoDept'       => $membrosDoDept,
            'membrosDisponiveis'  => $membrosDisponiveis,
        ], 'layouts/app');
    }

    // ======================================================
    // EDITAR
    // ======================================================

    public function edit(string $id): void
    {
        if (!Rbac::can('departamentos.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/departamentos');
        }

        $dept = $this->buscarDepartamento((int) $id);
        if (!$dept) {
            flash('error', 'Departamento não encontrado.');
            redirect('/departamentos');
        }

        View::render('departamentos/form', [
            'title'        => 'Editar Departamento — Monana Eclesia',
            'user'         => Auth::user(),
            'departamento' => $dept,
            'membros'      => $this->listarMembros(),
            'errors'       => [],
        ], 'layouts/app');
    }

    public function update(string $id): void
    {
        if (!Rbac::can('departamentos.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/departamentos');
        }

        $dept = $this->buscarDepartamento((int) $id);
        if (!$dept) {
            flash('error', 'Departamento não encontrado.');
            redirect('/departamentos');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->maxLen('nome', 120, 'Nome')
          ->maxLen('descricao', 255, 'Descrição')
          ->maxLen('cor', 20, 'Cor');

        if ($v->fails()) {
            View::render('departamentos/form', [
                'title'        => 'Editar Departamento — Monana Eclesia',
                'user'         => Auth::user(),
                'departamento' => array_merge($dept, $data),
                'membros'      => $this->listarMembros(),
                'errors'       => $v->errors(),
            ], 'layouts/app');
            return;
        }

        try {
            Database::execute(
                'UPDATE `departments` SET
                    `nome` = :nome,
                    `descricao` = :desc,
                    `cor` = :cor,
                    `lider_id` = :lider,
                    `vice_id` = :vice,
                    `ativo` = :ativo
                 WHERE `id` = :id AND `igreja_id` = :igreja_id',
                [
                    'id'        => $dept['id'],
                    'igreja_id' => Tenant::id(),
                    'nome'      => $data['nome'],
                    'desc'      => $data['descricao'] ?: null,
                    'cor'       => $data['cor'] ?: '#b8862b',
                    'lider'     => $data['lider_id'] ?: null,
                    'vice'      => $data['vice_id'] ?: null,
                    'ativo'     => $data['ativo'],
                ]
            );

            Audit::log('departamentos', 'editar', 'departments', (string) $dept['id'], $dept, $data);
            flash('success', 'Departamento atualizado.');
            redirect('/departamentos/' . $dept['id']);
        } catch (\Throwable $e) {
            logger('error', 'Erro ao editar departamento', ['msg' => $e->getMessage()]);
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/departamentos/' . $dept['id'] . '/editar');
        }
    }

    // ======================================================
    // ELIMINAR (desativar — `ativo = 0`)
    // ======================================================

    public function destroy(string $id): void
    {
        if (!Rbac::can('departamentos.eliminar')) {
            flash('error', 'Sem permissão.');
            redirect('/departamentos');
        }

        $dept = $this->buscarDepartamento((int) $id);
        if (!$dept) {
            flash('error', 'Departamento não encontrado.');
            redirect('/departamentos');
        }

        Database::execute(
            'UPDATE `departments` SET `ativo` = 0 WHERE `id` = :id AND `igreja_id` = :i',
            ['id' => $dept['id'], 'i' => Tenant::id()]
        );

        Audit::log('departamentos', 'desativar', 'departments', (string) $dept['id'], $dept, ['ativo' => 0]);
        flash('success', 'Departamento desativado.');
        redirect('/departamentos');
    }

    // ======================================================
    // ASSOCIAR / DESASSOCIAR MEMBROS
    // ======================================================

    public function addMembro(string $id): void
    {
        if (!Rbac::can('departamentos.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/departamentos');
        }

        $dept = $this->buscarDepartamento((int) $id);
        if (!$dept) {
            flash('error', 'Departamento não encontrado.');
            redirect('/departamentos');
        }

        $memberId = (int) ($_POST['member_id'] ?? 0);
        $funcao   = trim((string) ($_POST['funcao'] ?? ''));
        $desde    = (string) ($_POST['desde'] ?? '');

        if (!$memberId) {
            flash('error', 'Escolha um membro.');
            redirect('/departamentos/' . $dept['id']);
        }

        $membro = Database::first(
            'SELECT id, nome FROM `members`
             WHERE id = :id AND igreja_id = :i AND deletado_em IS NULL LIMIT 1',
            ['id' => $memberId, 'i' => Tenant::id()]
        );
        if (!$membro) {
            flash('error', 'Membro inválido.');
            redirect('/departamentos/' . $dept['id']);
        }

        try {
            Database::execute(
                'INSERT INTO `department_members`
                    (`department_id`, `member_id`, `funcao`, `desde`)
                 VALUES (:d, :m, :f, :desde)
                 ON DUPLICATE KEY UPDATE `funcao` = VALUES(`funcao`), `desde` = VALUES(`desde`)',
                [
                    'd'      => $dept['id'],
                    'm'      => $memberId,
                    'f'      => $funcao ?: null,
                    'desde'  => $desde ?: date('Y-m-d'),
                ]
            );

            Audit::log('departamentos', 'add_membro', 'department_members', (string) $dept['id'], null, [
                'member_id' => $memberId,
                'funcao'    => $funcao,
            ]);
            flash('success', 'Membro associado ao departamento.');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
        }

        redirect('/departamentos/' . $dept['id']);
    }

    public function removeMembro(string $id, string $memberId): void
    {
        if (!Rbac::can('departamentos.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/departamentos');
        }

        $dept = $this->buscarDepartamento((int) $id);
        if (!$dept) {
            flash('error', 'Departamento não encontrado.');
            redirect('/departamentos');
        }

        Database::execute(
            'DELETE FROM `department_members`
             WHERE `department_id` = :d AND `member_id` = :m',
            ['d' => $dept['id'], 'm' => (int) $memberId]
        );

        Audit::log('departamentos', 'remove_membro', 'department_members', (string) $dept['id'], [
            'member_id' => (int) $memberId,
        ], null);

        flash('success', 'Membro removido do departamento.');
        redirect('/departamentos/' . $dept['id']);
    }

    // ======================================================
    // HELPERS
    // ======================================================

    private function buscarDepartamento(int $id): ?array
    {
        return Database::first(
            'SELECT * FROM `departments`
             WHERE `id` = :id AND `igreja_id` = :i LIMIT 1',
            ['id' => $id, 'i' => Tenant::id()]
        );
    }

    private function listarMembros(): array
    {
        return Database::select(
            'SELECT `id`, `nome`, `codigo` FROM `members`
             WHERE `igreja_id` = :i AND `deletado_em` IS NULL
             ORDER BY `nome`',
            ['i' => Tenant::id()]
        );
    }

    private function dadosDoFormulario(): array
    {
        return [
            'nome'      => trim((string) ($_POST['nome'] ?? '')),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'cor'       => trim((string) ($_POST['cor'] ?? '#b8862b')),
            'lider_id'  => (int) ($_POST['lider_id'] ?? 0) ?: null,
            'vice_id'   => (int) ($_POST['vice_id'] ?? 0) ?: null,
            'ativo'     => isset($_POST['ativo']) ? (int) $_POST['ativo'] : 1,
        ];
    }
}