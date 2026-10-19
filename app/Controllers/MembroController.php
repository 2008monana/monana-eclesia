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

final class MembroController extends Controller
{
    private const PER_PAGE = 10;

    // ======================================================
    // LISTA
    // ======================================================

    public function index(): void
    {
        if (!Rbac::can('membros.ver')) {
            flash('error', 'Sem permissão para ver membros.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();

        $q          = trim((string) ($_GET['q'] ?? ''));
        $dep        = (string) ($_GET['dep'] ?? '');
        $status     = (string) ($_GET['status'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $offset     = ($page - 1) * self::PER_PAGE;

        $where  = ['m.igreja_id = :igreja_id', 'm.deletado_em IS NULL'];
        $params = ['igreja_id' => $igrejaId];

        if ($q !== '') {
            $where[] = '(m.nome LIKE :q OR m.telefone LIKE :q OR m.codigo LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($status !== '' && in_array($status, MEMBER_STATUS, true)) {
            $where[] = 'm.status = :status';
            $params['status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        // Total (para paginação)
        $total = (int) (Database::first(
            "SELECT COUNT(*) AS n FROM `members` m WHERE {$whereSql}",
            $params
        )['n'] ?? 0);

        // Página
        $membros = Database::select(
            "SELECT m.*,
                    (SELECT d.nome FROM `departments` d
                     JOIN `department_members` dm ON dm.department_id = d.id
                     WHERE dm.member_id = m.id
                     LIMIT 1) AS primeiro_departamento
             FROM `members` m
             WHERE {$whereSql}
             ORDER BY m.nome ASC
             LIMIT " . (int) self::PER_PAGE . " OFFSET " . (int) $offset,
            $params
        );

        $pages = max(1, (int) ceil($total / self::PER_PAGE));

        // Departamentos para o filtro
        $departamentos = Database::select(
            'SELECT `id`, `nome`, `cor` FROM `departments`
             WHERE `igreja_id` = :i AND `ativo` = 1 ORDER BY `nome`',
            ['i' => $igrejaId]
        );

        // Filtro de departamento (feito no PHP para simplificar)
        if ($dep !== '') {
            $membros = array_values(array_filter($membros, fn ($m) => $m['primeiro_departamento'] === $dep));
        }

        View::render('membros/index', [
            'title'          => 'Membros — Monana Eclesia',
            'user'           => Auth::user(),
            'membros'        => $membros,
            'departamentos'  => $departamentos,
            'q'              => $q,
            'dep'            => $dep,
            'status'         => $status,
            'page'           => $page,
            'pages'          => $pages,
            'total'          => $total,
            'perPage'        => self::PER_PAGE,
        ], 'layouts/app');
    }

    // ======================================================
    // CRIAR
    // ======================================================

    public function create(): void
    {
        if (!Rbac::can('membros.criar')) {
            flash('error', 'Sem permissão para criar membros.');
            redirect('/membros');
        }

        $departamentos = $this->listarDepartamentos();

        View::render('membros/form', [
            'title'         => 'Novo Membro — Monana Eclesia',
            'user'          => Auth::user(),
            'membro'        => [],
            'departamentos' => $departamentos,
            'errors'        => [],
        ], 'layouts/app');
    }

    public function store(): void
    {
        if (!Rbac::can('membros.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/membros');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->required('codigo', 'Código')
          ->maxLen('nome', 180, 'Nome')
          ->maxLen('codigo', 20, 'Código')
          ->email('email', 'E-mail')
          ->date('data_nascimento', 'Data de nascimento')
          ->date('data_conversao', 'Data de conversão')
          ->date('data_baptismo', 'Data de batismo')
          ->date('membro_desde', 'Membro desde')
          ->in('genero', ['M', 'F', 'outro'], 'Género')
          ->in('estado_civil', ['solteiro', 'casado', 'divorciado', 'viuvo', 'uniao_facto'], 'Estado civil')
          ->in('status', MEMBER_STATUS, 'Status');

        if ($v->fails()) {
            $departamentos = $this->listarDepartamentos();
            View::render('membros/form', [
                'title'         => 'Novo Membro — Monana Eclesia',
                'user'          => Auth::user(),
                'membro'        => $data,
                'departamentos' => $departamentos,
                'errors'        => $v->errors(),
            ], 'layouts/app');
            return;
        }

        $igrejaId = Tenant::id();
        $user     = Auth::user();

        try {
            $id = Database::insert(
                'INSERT INTO `members`
                    (`igreja_id`, `codigo`, `nome`, `telefone`, `email`, `data_nascimento`,
                     `genero`, `estado_civil`, `endereco`, `cidade`, `provincia`, `cargo`,
                     `status`, `data_conversao`, `data_baptismo`, `membro_desde`,
                     `observacoes`, `criado_por`)
                 VALUES
                    (:igreja_id, :codigo, :nome, :telefone, :email, :data_nascimento,
                     :genero, :estado_civil, :endereco, :cidade, :provincia, :cargo,
                     :status, :data_conversao, :data_baptismo, :membro_desde,
                     :observacoes, :criado_por)',
                [
                    'igreja_id'       => $igrejaId,
                    'codigo'          => $data['codigo'],
                    'nome'            => $data['nome'],
                    'telefone'        => $data['telefone'] ?: null,
                    'email'           => $data['email'] ?: null,
                    'data_nascimento' => $data['data_nascimento'] ?: null,
                    'genero'          => $data['genero'] ?: null,
                    'estado_civil'    => $data['estado_civil'] ?: null,
                    'endereco'        => $data['endereco'] ?: null,
                    'cidade'          => $data['cidade'] ?: null,
                    'provincia'       => $data['provincia'] ?: null,
                    'cargo'           => $data['cargo'] ?: null,
                    'status'          => $data['status'],
                    'data_conversao'  => $data['data_conversao'] ?: null,
                    'data_baptismo'   => $data['data_baptismo'] ?: null,
                    'membro_desde'    => $data['membro_desde'] ?: null,
                    'observacoes'     => $data['observacoes'] ?: null,
                    'criado_por'      => $user['id'] ?? null,
                ]
            );

            Audit::log('membros', 'criar', 'members', (string) $id, null, $data);
            flash('success', 'Membro criado com sucesso.');
            redirect('/membros/' . $id);
        } catch (\Throwable $e) {
            logger('error', 'Erro ao criar membro', ['msg' => $e->getMessage()]);
            flash('error', 'Erro ao criar membro: ' . $e->getMessage());
            redirect('/membros/criar');
        }
    }

    // ======================================================
    // PERFIL
    // ======================================================

    public function show(string $id): void
    {
        if (!Rbac::can('membros.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $membro = $this->buscarMembro((int) $id);
        if (!$membro) {
            flash('error', 'Membro não encontrado.');
            redirect('/membros');
        }

        $igrejaId = Tenant::id();

        // Departamentos do membro
        $departamentosDoMembro = Database::select(
            'SELECT d.*, dm.`funcao`, dm.`desde`
             FROM `departments` d
             JOIN `department_members` dm ON dm.department_id = d.id
             WHERE dm.member_id = :m AND d.igreja_id = :i
             ORDER BY d.nome',
            ['m' => $membro['id'], 'i' => $igrejaId]
        );

        // Células do membro
        $celulasDoMembro = Database::select(
            'SELECT c.*, cm.`desde`
             FROM `cells` c
             JOIN `cell_members` cm ON cm.cell_id = c.id
             WHERE cm.member_id = :m AND c.igreja_id = :i
             ORDER BY c.nome',
            ['m' => $membro['id'], 'i' => $igrejaId]
        );

        // Família
        $familia = Database::select(
            'SELECT mf.`relacao`, m.`id`, m.`nome`, m.`codigo`, m.`foto`
             FROM `member_families` mf
             JOIN `members` m ON m.id = mf.parente_id
             WHERE mf.member_id = :m AND m.deletado_em IS NULL',
            ['m' => $membro['id']]
        );

        // Contribuições (transações)
        $contribuicoes = Database::select(
            'SELECT t.`data`, t.`descricao`, t.`valor_kz`, t.`tipo`, c.`nome` AS categoria
             FROM `transactions` t
             LEFT JOIN `categories` c ON c.id = t.categoria_id
             WHERE t.member_id = :m AND t.igreja_id = :i
             ORDER BY t.`data` DESC LIMIT 20',
            ['m' => $membro['id'], 'i' => $igrejaId]
        );

        View::render('membros/perfil', [
            'title'                  => $membro['nome'] . ' — Monana Eclesia',
            'user'                   => Auth::user(),
            'membro'                 => $membro,
            'departamentosDoMembro'  => $departamentosDoMembro,
            'celulasDoMembro'        => $celulasDoMembro,
            'familia'                => $familia,
            'contribuicoes'          => $contribuicoes,
        ], 'layouts/app');
    }

    // ======================================================
    // EDITAR
    // ======================================================

    public function edit(string $id): void
    {
        if (!Rbac::can('membros.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/membros');
        }

        $membro = $this->buscarMembro((int) $id);
        if (!$membro) {
            flash('error', 'Membro não encontrado.');
            redirect('/membros');
        }

        View::render('membros/form', [
            'title'         => 'Editar Membro — Monana Eclesia',
            'user'          => Auth::user(),
            'membro'        => $membro,
            'departamentos' => $this->listarDepartamentos(),
            'errors'        => [],
        ], 'layouts/app');
    }

    public function update(string $id): void
    {
        if (!Rbac::can('membros.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/membros');
        }

        $membro = $this->buscarMembro((int) $id);
        if (!$membro) {
            flash('error', 'Membro não encontrado.');
            redirect('/membros');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->required('codigo', 'Código')
          ->maxLen('nome', 180, 'Nome')
          ->maxLen('codigo', 20, 'Código')
          ->email('email', 'E-mail')
          ->date('data_nascimento', 'Data de nascimento')
          ->date('data_conversao', 'Data de conversão')
          ->date('data_baptismo', 'Data de batismo')
          ->date('membro_desde', 'Membro desde')
          ->in('genero', ['M', 'F', 'outro'], 'Género')
          ->in('estado_civil', ['solteiro', 'casado', 'divorciado', 'viuvo', 'uniao_facto'], 'Estado civil')
          ->in('status', MEMBER_STATUS, 'Status');

        if ($v->fails()) {
            View::render('membros/form', [
                'title'         => 'Editar Membro — Monana Eclesia',
                'user'          => Auth::user(),
                'membro'        => array_merge($membro, $data),
                'departamentos' => $this->listarDepartamentos(),
                'errors'        => $v->errors(),
            ], 'layouts/app');
            return;
        }

        try {
            Database::execute(
                'UPDATE `members` SET
                    `codigo` = :codigo,
                    `nome` = :nome,
                    `telefone` = :telefone,
                    `email` = :email,
                    `data_nascimento` = :data_nascimento,
                    `genero` = :genero,
                    `estado_civil` = :estado_civil,
                    `endereco` = :endereco,
                    `cidade` = :cidade,
                    `provincia` = :provincia,
                    `cargo` = :cargo,
                    `status` = :status,
                    `data_conversao` = :data_conversao,
                    `data_baptismo` = :data_baptismo,
                    `membro_desde` = :membro_desde,
                    `observacoes` = :observacoes
                 WHERE `id` = :id AND `igreja_id` = :igreja_id',
                [
                    'id'              => $membro['id'],
                    'igreja_id'       => Tenant::id(),
                    'codigo'          => $data['codigo'],
                    'nome'            => $data['nome'],
                    'telefone'        => $data['telefone'] ?: null,
                    'email'           => $data['email'] ?: null,
                    'data_nascimento' => $data['data_nascimento'] ?: null,
                    'genero'          => $data['genero'] ?: null,
                    'estado_civil'    => $data['estado_civil'] ?: null,
                    'endereco'        => $data['endereco'] ?: null,
                    'cidade'          => $data['cidade'] ?: null,
                    'provincia'       => $data['provincia'] ?: null,
                    'cargo'           => $data['cargo'] ?: null,
                    'status'          => $data['status'],
                    'data_conversao'  => $data['data_conversao'] ?: null,
                    'data_baptismo'   => $data['data_baptismo'] ?: null,
                    'membro_desde'    => $data['membro_desde'] ?: null,
                    'observacoes'     => $data['observacoes'] ?: null,
                ]
            );

            Audit::log('membros', 'editar', 'members', (string) $membro['id'], $membro, $data);
            flash('success', 'Membro atualizado.');
            redirect('/membros/' . $membro['id']);
        } catch (\Throwable $e) {
            logger('error', 'Erro ao editar membro', ['msg' => $e->getMessage()]);
            flash('error', 'Erro ao editar: ' . $e->getMessage());
            redirect('/membros/' . $membro['id'] . '/editar');
        }
    }

    // ======================================================
    // ELIMINAR (soft delete)
    // ======================================================

    public function destroy(string $id): void
    {
        if (!Rbac::can('membros.eliminar')) {
            flash('error', 'Sem permissão para eliminar.');
            redirect('/membros');
        }

        $membro = $this->buscarMembro((int) $id);
        if (!$membro) {
            flash('error', 'Membro não encontrado.');
            redirect('/membros');
        }

        Database::execute(
            'UPDATE `members` SET `deletado_em` = NOW() WHERE `id` = :id AND `igreja_id` = :igreja_id',
            ['id' => $membro['id'], 'igreja_id' => Tenant::id()]
        );

        Audit::log('membros', 'eliminar', 'members', (string) $membro['id'], $membro, null);
        flash('success', 'Membro eliminado.');
        redirect('/membros');
    }

    // ======================================================
    // FAMÍLIA
    // ======================================================

    public function addFamilia(string $id): void
    {
        if (!Rbac::can('membros.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/membros');
        }

        $membro = $this->buscarMembro((int) $id);
        if (!$membro) {
            flash('error', 'Membro não encontrado.');
            redirect('/membros');
        }

        $parenteId = (int) ($_POST['parente_id'] ?? 0);
        $relacao   = (string) ($_POST['relacao'] ?? '');

        if (!$parenteId || !in_array($relacao, ['conjuge', 'filho', 'pai', 'mae', 'irmao', 'outro'], true)) {
            flash('error', 'Dados inválidos.');
            redirect('/membros/' . $membro['id']);
        }

        if ($parenteId === (int) $membro['id']) {
            flash('error', 'Não pode ligar o membro a si mesmo.');
            redirect('/membros/' . $membro['id']);
        }

        $parente = $this->buscarMembro($parenteId);
        if (!$parente) {
            flash('error', 'Parente não encontrado.');
            redirect('/membros/' . $membro['id']);
        }

        try {
            Database::execute(
                'INSERT INTO `member_families`
                    (`igreja_id`, `member_id`, `parente_id`, `relacao`)
                 VALUES (:i, :m, :p, :r)',
                [
                    'i' => Tenant::id(),
                    'm' => $membro['id'],
                    'p' => $parenteId,
                    'r' => $relacao,
                ]
            );

            // Cria também a relação inversa se fizer sentido
            $inversa = match ($relacao) {
                'conjuge' => 'conjuge',
                'filho'   => 'pai',
                'pai'     => 'filho',
                'mae'     => 'filho',
                'irmao'   => 'irmao',
                default   => null,
            };
            if ($inversa) {
                Database::execute(
                    'INSERT IGNORE INTO `member_families`
                        (`igreja_id`, `member_id`, `parente_id`, `relacao`)
                     VALUES (:i, :m, :p, :r)',
                    ['i' => Tenant::id(), 'm' => $parenteId, 'p' => $membro['id'], 'r' => $inversa]
                );
            }

            Audit::log('membros', 'add_familia', 'member_families', (string) $membro['id'], null, [
                'parente_id' => $parenteId,
                'relacao'    => $relacao,
            ]);
            flash('success', 'Familiar adicionado.');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
        }

        redirect('/membros/' . $membro['id']);
    }

    public function removeFamilia(string $id, string $parenteId): void
    {
        if (!Rbac::can('membros.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/membros');
        }

        Database::execute(
            'DELETE FROM `member_families`
             WHERE `igreja_id` = :i
               AND ((`member_id` = :m AND `parente_id` = :p)
                    OR (`member_id` = :p AND `parente_id` = :m))',
            ['i' => Tenant::id(), 'm' => (int) $id, 'p' => (int) $parenteId]
        );

        Audit::log('membros', 'remove_familia', 'member_families', $id, [
            'parente_id' => (int) $parenteId,
        ], null);

        flash('success', 'Familiar removido.');
        redirect('/membros/' . $id);
    }

    // ======================================================
    // HELPERS PRIVADOS
    // ======================================================

    private function buscarMembro(int $id): ?array
    {
        return Database::first(
            'SELECT * FROM `members`
             WHERE `id` = :id AND `igreja_id` = :i AND `deletado_em` IS NULL
             LIMIT 1',
            ['id' => $id, 'i' => Tenant::id()]
        );
    }

    private function listarDepartamentos(): array
    {
        return Database::select(
            'SELECT `id`, `nome`, `cor` FROM `departments`
             WHERE `igreja_id` = :i AND `ativo` = 1 ORDER BY `nome`',
            ['i' => Tenant::id()]
        );
    }

    private function dadosDoFormulario(): array
    {
        return [
            'codigo'          => trim((string) ($_POST['codigo'] ?? '')),
            'nome'            => trim((string) ($_POST['nome'] ?? '')),
            'telefone'        => trim((string) ($_POST['telefone'] ?? '')),
            'email'           => trim((string) ($_POST['email'] ?? '')),
            'data_nascimento' => (string) ($_POST['data_nascimento'] ?? ''),
            'genero'          => (string) ($_POST['genero'] ?? ''),
            'estado_civil'    => (string) ($_POST['estado_civil'] ?? ''),
            'endereco'        => trim((string) ($_POST['endereco'] ?? '')),
            'cidade'          => trim((string) ($_POST['cidade'] ?? '')),
            'provincia'       => trim((string) ($_POST['provincia'] ?? '')),
            'cargo'           => trim((string) ($_POST['cargo'] ?? '')),
            'status'          => (string) ($_POST['status'] ?? 'activo'),
            'data_conversao'  => (string) ($_POST['data_conversao'] ?? ''),
            'data_baptismo'   => (string) ($_POST['data_baptismo'] ?? ''),
            'membro_desde'    => (string) ($_POST['membro_desde'] ?? ''),
            'observacoes'     => trim((string) ($_POST['observacoes'] ?? '')),
        ];
    }
}