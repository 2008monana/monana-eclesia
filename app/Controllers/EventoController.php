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

final class EventoController extends Controller
{
    private const TIPOS = ['Culto', 'Juventude', 'Ensino', 'Mulheres', 'Homens', 'Evangelismo', 'Outro'];

    // ======================================================
    // LISTA + CALENDÁRIO
    // ======================================================

    public function index(): void
    {
        if (!Rbac::can('eventos.ver')) {
            flash('error', 'Sem permissão para ver eventos.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();

        // Mês de referência (padrão: atual)
        $mes = (int) ($_GET['mes'] ?? date('n'));
        $ano = (int) ($_GET['ano'] ?? date('Y'));

        if ($mes < 1 || $mes > 12) $mes = (int) date('n');
        if ($ano < 2000 || $ano > 2100) $ano = (int) date('Y');

        $tipo = (string) ($_GET['tipo'] ?? '');

        $where  = ['e.igreja_id = :i'];
        $params = ['i' => $igrejaId];

        if ($tipo !== '' && in_array($tipo, self::TIPOS, true)) {
            $where[] = 'e.tipo = :tipo';
            $params['tipo'] = $tipo;
        }

        $whereSql = implode(' AND ', $where);

        // Todos os eventos do mês (para o calendário)
        $eventosDoMes = Database::select(
            "SELECT e.*, m.`nome` AS responsavel_nome
             FROM `events` e
             LEFT JOIN `members` m ON m.id = e.responsavel_id
             WHERE {$whereSql}
               AND YEAR(e.`data`) = :ano AND MONTH(e.`data`) = :mes
             ORDER BY e.`data`, e.`hora`",
            array_merge($params, ['ano' => $ano, 'mes' => $mes])
        );

        // Próximos eventos (5) para a lista lateral
        $proximos = Database::select(
            "SELECT e.*, m.`nome` AS responsavel_nome
             FROM `events` e
             LEFT JOIN `members` m ON m.id = e.responsavel_id
             WHERE {$whereSql} AND e.`data` >= CURDATE()
             ORDER BY e.`data` ASC, e.`hora` ASC
             LIMIT 5",
            $params
        );

        // Agrupa por dia para o calendário
        $porDia = [];
        foreach ($eventosDoMes as $e) {
            $porDia[$e['data']][] = $e;
        }

        View::render('eventos/index', [
            'title'        => 'Eventos — Monana Eclesia',
            'user'         => Auth::user(),
            'eventos'      => $eventosDoMes,
            'porDia'       => $porDia,
            'proximos'     => $proximos,
            'mes'          => $mes,
            'ano'          => $ano,
            'tipo'         => $tipo,
            'tipos'        => self::TIPOS,
        ], 'layouts/app');
    }

    // ======================================================
    // CRIAR
    // ======================================================

    public function create(): void
    {
        if (!Rbac::can('eventos.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/eventos');
        }

        View::render('eventos/form', [
            'title'   => 'Novo Evento — Monana Eclesia',
            'user'    => Auth::user(),
            'evento'  => [],
            'tipos'   => self::TIPOS,
            'membros' => $this->listarMembros(),
            'errors'  => [],
        ], 'layouts/app');
    }

    public function store(): void
    {
        if (!Rbac::can('eventos.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/eventos');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('titulo', 'Título')
          ->required('data', 'Data')
          ->maxLen('titulo', 180, 'Título')
          ->date('data', 'Data')
          ->in('tipo', self::TIPOS, 'Tipo');

        if ($v->fails()) {
            View::render('eventos/form', [
                'title'   => 'Novo Evento — Monana Eclesia',
                'user'    => Auth::user(),
                'evento'  => $data,
                'tipos'   => self::TIPOS,
                'membros' => $this->listarMembros(),
                'errors'  => $v->errors(),
            ], 'layouts/app');
            return;
        }

        try {
            $user = Auth::user();
            $id = Database::insert(
                'INSERT INTO `events`
                    (`igreja_id`, `titulo`, `descricao`, `tipo`, `data`, `hora`,
                     `local`, `responsavel_id`, `criado_por`)
                 VALUES (:i, :titulo, :desc, :tipo, :data, :hora,
                         :local, :resp, :user)',
                [
                    'i'      => Tenant::id(),
                    'titulo' => $data['titulo'],
                    'desc'   => $data['descricao'] ?: null,
                    'tipo'   => $data['tipo'] ?: null,
                    'data'   => $data['data'],
                    'hora'   => $data['hora'] ?: null,
                    'local'  => $data['local'] ?: null,
                    'resp'   => $data['responsavel_id'] ?: null,
                    'user'   => $user['id'] ?? null,
                ]
            );

            Audit::log('eventos', 'criar', 'events', (string) $id, null, $data);
            flash('success', 'Evento criado com sucesso.');
            redirect('/eventos/' . $id);
        } catch (\Throwable $e) {
            logger('error', 'Erro ao criar evento', ['msg' => $e->getMessage()]);
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/eventos/criar');
        }
    }

    // ======================================================
    // VER + PRESENÇAS
    // ======================================================

    public function show(string $id): void
    {
        if (!Rbac::can('eventos.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $evento = $this->buscarEvento((int) $id);
        if (!$evento) {
            flash('error', 'Evento não encontrado.');
            redirect('/eventos');
        }

        $igrejaId = Tenant::id();

        // Todos os membros ativos
        $membros = Database::select(
            'SELECT m.id, m.nome, m.codigo, m.foto
             FROM `members` m
             WHERE m.igreja_id = :i AND m.deletado_em IS NULL AND m.status = "activo"
             ORDER BY m.nome',
            ['i' => $igrejaId]
        );

        // Presenças registadas (mapa member_id => presente)
        $presencasRaw = Database::select(
            'SELECT member_id, presente FROM `event_attendances` WHERE event_id = :e',
            ['e' => $evento['id']]
        );
        $presencas = [];
        foreach ($presencasRaw as $p) {
            $presencas[(int) $p['member_id']] = (bool) $p['presente'];
        }

        $totalPresentes = count(array_filter($presencas));
        $totalMembros   = count($membros);

        View::render('eventos/show', [
            'title'          => $evento['titulo'] . ' — Monana Eclesia',
            'user'           => Auth::user(),
            'evento'         => $evento,
            'membros'        => $membros,
            'presencas'      => $presencas,
            'totalPresentes' => $totalPresentes,
            'totalMembros'   => $totalMembros,
        ], 'layouts/app');
    }

    // ======================================================
    // EDITAR
    // ======================================================

    public function edit(string $id): void
    {
        if (!Rbac::can('eventos.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/eventos');
        }

        $evento = $this->buscarEvento((int) $id);
        if (!$evento) {
            flash('error', 'Evento não encontrado.');
            redirect('/eventos');
        }

        View::render('eventos/form', [
            'title'   => 'Editar Evento — Monana Eclesia',
            'user'    => Auth::user(),
            'evento'  => $evento,
            'tipos'   => self::TIPOS,
            'membros' => $this->listarMembros(),
            'errors'  => [],
        ], 'layouts/app');
    }

    public function update(string $id): void
    {
        if (!Rbac::can('eventos.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/eventos');
        }

        $evento = $this->buscarEvento((int) $id);
        if (!$evento) {
            flash('error', 'Evento não encontrado.');
            redirect('/eventos');
        }

        $data = $this->dadosDoFormulario();

        $v = new Validator($data);
        $v->required('titulo', 'Título')
          ->required('data', 'Data')
          ->maxLen('titulo', 180, 'Título')
          ->date('data', 'Data')
          ->in('tipo', self::TIPOS, 'Tipo');

        if ($v->fails()) {
            View::render('eventos/form', [
                'title'   => 'Editar Evento — Monana Eclesia',
                'user'    => Auth::user(),
                'evento'  => array_merge($evento, $data),
                'tipos'   => self::TIPOS,
                'membros' => $this->listarMembros(),
                'errors'  => $v->errors(),
            ], 'layouts/app');
            return;
        }

        try {
            Database::execute(
                'UPDATE `events` SET
                    `titulo` = :titulo,
                    `descricao` = :desc,
                    `tipo` = :tipo,
                    `data` = :data,
                    `hora` = :hora,
                    `local` = :local,
                    `responsavel_id` = :resp
                 WHERE `id` = :id AND `igreja_id` = :i',
                [
                    'id'     => $evento['id'],
                    'i'      => Tenant::id(),
                    'titulo' => $data['titulo'],
                    'desc'   => $data['descricao'] ?: null,
                    'tipo'   => $data['tipo'] ?: null,
                    'data'   => $data['data'],
                    'hora'   => $data['hora'] ?: null,
                    'local'  => $data['local'] ?: null,
                    'resp'   => $data['responsavel_id'] ?: null,
                ]
            );

            Audit::log('eventos', 'editar', 'events', (string) $evento['id'], $evento, $data);
            flash('success', 'Evento atualizado.');
            redirect('/eventos/' . $evento['id']);
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/eventos/' . $evento['id'] . '/editar');
        }
    }

    // ======================================================
    // ELIMINAR
    // ======================================================

    public function destroy(string $id): void
    {
        if (!Rbac::can('eventos.eliminar')) {
            flash('error', 'Sem permissão.');
            redirect('/eventos');
        }

        $evento = $this->buscarEvento((int) $id);
        if (!$evento) {
            flash('error', 'Evento não encontrado.');
            redirect('/eventos');
        }

        Database::execute(
            'DELETE FROM `events` WHERE `id` = :id AND `igreja_id` = :i',
            ['id' => $evento['id'], 'i' => Tenant::id()]
        );

        Audit::log('eventos', 'eliminar', 'events', (string) $evento['id'], $evento, null);
        flash('success', 'Evento eliminado.');
        redirect('/eventos');
    }

    // ======================================================
    // PRESENÇAS
    // ======================================================

    public function togglePresenca(string $id, string $memberId): void
    {
        if (!Rbac::can('eventos.registar_presenca')) {
            flash('error', 'Sem permissão.');
            redirect('/eventos');
        }

        $evento = $this->buscarEvento((int) $id);
        if (!$evento) {
            flash('error', 'Evento não encontrado.');
            redirect('/eventos');
        }

        $memberId = (int) $memberId;

        $atual = Database::first(
            'SELECT `id`, `presente` FROM `event_attendances`
             WHERE `event_id` = :e AND `member_id` = :m LIMIT 1',
            ['e' => $evento['id'], 'm' => $memberId]
        );

        if ($atual) {
            // Alterna
            Database::execute(
                'UPDATE `event_attendances` SET `presente` = :p WHERE `id` = :id',
                ['p' => $atual['presente'] ? 0 : 1, 'id' => $atual['id']]
            );
        } else {
            Database::execute(
                'INSERT INTO `event_attendances` (`event_id`, `member_id`, `presente`)
                 VALUES (:e, :m, 1)',
                ['e' => $evento['id'], 'm' => $memberId]
            );
        }

        redirect('/eventos/' . $evento['id']);
    }

    public function marcarTodos(string $id): void
    {
        if (!Rbac::can('eventos.registar_presenca')) {
            flash('error', 'Sem permissão.');
            redirect('/eventos');
        }

        $evento = $this->buscarEvento((int) $id);
        if (!$evento) {
            flash('error', 'Evento não encontrado.');
            redirect('/eventos');
        }

        $membros = Database::select(
            'SELECT id FROM `members`
             WHERE igreja_id = :i AND deletado_em IS NULL AND status = "activo"',
            ['i' => Tenant::id()]
        );

        foreach ($membros as $m) {
            Database::execute(
                'INSERT INTO `event_attendances` (`event_id`, `member_id`, `presente`)
                 VALUES (:e, :m, 1)
                 ON DUPLICATE KEY UPDATE `presente` = 1',
                ['e' => $evento['id'], 'm' => $m['id']]
            );
        }

        Audit::log('eventos', 'marcar_todos', 'event_attendances', (string) $evento['id'], null, null);
        flash('success', 'Todos os membros marcados como presentes.');
        redirect('/eventos/' . $evento['id']);
    }

    // ======================================================
    // HELPERS
    // ======================================================

    private function buscarEvento(int $id): ?array
    {
        return Database::first(
            'SELECT * FROM `events` WHERE `id` = :id AND `igreja_id` = :i LIMIT 1',
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
            'titulo'         => trim((string) ($_POST['titulo'] ?? '')),
            'descricao'      => trim((string) ($_POST['descricao'] ?? '')),
            'tipo'           => (string) ($_POST['tipo'] ?? ''),
            'data'           => (string) ($_POST['data'] ?? ''),
            'hora'           => (string) ($_POST['hora'] ?? ''),
            'local'          => trim((string) ($_POST['local'] ?? '')),
            'responsavel_id' => (int) ($_POST['responsavel_id'] ?? 0) ?: null,
        ];
    }
}