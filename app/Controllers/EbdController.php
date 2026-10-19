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

final class EbdController extends Controller
{
    // ======================================================
    // LISTA
    // ======================================================

    public function index(): void
    {
        if (!Rbac::can('ebd.ver')) {
            flash('error', 'Sem permissão para ver EBD.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();
        $q        = trim((string) ($_GET['q'] ?? ''));

        $where  = ['c.igreja_id = :i'];
        $params = ['i' => $igrejaId];

        if ($q !== '') {
            $where[] = 'c.nome LIKE :q';
            $params['q'] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);

        $classes = Database::select(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM `ebd_students` s WHERE s.class_id = c.id) AS total_alunos,
                    (SELECT COUNT(*) FROM `ebd_teachers` t WHERE t.class_id = c.id) AS total_professores
             FROM `ebd_classes` c
             WHERE {$whereSql}
             ORDER BY c.ativo DESC, c.nome ASC",
            $params
        );

        View::render('ebd/index', [
            'title'   => 'EBD — Monana Eclesia',
            'user'    => Auth::user(),
            'classes' => $classes,
            'q'       => $q,
        ], 'layouts/app');
    }

    // ======================================================
    // CRIAR
    // ======================================================

    public function create(): void
    {
        if (!Rbac::can('ebd.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/ebd');
        }

        View::render('ebd/form', [
            'title'   => 'Nova Classe EBD — Monana Eclesia',
            'user'    => Auth::user(),
            'classe'  => [],
            'errors'  => [],
        ], 'layouts/app');
    }

    public function store(): void
    {
        if (!Rbac::can('ebd.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/ebd');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->maxLen('nome', 120, 'Nome')
          ->maxLen('faixa_etaria', 60, 'Faixa etária')
          ->maxLen('sala', 60, 'Sala');

        if ($v->fails()) {
            View::render('ebd/form', [
                'title'  => 'Nova Classe EBD — Monana Eclesia',
                'user'   => Auth::user(),
                'classe' => $data,
                'errors' => $v->errors(),
            ], 'layouts/app');
            return;
        }

        try {
            $id = Database::insert(
                'INSERT INTO `ebd_classes`
                    (`igreja_id`, `nome`, `faixa_etaria`, `sala`, `ativo`)
                 VALUES (:i, :nome, :faixa, :sala, :ativo)',
                [
                    'i'     => Tenant::id(),
                    'nome'  => $data['nome'],
                    'faixa' => $data['faixa_etaria'] ?: null,
                    'sala'  => $data['sala'] ?: null,
                    'ativo' => $data['ativo'],
                ]
            );

            Audit::log('ebd', 'criar', 'ebd_classes', (string) $id, null, $data);
            flash('success', 'Classe EBD criada.');
            redirect('/ebd/' . $id);
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/ebd/criar');
        }
    }

    // ======================================================
    // VER
    // ======================================================

    public function show(string $id): void
    {
        if (!Rbac::can('ebd.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $classe = $this->buscarClasse((int) $id);
        if (!$classe) {
            flash('error', 'Classe não encontrada.');
            redirect('/ebd');
        }

        $alunos = Database::select(
            'SELECT m.*, s.`desde`
             FROM `members` m
             JOIN `ebd_students` s ON s.member_id = m.id
             WHERE s.class_id = :c AND m.igreja_id = :i AND m.deletado_em IS NULL
             ORDER BY m.nome',
            ['c' => $classe['id'], 'i' => Tenant::id()]
        );

        $professores = Database::select(
            'SELECT m.*, t.`desde`
             FROM `members` m
             JOIN `ebd_teachers` t ON t.member_id = m.id
             WHERE t.class_id = :c AND m.igreja_id = :i AND m.deletado_em IS NULL
             ORDER BY m.nome',
            ['c' => $classe['id'], 'i' => Tenant::id()]
        );

        // Membros disponíveis (não estão na classe como aluno nem professor)
        $membrosDisponiveis = Database::select(
            'SELECT m.`id`, m.`nome`, m.`codigo`
             FROM `members` m
             WHERE m.`igreja_id` = :i AND m.`deletado_em` IS NULL
               AND m.id NOT IN (SELECT member_id FROM `ebd_students` WHERE class_id = :c1)
               AND m.id NOT IN (SELECT member_id FROM `ebd_teachers` WHERE class_id = :c2)
             ORDER BY m.`nome`',
            ['i' => Tenant::id(), 'c1' => $classe['id'], 'c2' => $classe['id']]
        );

        View::render('ebd/show', [
            'title'               => $classe['nome'] . ' — EBD',
            'user'                => Auth::user(),
            'classe'              => $classe,
            'alunos'              => $alunos,
            'professores'         => $professores,
            'membrosDisponiveis'  => $membrosDisponiveis,
        ], 'layouts/app');
    }

    // ======================================================
    // EDITAR
    // ======================================================

    public function edit(string $id): void
    {
        if (!Rbac::can('ebd.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/ebd');
        }

        $classe = $this->buscarClasse((int) $id);
        if (!$classe) {
            flash('error', 'Classe não encontrada.');
            redirect('/ebd');
        }

        View::render('ebd/form', [
            'title'  => 'Editar Classe EBD — Monana Eclesia',
            'user'   => Auth::user(),
            'classe' => $classe,
            'errors' => [],
        ], 'layouts/app');
    }

    public function update(string $id): void
    {
        if (!Rbac::can('ebd.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/ebd');
        }

        $classe = $this->buscarClasse((int) $id);
        if (!$classe) {
            flash('error', 'Classe não encontrada.');
            redirect('/ebd');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->maxLen('nome', 120, 'Nome')
          ->maxLen('faixa_etaria', 60, 'Faixa etária')
          ->maxLen('sala', 60, 'Sala');

        if ($v->fails()) {
            View::render('ebd/form', [
                'title'  => 'Editar Classe EBD — Monana Eclesia',
                'user'   => Auth::user(),
                'classe' => array_merge($classe, $data),
                'errors' => $v->errors(),
            ], 'layouts/app');
            return;
        }

        try {
            Database::execute(
                'UPDATE `ebd_classes` SET
                    `nome` = :nome,
                    `faixa_etaria` = :faixa,
                    `sala` = :sala,
                    `ativo` = :ativo
                 WHERE `id` = :id AND `igreja_id` = :i',
                [
                    'id'    => $classe['id'],
                    'i'     => Tenant::id(),
                    'nome'  => $data['nome'],
                    'faixa' => $data['faixa_etaria'] ?: null,
                    'sala'  => $data['sala'] ?: null,
                    'ativo' => $data['ativo'],
                ]
            );

            Audit::log('ebd', 'editar', 'ebd_classes', (string) $classe['id'], $classe, $data);
            flash('success', 'Classe atualizada.');
            redirect('/ebd/' . $classe['id']);
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/ebd/' . $classe['id'] . '/editar');
        }
    }

    // ======================================================
    // ELIMINAR (desativar)
    // ======================================================

    public function destroy(string $id): void
    {
        if (!Rbac::can('ebd.eliminar')) {
            flash('error', 'Sem permissão.');
            redirect('/ebd');
        }

        $classe = $this->buscarClasse((int) $id);
        if (!$classe) {
            flash('error', 'Classe não encontrada.');
            redirect('/ebd');
        }

        Database::execute(
            'UPDATE `ebd_classes` SET `ativo` = 0 WHERE `id` = :id AND `igreja_id` = :i',
            ['id' => $classe['id'], 'i' => Tenant::id()]
        );

        Audit::log('ebd', 'desativar', 'ebd_classes', (string) $classe['id'], $classe, ['ativo' => 0]);
        flash('success', 'Classe desativada.');
        redirect('/ebd');
    }

    // ======================================================
    // ALUNOS / PROFESSORES
    // ======================================================

    public function addAluno(string $id): void
    {
        $classe = $this->buscarClasse((int) $id);
        if (!$classe) {
            flash('error', 'Classe não encontrada.');
            redirect('/ebd');
        }
        if (!Rbac::can('ebd.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/ebd/' . $classe['id']);
        }

        $memberId = (int) ($_POST['member_id'] ?? 0);
        if (!$memberId) {
            flash('error', 'Escolha um membro.');
            redirect('/ebd/' . $classe['id']);
        }

        try {
            Database::execute(
                'INSERT INTO `ebd_students` (`class_id`, `member_id`, `desde`)
                 VALUES (:c, :m, CURDATE())
                 ON DUPLICATE KEY UPDATE `desde` = VALUES(`desde`)',
                ['c' => $classe['id'], 'm' => $memberId]
            );
            Audit::log('ebd', 'add_aluno', 'ebd_students', (string) $classe['id'], null, ['member_id' => $memberId]);
            flash('success', 'Aluno adicionado.');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
        }
        redirect('/ebd/' . $classe['id']);
    }

    public function removeAluno(string $id, string $memberId): void
    {
        if (!Rbac::can('ebd.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/ebd');
        }
        Database::execute(
            'DELETE FROM `ebd_students` WHERE `class_id` = :c AND `member_id` = :m',
            ['c' => (int) $id, 'm' => (int) $memberId]
        );
        Audit::log('ebd', 'remove_aluno', 'ebd_students', $id, ['member_id' => (int) $memberId], null);
        flash('success', 'Aluno removido.');
        redirect('/ebd/' . $id);
    }

    public function addProfessor(string $id): void
    {
        $classe = $this->buscarClasse((int) $id);
        if (!$classe) {
            flash('error', 'Classe não encontrada.');
            redirect('/ebd');
        }
        if (!Rbac::can('ebd.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/ebd/' . $classe['id']);
        }

        $memberId = (int) ($_POST['member_id'] ?? 0);
        if (!$memberId) {
            flash('error', 'Escolha um membro.');
            redirect('/ebd/' . $classe['id']);
        }

        try {
            Database::execute(
                'INSERT INTO `ebd_teachers` (`class_id`, `member_id`, `desde`)
                 VALUES (:c, :m, CURDATE())
                 ON DUPLICATE KEY UPDATE `desde` = VALUES(`desde`)',
                ['c' => $classe['id'], 'm' => $memberId]
            );
            Audit::log('ebd', 'add_professor', 'ebd_teachers', (string) $classe['id'], null, ['member_id' => $memberId]);
            flash('success', 'Professor adicionado.');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
        }
        redirect('/ebd/' . $classe['id']);
    }

    public function removeProfessor(string $id, string $memberId): void
    {
        if (!Rbac::can('ebd.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/ebd');
        }
        Database::execute(
            'DELETE FROM `ebd_teachers` WHERE `class_id` = :c AND `member_id` = :m',
            ['c' => (int) $id, 'm' => (int) $memberId]
        );
        Audit::log('ebd', 'remove_professor', 'ebd_teachers', $id, ['member_id' => (int) $memberId], null);
        flash('success', 'Professor removido.');
        redirect('/ebd/' . $id);
    }

    // ======================================================
    // HELPERS
    // ======================================================

    private function buscarClasse(int $id): ?array
    {
        return Database::first(
            'SELECT * FROM `ebd_classes` WHERE `id` = :id AND `igreja_id` = :i LIMIT 1',
            ['id' => $id, 'i' => Tenant::id()]
        );
    }

    private function dadosDoFormulario(): array
    {
        return [
            'nome'         => trim((string) ($_POST['nome'] ?? '')),
            'faixa_etaria' => trim((string) ($_POST['faixa_etaria'] ?? '')),
            'sala'         => trim((string) ($_POST['sala'] ?? '')),
            'ativo'        => isset($_POST['ativo']) ? (int) $_POST['ativo'] : 1,
        ];
    }
}