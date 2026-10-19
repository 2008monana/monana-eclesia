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

final class FinanceiroController extends Controller
{
    private const METODOS = ['dinheiro', 'transferencia', 'multicaixa', 'deposito', 'outro'];

    // ======================================================
    // PAINEL PRINCIPAL
    // ======================================================

    public function index(): void
    {
        if (!Rbac::can('financeiro.ver')) {
            flash('error', 'Sem permissão para ver financeiro.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();
        $ano = (int) ($_GET['ano'] ?? date('Y'));
        $mes = (int) ($_GET['mes'] ?? date('n'));
        if ($mes < 1 || $mes > 12) $mes = (int) date('n');
        if ($ano < 2000 || $ano > 2100) $ano = (int) date('Y');

        // Totais do mês
        $totais = $this->totaisDoMes($igrejaId, $ano, $mes);

        // Últimos 6 meses (evolução)
        $evolucao = $this->evolucao6Meses($igrejaId);

        // Categorias de despesa (para gráfico de rosca)
        $porCategoria = Database::select(
            'SELECT c.nome, c.cor, COALESCE(SUM(t.valor_kz), 0) AS total
             FROM categories c
             LEFT JOIN transactions t ON t.categoria_id = c.id
                 AND t.igreja_id = :i
                 AND YEAR(t.data) = :a AND MONTH(t.data) = :m
                 AND t.tipo = "despesa"
             WHERE c.igreja_id = :i2 AND c.tipo = "despesa" AND c.ativo = 1
             GROUP BY c.id
             HAVING total > 0
             ORDER BY total DESC',
            ['i' => $igrejaId, 'i2' => $igrejaId, 'a' => $ano, 'm' => $mes]
        );

        // Fechos mensais existentes
        $fechos = Database::select(
            'SELECT * FROM `monthly_closures`
             WHERE igreja_id = :i
             ORDER BY ano DESC, mes DESC LIMIT 6',
            ['i' => $igrejaId]
        );

        View::render('financeiro/index', [
            'title'       => 'Financeiro — Monana Eclesia',
            'user'        => Auth::user(),
            'ano'         => $ano,
            'mes'         => $mes,
            'totais'      => $totais,
            'evolucao'    => $evolucao,
            'porCategoria' => $porCategoria,
            'fechos'      => $fechos,
        ], 'layouts/app');
    }

    // ======================================================
    // CATEGORIAS
    // ======================================================

    public function categorias(): void
    {
        if (!Rbac::can('financeiro.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();
        $tipo = (string) ($_GET['tipo'] ?? '');

        $where  = ['igreja_id = :i'];
        $params = ['i' => $igrejaId];
        if ($tipo !== '' && in_array($tipo, ['entrada', 'despesa'], true)) {
            $where[] = 'tipo = :t';
            $params['t'] = $tipo;
        }
        $whereSql = implode(' AND ', $where);

        $categorias = Database::select(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM `transactions` t WHERE t.categoria_id = c.id) AS total_transacoes
             FROM `categories` c
             WHERE {$whereSql}
             ORDER BY c.ativo DESC, c.tipo, c.nome",
            $params
        );

        View::render('financeiro/categorias/index', [
            'title'      => 'Categorias — Financeiro',
            'user'       => Auth::user(),
            'categorias' => $categorias,
            'tipo'       => $tipo,
        ], 'layouts/app');
    }

    public function categoriaCreate(): void
    {
        if (!Rbac::can('financeiro.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro/categorias');
        }
        View::render('financeiro/categorias/form', [
            'title'    => 'Nova Categoria',
            'user'     => Auth::user(),
            'categoria'=> [],
            'errors'   => [],
        ], 'layouts/app');
    }

    public function categoriaStore(): void
    {
        if (!Rbac::can('financeiro.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro/categorias');
        }

        $data = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'tipo' => (string) ($_POST['tipo'] ?? ''),
            'cor'  => trim((string) ($_POST['cor'] ?? '#b8862b')),
            'icone'=> trim((string) ($_POST['icone'] ?? 'fa-tag')),
            'ativo'=> isset($_POST['ativo']) ? (int) $_POST['ativo'] : 1,
        ];

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->maxLen('nome', 120, 'Nome')
          ->in('tipo', ['entrada', 'despesa'], 'Tipo');

        if ($v->fails()) {
            View::render('financeiro/categorias/form', [
                'title'    => 'Nova Categoria',
                'user'     => Auth::user(),
                'categoria'=> $data,
                'errors'   => $v->errors(),
            ], 'layouts/app');
            return;
        }

        try {
            $id = Database::insert(
                'INSERT INTO `categories` (`igreja_id`, `nome`, `tipo`, `cor`, `icone`, `ativo`)
                 VALUES (:i, :nome, :tipo, :cor, :icone, :ativo)',
                [
                    'i'     => Tenant::id(),
                    'nome'  => $data['nome'],
                    'tipo'  => $data['tipo'],
                    'cor'   => $data['cor'] ?: '#b8862b',
                    'icone' => $data['icone'] ?: 'fa-tag',
                    'ativo' => $data['ativo'],
                ]
            );

            Audit::log('financeiro', 'criar_categoria', 'categories', (string) $id, null, $data);
            flash('success', 'Categoria criada.');
            redirect('/financeiro/categorias');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/financeiro/categorias/criar');
        }
    }

    public function categoriaEdit(string $id): void
    {
        if (!Rbac::can('financeiro.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro/categorias');
        }

        $categoria = $this->buscarCategoria((int) $id);
        if (!$categoria) {
            flash('error', 'Categoria não encontrada.');
            redirect('/financeiro/categorias');
        }

        View::render('financeiro/categorias/form', [
            'title'     => 'Editar Categoria',
            'user'      => Auth::user(),
            'categoria' => $categoria,
            'errors'    => [],
        ], 'layouts/app');
    }

    public function categoriaUpdate(string $id): void
    {
        if (!Rbac::can('financeiro.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro/categorias');
        }

        $categoria = $this->buscarCategoria((int) $id);
        if (!$categoria) {
            flash('error', 'Categoria não encontrada.');
            redirect('/financeiro/categorias');
        }

        $data = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'tipo' => (string) ($_POST['tipo'] ?? ''),
            'cor'  => trim((string) ($_POST['cor'] ?? '#b8862b')),
            'icone'=> trim((string) ($_POST['icone'] ?? 'fa-tag')),
            'ativo'=> isset($_POST['ativo']) ? (int) $_POST['ativo'] : 1,
        ];

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->maxLen('nome', 120, 'Nome')
          ->in('tipo', ['entrada', 'despesa'], 'Tipo');

        if ($v->fails()) {
            View::render('financeiro/categorias/form', [
                'title'     => 'Editar Categoria',
                'user'      => Auth::user(),
                'categoria' => array_merge($categoria, $data),
                'errors'    => $v->errors(),
            ], 'layouts/app');
            return;
        }

        try {
            Database::execute(
                'UPDATE `categories` SET
                    `nome` = :nome, `tipo` = :tipo, `cor` = :cor, `icone` = :icone, `ativo` = :ativo
                 WHERE `id` = :id AND `igreja_id` = :i',
                [
                    'id'    => $categoria['id'],
                    'i'     => Tenant::id(),
                    'nome'  => $data['nome'],
                    'tipo'  => $data['tipo'],
                    'cor'   => $data['cor'],
                    'icone' => $data['icone'],
                    'ativo' => $data['ativo'],
                ]
            );

            Audit::log('financeiro', 'editar_categoria', 'categories', (string) $categoria['id'], $categoria, $data);
            flash('success', 'Categoria atualizada.');
            redirect('/financeiro/categorias');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/financeiro/categorias/' . $categoria['id'] . '/editar');
        }
    }

    public function categoriaDestroy(string $id): void
    {
        if (!Rbac::can('financeiro.eliminar')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro/categorias');
        }

        $categoria = $this->buscarCategoria((int) $id);
        if (!$categoria) {
            flash('error', 'Categoria não encontrada.');
            redirect('/financeiro/categorias');
        }

        Database::execute(
            'UPDATE `categories` SET `ativo` = 0 WHERE `id` = :id AND `igreja_id` = :i',
            ['id' => $categoria['id'], 'i' => Tenant::id()]
        );

        Audit::log('financeiro', 'desativar_categoria', 'categories', (string) $categoria['id'], $categoria, ['ativo' => 0]);
        flash('success', 'Categoria desativada.');
        redirect('/financeiro/categorias');
    }

    // ======================================================
    // TRANSAÇÕES
    // ======================================================

    public function transacoes(): void
    {
        if (!Rbac::can('financeiro.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();

        $tipo       = (string) ($_GET['tipo'] ?? '');
        $categoriaId= (int) ($_GET['categoria'] ?? 0);
        $de         = (string) ($_GET['de'] ?? '');
        $ate        = (string) ($_GET['ate'] ?? '');
        $q          = trim((string) ($_GET['q'] ?? ''));
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $perPage    = 20;
        $offset     = ($page - 1) * $perPage;

        $where  = ['t.igreja_id = :i'];
        $params = ['i' => $igrejaId];

        if ($tipo !== '' && in_array($tipo, ['entrada', 'despesa'], true)) {
            $where[] = 't.tipo = :tipo';
            $params['tipo'] = $tipo;
        }
        if ($categoriaId > 0) {
            $where[] = 't.categoria_id = :cat';
            $params['cat'] = $categoriaId;
        }
        if ($de !== '' && strtotime($de)) {
            $where[] = 't.data >= :de';
            $params['de'] = $de;
        }
        if ($ate !== '' && strtotime($ate)) {
            $where[] = 't.data <= :ate';
            $params['ate'] = $ate;
        }
        if ($q !== '') {
            $where[] = 't.descricao LIKE :q';
            $params['q'] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::first(
            "SELECT COUNT(*) AS n FROM `transactions` t WHERE {$whereSql}",
            $params
        )['n'] ?? 0);

        $transacoes = Database::select(
            "SELECT t.*, c.nome AS categoria_nome, c.cor AS categoria_cor,
                    m.nome AS membro_nome, u.nome AS criador_nome
             FROM `transactions` t
             LEFT JOIN `categories` c ON c.id = t.categoria_id
             LEFT JOIN `members` m ON m.id = t.member_id
             LEFT JOIN `users` u ON u.id = t.criado_por
             WHERE {$whereSql}
             ORDER BY t.data DESC, t.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $pages = max(1, (int) ceil($total / $perPage));

        $categorias = Database::select(
            'SELECT id, nome, tipo FROM `categories`
             WHERE igreja_id = :i AND ativo = 1
             ORDER BY tipo, nome',
            ['i' => $igrejaId]
        );

        // Totais filtrados
        $totaisFiltro = Database::first(
            "SELECT
                COALESCE(SUM(CASE WHEN t.tipo = 'entrada' THEN t.valor_kz ELSE 0 END), 0) AS entradas,
                COALESCE(SUM(CASE WHEN t.tipo = 'despesa' THEN t.valor_kz ELSE 0 END), 0) AS despesas
             FROM `transactions` t WHERE {$whereSql}",
            $params
        ) ?? ['entradas' => 0, 'despesas' => 0];

        View::render('financeiro/transacoes/index', [
            'title'        => 'Transações — Financeiro',
            'user'         => Auth::user(),
            'transacoes'   => $transacoes,
            'categorias'   => $categorias,
            'total'        => $total,
            'page'         => $page,
            'pages'        => $pages,
            'perPage'      => $perPage,
            'tipo'         => $tipo,
            'categoriaId'  => $categoriaId,
            'de'           => $de,
            'ate'          => $ate,
            'q'            => $q,
            'totaisFiltro' => $totaisFiltro,
        ], 'layouts/app');
    }

    public function transacaoCreate(): void
    {
        if (!Rbac::can('financeiro.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro/transacoes');
        }

        View::render('financeiro/transacoes/form', [
            'title'      => 'Nova Transação',
            'user'       => Auth::user(),
            'transacao'  => [],
            'categorias' => $this->listarCategorias(),
            'membros'    => $this->listarMembros(),
            'metodos'    => self::METODOS,
            'errors'     => [],
        ], 'layouts/app');
    }

    public function transacaoStore(): void
    {
        if (!Rbac::can('financeiro.criar')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro/transacoes');
        }

        $data = $this->dadosTransacao();

        $v = new Validator($data);
        $v->required('descricao', 'Descrição')
          ->required('valor_kz', 'Valor')
          ->required('data', 'Data')
          ->required('tipo', 'Tipo')
          ->date('data', 'Data')
          ->in('tipo', ['entrada', 'despesa'], 'Tipo')
          ->in('metodo', array_merge([''], self::METODOS), 'Método');

        if ($v->fails()) {
            View::render('financeiro/transacoes/form', [
                'title'      => 'Nova Transação',
                'user'       => Auth::user(),
                'transacao'  => $data,
                'categorias' => $this->listarCategorias(),
                'membros'    => $this->listarMembros(),
                'metodos'    => self::METODOS,
                'errors'     => $v->errors(),
            ], 'layouts/app');
            return;
        }

        // Verifica fecho mensal
        if ($this->mesFechado(Tenant::id(), (string) $data['data'])) {
            flash('error', 'Este mês já está fechado. Não é possível adicionar transações.');
            redirect('/financeiro/transacoes/criar');
        }

        try {
            $user = Auth::user();
            $id = Database::insert(
                'INSERT INTO `transactions`
                    (`igreja_id`, `tipo`, `categoria_id`, `member_id`, `descricao`,
                     `valor_kz`, `data`, `metodo`, `referencia`, `criado_por`)
                 VALUES (:i, :tipo, :cat, :mem, :desc, :val, :data, :met, :ref, :user)',
                [
                    'i'    => Tenant::id(),
                    'tipo' => $data['tipo'],
                    'cat'  => $data['categoria_id'] ?: null,
                    'mem'  => $data['member_id'] ?: null,
                    'desc' => $data['descricao'],
                    'val'  => (float) str_replace(',', '.', $data['valor_kz']),
                    'data' => $data['data'],
                    'met'  => $data['metodo'] ?: null,
                    'ref'  => $data['referencia'] ?: null,
                    'user' => $user['id'] ?? null,
                ]
            );

            Audit::log('financeiro', 'criar_transacao', 'transactions', (string) $id, null, $data);
            flash('success', 'Transação registada.');
            redirect('/financeiro/transacoes');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/financeiro/transacoes/criar');
        }
    }

    public function transacaoEdit(string $id): void
    {
        if (!Rbac::can('financeiro.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro/transacoes');
        }

        $transacao = $this->buscarTransacao((int) $id);
        if (!$transacao) {
            flash('error', 'Transação não encontrada.');
            redirect('/financeiro/transacoes');
        }

        if ($this->mesFechado(Tenant::id(), (string) $transacao['data'])) {
            flash('error', 'Este mês está fechado. Não é possível editar.');
            redirect('/financeiro/transacoes');
        }

        View::render('financeiro/transacoes/form', [
            'title'      => 'Editar Transação',
            'user'       => Auth::user(),
            'transacao'  => $transacao,
            'categorias' => $this->listarCategorias(),
            'membros'    => $this->listarMembros(),
            'metodos'    => self::METODOS,
            'errors'     => [],
        ], 'layouts/app');
    }

    public function transacaoUpdate(string $id): void
    {
        if (!Rbac::can('financeiro.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro/transacoes');
        }

        $transacao = $this->buscarTransacao((int) $id);
        if (!$transacao) {
            flash('error', 'Transação não encontrada.');
            redirect('/financeiro/transacoes');
        }

        if ($this->mesFechado(Tenant::id(), (string) $transacao['data'])) {
            flash('error', 'Este mês está fechado.');
            redirect('/financeiro/transacoes');
        }

        $data = $this->dadosTransacao();

        $v = new Validator($data);
        $v->required('descricao', 'Descrição')
          ->required('valor_kz', 'Valor')
          ->required('data', 'Data')
          ->required('tipo', 'Tipo')
          ->date('data', 'Data')
          ->in('tipo', ['entrada', 'despesa'], 'Tipo');

        if ($v->fails()) {
            View::render('financeiro/transacoes/form', [
                'title'      => 'Editar Transação',
                'user'       => Auth::user(),
                'transacao'  => array_merge($transacao, $data),
                'categorias' => $this->listarCategorias(),
                'membros'    => $this->listarMembros(),
                'metodos'    => self::METODOS,
                'errors'     => $v->errors(),
            ], 'layouts/app');
            return;
        }

        try {
            Database::execute(
                'UPDATE `transactions` SET
                    `tipo` = :tipo, `categoria_id` = :cat, `member_id` = :mem,
                    `descricao` = :desc, `valor_kz` = :val, `data` = :data,
                    `metodo` = :met, `referencia` = :ref
                 WHERE `id` = :id AND `igreja_id` = :i',
                [
                    'id'   => $transacao['id'],
                    'i'    => Tenant::id(),
                    'tipo' => $data['tipo'],
                    'cat'  => $data['categoria_id'] ?: null,
                    'mem'  => $data['member_id'] ?: null,
                    'desc' => $data['descricao'],
                    'val'  => (float) str_replace(',', '.', $data['valor_kz']),
                    'data' => $data['data'],
                    'met'  => $data['metodo'] ?: null,
                    'ref'  => $data['referencia'] ?: null,
                ]
            );

            Audit::log('financeiro', 'editar_transacao', 'transactions', (string) $transacao['id'], $transacao, $data);
            flash('success', 'Transação atualizada.');
            redirect('/financeiro/transacoes');
        } catch (\Throwable $e) {
            flash('error', 'Erro: ' . $e->getMessage());
            redirect('/financeiro/transacoes/' . $transacao['id'] . '/editar');
        }
    }

    public function transacaoDestroy(string $id): void
    {
        if (!Rbac::can('financeiro.eliminar')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro/transacoes');
        }

        $transacao = $this->buscarTransacao((int) $id);
        if (!$transacao) {
            flash('error', 'Transação não encontrada.');
            redirect('/financeiro/transacoes');
        }

        if ($this->mesFechado(Tenant::id(), (string) $transacao['data'])) {
            flash('error', 'Este mês está fechado.');
            redirect('/financeiro/transacoes');
        }

        Database::execute(
            'DELETE FROM `transactions` WHERE `id` = :id AND `igreja_id` = :i',
            ['id' => $transacao['id'], 'i' => Tenant::id()]
        );

        Audit::log('financeiro', 'eliminar_transacao', 'transactions', (string) $transacao['id'], $transacao, null);
        flash('success', 'Transação eliminada.');
        redirect('/financeiro/transacoes');
    }

    // ======================================================
    // FECHO MENSAL
    // ======================================================

    public function fecho(): void
    {
        if (!Rbac::can('financeiro.fechar_mes')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro');
        }

        $igrejaId = Tenant::id();
        $fechos = Database::select(
            'SELECT f.*, u.nome AS fechado_por_nome
             FROM `monthly_closures` f
             LEFT JOIN `users` u ON u.id = f.fechado_por
             WHERE f.igreja_id = :i
             ORDER BY f.ano DESC, f.mes DESC',
            ['i' => $igrejaId]
        );

        View::render('financeiro/fecho', [
            'title'  => 'Fecho Mensal — Financeiro',
            'user'   => Auth::user(),
            'fechos' => $fechos,
        ], 'layouts/app');
    }

    public function executarFecho(): void
    {
        if (!Rbac::can('financeiro.fechar_mes')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro/fecho');
        }

        $ano = (int) ($_POST['ano'] ?? 0);
        $mes = (int) ($_POST['mes'] ?? 0);

        if ($ano < 2000 || $ano > 2100 || $mes < 1 || $mes > 12) {
            flash('error', 'Ano/mês inválido.');
            redirect('/financeiro/fecho');
        }

        $igrejaId = Tenant::id();

        // Verifica se já está fechado
        $existe = Database::first(
            'SELECT id FROM `monthly_closures` WHERE igreja_id = :i AND ano = :a AND mes = :m LIMIT 1',
            ['i' => $igrejaId, 'a' => $ano, 'm' => $mes]
        );
        if ($existe) {
            flash('error', 'Este mês já está fechado.');
            redirect('/financeiro/fecho');
        }

        // Calcula totais
        $totais = Database::first(
            'SELECT
                COALESCE(SUM(CASE WHEN tipo = "entrada" THEN valor_kz ELSE 0 END), 0) AS entradas,
                COALESCE(SUM(CASE WHEN tipo = "despesa" THEN valor_kz ELSE 0 END), 0) AS despesas
             FROM `transactions`
             WHERE igreja_id = :i AND YEAR(data) = :a AND MONTH(data) = :m',
            ['i' => $igrejaId, 'a' => $ano, 'm' => $mes]
        );

        $entradas = (float) ($totais['entradas'] ?? 0);
        $despesas = (float) ($totais['despesas'] ?? 0);
        $saldo    = $entradas - $despesas;

        $user = Auth::user();

        Database::insert(
            'INSERT INTO `monthly_closures`
                (`igreja_id`, `ano`, `mes`, `total_entradas`, `total_despesas`, `saldo`, `fechado_por`)
             VALUES (:i, :a, :m, :ent, :desp, :saldo, :user)',
            [
                'i'     => $igrejaId,
                'a'     => $ano,
                'm'     => $mes,
                'ent'   => $entradas,
                'desp'  => $despesas,
                'saldo' => $saldo,
                'user'  => $user['id'] ?? null,
            ]
        );

        Audit::log('financeiro', 'fechar_mes', 'monthly_closures', null, null, [
            'ano' => $ano, 'mes' => $mes,
            'entradas' => $entradas, 'despesas' => $despesas, 'saldo' => $saldo,
        ]);

        flash('success', "Fecho de {$mes}/{$ano} realizado com sucesso.");
        redirect('/financeiro/fecho');
    }

    public function reabrirFecho(string $id): void
    {
        if (!Rbac::can('financeiro.fechar_mes')) {
            flash('error', 'Sem permissão.');
            redirect('/financeiro/fecho');
        }

        $fecho = Database::first(
            'SELECT * FROM `monthly_closures` WHERE id = :id AND igreja_id = :i LIMIT 1',
            ['id' => (int) $id, 'i' => Tenant::id()]
        );
        if (!$fecho) {
            flash('error', 'Fecho não encontrado.');
            redirect('/financeiro/fecho');
        }

        Database::execute(
            'DELETE FROM `monthly_closures` WHERE id = :id AND igreja_id = :i',
            ['id' => $fecho['id'], 'i' => Tenant::id()]
        );

        Audit::log('financeiro', 'reabrir_mes', 'monthly_closures', (string) $fecho['id'], $fecho, null);
        flash('success', 'Mês reaberto.');
        redirect('/financeiro/fecho');
    }

    // ======================================================
    // EXPORTAR CSV
    // ======================================================

    public function exportar(): never
    {
        if (!Rbac::can('financeiro.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();
        $ano = (int) ($_GET['ano'] ?? date('Y'));
        $mes = (int) ($_GET['mes'] ?? date('n'));

        $rows = Database::select(
            'SELECT t.data, t.tipo, c.nome AS categoria, t.descricao,
                    t.valor_kz, t.metodo, t.referencia,
                    m.nome AS membro
             FROM `transactions` t
             LEFT JOIN `categories` c ON c.id = t.categoria_id
             LEFT JOIN `members` m ON m.id = t.member_id
             WHERE t.igreja_id = :i AND YEAR(t.data) = :a AND MONTH(t.data) = :m
             ORDER BY t.data, t.id',
            ['i' => $igrejaId, 'a' => $ano, 'm' => $mes]
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="financeiro-' . $ano . '-' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8

        fputcsv($out, ['Data', 'Tipo', 'Categoria', 'Descrição', 'Valor (Kz)', 'Método', 'Referência', 'Membro'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['data'], $r['tipo'], $r['categoria'] ?? '', $r['descricao'],
                number_format((float) $r['valor_kz'], 2, ',', '.'),
                $r['metodo'] ?? '', $r['referencia'] ?? '', $r['membro'] ?? '',
            ], ';');
        }

        fclose($out);
        exit;
    }

    // ======================================================
    // HELPERS
    // ======================================================

    private function totaisDoMes(int $igrejaId, int $ano, int $mes): array
    {
        $row = Database::first(
            'SELECT
                COALESCE(SUM(CASE WHEN tipo = "entrada" THEN valor_kz ELSE 0 END), 0) AS entradas,
                COALESCE(SUM(CASE WHEN tipo = "despesa" THEN valor_kz ELSE 0 END), 0) AS despesas,
                COUNT(*) AS total
             FROM `transactions`
             WHERE igreja_id = :i AND YEAR(data) = :a AND MONTH(data) = :m',
            ['i' => $igrejaId, 'a' => $ano, 'm' => $mes]
        );

        $entradas = (float) ($row['entradas'] ?? 0);
        $despesas = (float) ($row['despesas'] ?? 0);

        return [
            'entradas' => $entradas,
            'despesas' => $despesas,
            'saldo'    => $entradas - $despesas,
            'total'    => (int) ($row['total'] ?? 0),
        ];
    }

    private function evolucao6Meses(int $igrejaId): array
    {
        $result = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts = strtotime("-{$i} months");
            $mes = (int) date('n', $ts);
            $ano = (int) date('Y', $ts);
            $label = date('M', $ts);

            $row = Database::first(
                'SELECT
                    COALESCE(SUM(CASE WHEN tipo = "entrada" THEN valor_kz ELSE 0 END), 0) AS entradas,
                    COALESCE(SUM(CASE WHEN tipo = "despesa" THEN valor_kz ELSE 0 END), 0) AS despesas
                 FROM `transactions`
                 WHERE igreja_id = :i AND YEAR(data) = :a AND MONTH(data) = :m',
                ['i' => $igrejaId, 'a' => $ano, 'm' => $mes]
            );

            $result[] = [
                'label'    => $label,
                'mes'      => $mes,
                'ano'      => $ano,
                'entradas' => (float) ($row['entradas'] ?? 0),
                'despesas' => (float) ($row['despesas'] ?? 0),
            ];
        }
        return $result;
    }

    private function mesFechado(int $igrejaId, string $data): bool
    {
        if (!strtotime($data)) return false;
        $ano = (int) date('Y', strtotime($data));
        $mes = (int) date('n', strtotime($data));

        return (bool) Database::first(
            'SELECT id FROM `monthly_closures` WHERE igreja_id = :i AND ano = :a AND mes = :m LIMIT 1',
            ['i' => $igrejaId, 'a' => $ano, 'm' => $mes]
        );
    }

    private function buscarCategoria(int $id): ?array
    {
        return Database::first(
            'SELECT * FROM `categories` WHERE `id` = :id AND `igreja_id` = :i LIMIT 1',
            ['id' => $id, 'i' => Tenant::id()]
        );
    }

    private function buscarTransacao(int $id): ?array
    {
        return Database::first(
            'SELECT * FROM `transactions` WHERE `id` = :id AND `igreja_id` = :i LIMIT 1',
            ['id' => $id, 'i' => Tenant::id()]
        );
    }

    private function listarCategorias(): array
    {
        return Database::select(
            'SELECT `id`, `nome`, `tipo` FROM `categories`
             WHERE `igreja_id` = :i AND `ativo` = 1
             ORDER BY `tipo`, `nome`',
            ['i' => Tenant::id()]
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

    private function dadosTransacao(): array
    {
        return [
            'tipo'         => (string) ($_POST['tipo'] ?? ''),
            'categoria_id' => (int) ($_POST['categoria_id'] ?? 0) ?: null,
            'member_id'    => (int) ($_POST['member_id'] ?? 0) ?: null,
            'descricao'    => trim((string) ($_POST['descricao'] ?? '')),
            'valor_kz'     => trim((string) ($_POST['valor_kz'] ?? '')),
            'data'         => (string) ($_POST['data'] ?? ''),
            'metodo'       => (string) ($_POST['metodo'] ?? ''),
            'referencia'   => trim((string) ($_POST['referencia'] ?? '')),
        ];
    }
}