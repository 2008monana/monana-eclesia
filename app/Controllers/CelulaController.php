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

final class CelulaController extends Controller
{
    private const DIAS = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'];

    // ======================================================
    // LISTA
    // ======================================================

    public function index(): void
    {
        if (!Rbac::can('celulas.ver')) {
            flash('error', 'Sem permissão para ver células.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();
        $q        = trim((string) ($_GET['q'] ?? ''));

        $where  = ['c.igreja_id = :igreja_id'];
        $params = ['igreja_id' => $igrejaId];

        if ($q !== '') {
            $where[] = 'c.nome LIKE :q';
            $params['q'] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);

        $celulas = Database::select(
            "SELECT c.*,
                    l.`nome` AS lider_nome,
                    a.`nome` AS anfitriao_nome,
                    (SELECT COUNT(*) FROM `cell_members` cm WHERE cm.cell_id = c.id) AS total_membros
             FROM `cells` c
             LEFT JOIN `members` l ON l.id = c.lider_id
             LEFT JOIN `members` a ON a.id = c.anfitriao_id
             WHERE {$whereSql}
             ORDER BY c.ativo DESC, c.nome ASC",
            $params
        );

        View::render('celulas/index', [
            'title'   => 'Células — Monana Eclesia',
            'user'    => Auth::user(),
            'celulas' => $celulas,
            'q'       => $q,
        ], 'layouts/app');
    }

    // ======================================================
    // CRIAR
    // ======================================================

    public function create(): void
    {
        if (!Rbac::can('celulas.criar')) {
            flash('error', 'Sem permissão para criar células.');
            redirect('/celulas');
        }

        View::render('celulas/form', [
            'title'   => 'Nova Célula — Monana Eclesia',
            'user'    => Auth::user(),
            'celula'  => [],
            'membros' => $this->listarMembros(),
            'dias'    => self::DIAS,
            'errors'  => [],
        ], 'layouts/app');
    }

    public function store(): void
    {
        if (!Rbac::can('celulas.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/celulas');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->maxLen('nome', 150, 'Nome')
          ->maxLen('endereco', 255, 'Endereço')
          ->in('dia_semana', array_merge([''], self::DIAS), 'Dia da semana');

        if ($v->fails()) {
            View::render('celulas/form', [
                'title'   => 'Nova Célula — Monana Eclesia',
                'user'    => Auth::user(),
                'celula'  => $data,
                'membros' => $this->listarMembros(),
                'dias'    => self::DIAS,
                'errors'  => $v->errors(),
            ], 'layouts/app');
            return;
        }

        try {
            $id = Database::insert(
                'INSERT INTO `cells`
                    (`igreja_id`, `nome`, `lider_id`, `anfitriao_id`, `endereco`,
                     `dia_semana`, `hora`, `ativo`)
                 VALUES (:i, :nome, :lider, :anfitriao, :endereco,
                         :dia, :hora, :ativo)',
                [
                    'i'         => Tenant::id(),
                    'nome'      => $data['nome'],
                    'lider'     => $data['lider_id'] ?: null,
                    'anfitriao' => $data['anfitriao_id'] ?: null,
                    'endereco'  => $data['endereco'] ?: null,
                    'dia'       => $data['dia_semana'] ?: null,
                    'hora'      => $data['hora'] ?: null,
                    'ativo'     => $data['ativo'],
                ]
            );

            Audit::log('celulas', 'criar', 'cells', (string) $id, null, $data);
            flash('success', 'Célula criada com sucesso.');
            redirect('/celulas/' . $id);
        } catch (\Throwable $e) {
            logger('error', 'Erro ao criar célula', ['msg' => $e->getMessage()]);
            flash('error', 'Erro ao criar: ' . $e->getMessage());
            redirect('/celulas/criar');
        }
    }

    // ======================================================
    // VER
    // ======================================================

    public function show(string $id): void
    {
        if (!Rbac::can('celulas.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $celula = $this->buscarCelula((int) $id);
        if (!$celula) {
            flash('error', 'Célula não encontrada.');
            redirect('/celulas');
        }

        $membrosDaCelula = Database::select(
            'SELECT m.*, cm.`desde`
             FROM `members` m
             JOIN `cell_members` cm ON cm.member_id = m.id
             WHERE cm.cell_id = :c AND m.igreja_id = :i AND m.deletado_em IS NULL
             ORDER BY m.nome',
            ['c' => $celula['id'], 'i' => Tenant::id()]
        );

        $membrosDisponiveis = Database::select(
            'SELECT m.`id`, m.`nome`, m.`codigo`
             FROM `members` m
             WHERE m.`igreja_id` = :i AND m.`deletado_em` IS NULL
               AND m.id NOT IN (SELECT member_id FROM `cell_members` WHERE cell_id = :c)
             ORDER BY m.`nome`',
            ['i' => Tenant::id(), 'c' => $celula['id']]
        );

        $reunioes = Database::select(
            'SELECT * FROM `cell_meetings`
             WHERE `cell_id` = :c
             ORDER BY `data` DESC, `id` DESC
             LIMIT 30',
            ['c' => $celula['id']]
        );

        View::render('celulas/show', [
            'title'              => $celula['nome'] . ' — Monana Eclesia',
            'user'               => Auth::user(),
            'celula'             => $celula,
            'membrosDaCelula'    => $membrosDaCelula,
            'membrosDisponiveis' => $membrosDisponiveis,
            'reunioes'           => $reunioes,
            'dias'               => self::DIAS,
        ], 'layouts/app');
    }

    // ======================================================
    // EDITAR
    // ======================================================

    public function edit(string $id): void
    {
        if (!Rbac::can('celulas.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/celulas');
        }

        $celula = $this->buscarCelula((int) $id);
        if (!$celula) {
            flash('error', 'Célula não encontrada.');
            redirect('/celulas');
        }

        View::render('celulas/form', [
            'title'   => 'Editar Célula — Monana Eclesia',
            'user'    => Auth::user(),
            'celula'  => $celula,
            'membros' => $this->listarMembros(),
            'dias'    => self::DIAS,
            'errors'  => [],
        ], 'layouts/app');
    }

    public function update(string $id): void
    {
        if (!Rbac::can('celulas.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/celulas');
        }

        $celula = $this->buscarCelula((int) $id);
        if (!$celula) {
            flash('error', 'Célula não encontrada.');
            redirect('/celulas');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->maxLen('nome', 150, 'Nome')
          ->maxLen('endereco', 255, 'Endereço')
          ->in('dia_semana', array_merge([''], self::DIAS), 'Dia da semana');

        if ($v->fails()) {
            View::render('celulas/form', [
                'title'   => 'Editar Célula — Monana Eclesia',
                'user'    => Auth::user(),
                'celula'  => array_merge($celula, $data),
                'membros' => $this->listarMembros(),
                'dias'    => self::DIAS,
                'errors'  => $v->errors(),
            ], 'layouts/app');
            return;
        }

        try {
            Database::execute(
                'UPDATE `cells` SET
                    `nome` = :nome,
                    `lider_id` = :lider,
                    `anfitriao_id` = :anfitriao,
                    `endereco` = :endereco,
                    `dia_semana` = :dia,
                    `hora` = :hora,
                    `ativo` = :ativo
                 WHERE `id` = :id AND `igreja_id` = :igreja_id',
                [
                    'id'        => $celula['id'],
                    'igreja_id' => Tenant::id(),
                    'nome'      => $data['nome'],
                    'lider'     => $data['lider_id'] ?: null,
                    'anfitriao' => $data['anfitriao_id'] ?: null,
                    'endereco'  => $data['endereco'] ?: null,
                    'dia'       => $data['dia_semana'] ?: null,
                    'hora'      => $data['hora'] ?: null,
                    'ativo'     => $data['ativo'],
                ]
            );

            Audit::log('celulas', 'editar', 'cells', (string) $celula['id'], $celula, $data);
            flash('success', 'Célula atualizada.');
            redirect('/celulas/' . $celula['id']);
        } catch (\Throwable $e) {
            logger('error', 'Erro ao editar célula', ['msg' => $e->getMessage()]);
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/celulas/' . $celula['id'] . '/editar');
        }
    }

    // ======================================================
    // ELIMINAR (desativar)
    // ======================================================

    public function destroy(string $id): void
    {
        if (!Rbac::can('celulas.eliminar')) {
            flash('error', 'Sem permissão.');
            redirect('/celulas');
        }

        $celula = $this->buscarCelula((int) $id);
        if (!$celula) {
            flash('error', 'Célula não encontrada.');
            redirect('/celulas');
        }

        Database::execute(
            'UPDATE `cells` SET `ativo` = 0 WHERE `id` = :id AND `igreja_id` = :i',
            ['id' => $celula['id'], 'i' => Tenant::id()]
        );

        Audit::log('celulas', 'desativar', 'cells', (string) $celula['id'], $celula, ['ativo' => 0]);
        flash('success', 'Célula desativada.');
        redirect('/celulas');
    }

    // ======================================================
    // MEMBROS DA CÉLULA
    // ======================================================

    public function addMembro(string $id): void
    {
        if (!Rbac::can('celulas.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/celulas');
        }

        $celula = $this->buscarCelula((int) $id);
        if (!$celula) {
            flash('error', 'Célula não encontrada.');
            redirect('/celulas');
        }

        $memberId = (int) ($_POST['member_id'] ?? 0);
        $desde    = (string) ($_POST['desde'] ?? '');

        if (!$memberId) {
            flash('error', 'Escolha um membro.');
            redirect('/celulas/' . $celula['id']);
        }

        try {
            Database::execute(
                'INSERT INTO `cell_members` (`cell_id`, `member_id`, `desde`)
                 VALUES (:c, :m, :desde)
                 ON DUPLICATE KEY UPDATE `desde` = VALUES(`desde`)',
                [
                    'c'      => $celula['id'],
                    'm'      => $memberId,
                    'desde'  => $desde ?: date('Y-m-d'),
                ]
            );

            Audit::log('celulas', 'add_membro', 'cell_members', (string) $celula['id'], null, [
                'member_id' => $memberId,
            ]);
            flash('success', 'Membro associado à célula.');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
        }

        redirect('/celulas/' . $celula['id']);
    }

    public function removeMembro(string $id, string $memberId): void
    {
        if (!Rbac::can('celulas.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/celulas');
        }

        $celula = $this->buscarCelula((int) $id);
        if (!$celula) {
            flash('error', 'Célula não encontrada.');
            redirect('/celulas');
        }

        Database::execute(
            'DELETE FROM `cell_members` WHERE `cell_id` = :c AND `member_id` = :m',
            ['c' => $celula['id'], 'm' => (int) $memberId]
        );

        Audit::log('celulas', 'remove_membro', 'cell_members', (string) $celula['id'], [
            'member_id' => (int) $memberId,
        ], null);

        flash('success', 'Membro removido da célula.');
        redirect('/celulas/' . $celula['id']);
    }

    // ======================================================
    // REUNIÕES
    // ======================================================

    public function addReuniao(string $id): void
    {
        if (!Rbac::can('celulas.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/celulas');
        }

        $celula = $this->buscarCelula((int) $id);
        if (!$celula) {
            flash('error', 'Célula não encontrada.');
            redirect('/celulas');
        }

        $data        = (string) ($_POST['data'] ?? '');
        $tema        = trim((string) ($_POST['tema'] ?? ''));
        $presentes   = max(0, (int) ($_POST['presentes'] ?? 0));
        $observacoes = trim((string) ($_POST['observacoes'] ?? ''));

        if (!strtotime($data)) {
            flash('error', 'Data inválida.');
            redirect('/celulas/' . $celula['id']);
        }

        Database::execute(
            'INSERT INTO `cell_meetings`
                (`cell_id`, `data`, `tema`, `presentes`, `observacoes`)
             VALUES (:c, :data, :tema, :pres, :obs)',
            [
                'c'    => $celula['id'],
                'data' => $data,
                'tema' => $tema ?: null,
                'pres' => $presentes,
                'obs'  => $observacoes ?: null,
            ]
        );

        Audit::log('celulas', 'add_reuniao', 'cell_meetings', (string) $celula['id'], null, [
            'data'      => $data,
            'tema'      => $tema,
            'presentes' => $presentes,
        ]);

        flash('success', 'Reunião registada.');
        redirect('/celulas/' . $celula['id']);
    }

    public function removeReuniao(string $id, string $rid): void
    {
        if (!Rbac::can('celulas.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/celulas');
        }

        $celula = $this->buscarCelula((int) $id);
        if (!$celula) {
            flash('error', 'Célula não encontrada.');
            redirect('/celulas');
        }

        $reuniao = Database::first(
            'SELECT * FROM `cell_meetings` WHERE id = :id AND cell_id = :c LIMIT 1',
            ['id' => (int) $rid, 'c' => $celula['id']]
        );
        if (!$reuniao) {
            flash('error', 'Reunião não encontrada.');
            redirect('/celulas/' . $celula['id']);
        }

        Database::execute('DELETE FROM `cell_meetings` WHERE id = :id', ['id' => $reuniao['id']]);

        Audit::log('celulas', 'remove_reuniao', 'cell_meetings', (string) $reuniao['id'], $reuniao, null);
        flash('success', 'Reunião eliminada.');
        redirect('/celulas/' . $celula['id']);
    }

    // ======================================================
    // HELPERS
    // ======================================================

    private function buscarCelula(int $id): ?array
    {
        return Database::first(
            'SELECT * FROM `cells` WHERE `id` = :id AND `igreja_id` = :i LIMIT 1',
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
            'nome'         => trim((string) ($_POST['nome'] ?? '')),
            'lider_id'     => (int) ($_POST['lider_id'] ?? 0) ?: null,
            'anfitriao_id' => (int) ($_POST['anfitriao_id'] ?? 0) ?: null,
            'endereco'     => trim((string) ($_POST['endereco'] ?? '')),
            'dia_semana'   => (string) ($_POST['dia_semana'] ?? ''),
            'hora'         => (string) ($_POST['hora'] ?? ''),
            'ativo'        => isset($_POST['ativo']) ? (int) $_POST['ativo'] : 1,
        ];
    }
}