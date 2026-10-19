<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Rbac;
use App\Core\Tenant;
use App\Core\View;

final class RelatorioController extends Controller
{
    // ======================================================
    // HUB
    // ======================================================

    public function index(): void
    {
        if (!Rbac::can('relatorios.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();

        $totalMembros = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `members` WHERE igreja_id = :i AND deletado_em IS NULL',
            ['i' => $igrejaId]
        )['n'] ?? 0);

        $totalEventos = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `events` WHERE igreja_id = :i',
            ['i' => $igrejaId]
        )['n'] ?? 0);

        $totalTransacoes = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `transactions` WHERE igreja_id = :i',
            ['i' => $igrejaId]
        )['n'] ?? 0);

        $totalAuditoria = (int) (Database::first(
            'SELECT COUNT(*) AS n FROM `audit_logs` WHERE igreja_id = :i',
            ['i' => $igrejaId]
        )['n'] ?? 0);

        View::render('relatorios/index', [
            'title'           => 'Relatórios — Monana Eclesia',
            'user'            => Auth::user(),
            'totalMembros'    => $totalMembros,
            'totalEventos'    => $totalEventos,
            'totalTransacoes' => $totalTransacoes,
            'totalAuditoria'  => $totalAuditoria,
        ], 'layouts/app');
    }

    // ======================================================
    // MEMBROS
    // ======================================================

    public function membros(): void
    {
        if (!Rbac::can('relatorios.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();

        // Por status
        $porStatus = Database::select(
            'SELECT status, COUNT(*) AS total FROM `members`
             WHERE igreja_id = :i AND deletado_em IS NULL
             GROUP BY status ORDER BY total DESC',
            ['i' => $igrejaId]
        );

        // Por género
        $porGenero = Database::select(
            'SELECT genero, COUNT(*) AS total FROM `members`
             WHERE igreja_id = :i AND deletado_em IS NULL AND genero IS NOT NULL
             GROUP BY genero',
            ['i' => $igrejaId]
        );

        // Por estado civil
        $porEstadoCivil = Database::select(
            'SELECT estado_civil, COUNT(*) AS total FROM `members`
             WHERE igreja_id = :i AND deletado_em IS NULL AND estado_civil IS NOT NULL
             GROUP BY estado_civil ORDER BY total DESC',
            ['i' => $igrejaId]
        );

        // Por departamento
        $porDepartamento = Database::select(
            'SELECT d.nome, d.cor, COUNT(dm.member_id) AS total
             FROM `departments` d
             LEFT JOIN `department_members` dm ON dm.department_id = d.id
             WHERE d.igreja_id = :i AND d.ativo = 1
             GROUP BY d.id
             ORDER BY total DESC',
            ['i' => $igrejaId]
        );

        // Faixa etária
        $porFaixaEtaria = Database::select(
            'SELECT
                CASE
                    WHEN TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) < 12 THEN "0-11"
                    WHEN TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) < 18 THEN "12-17"
                    WHEN TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) < 30 THEN "18-29"
                    WHEN TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) < 45 THEN "30-44"
                    WHEN TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) < 60 THEN "45-59"
                    ELSE "60+"
                END AS faixa,
                COUNT(*) AS total
             FROM `members`
             WHERE igreja_id = :i AND deletado_em IS NULL AND data_nascimento IS NOT NULL
             GROUP BY faixa
             ORDER BY faixa',
            ['i' => $igrejaId]
        );

        View::render('relatorios/membros', [
            'title'           => 'Relatório de Membros',
            'user'            => Auth::user(),
            'porStatus'       => $porStatus,
            'porGenero'       => $porGenero,
            'porEstadoCivil'  => $porEstadoCivil,
            'porDepartamento' => $porDepartamento,
            'porFaixaEtaria'  => $porFaixaEtaria,
        ], 'layouts/app');
    }

    // ======================================================
    // FINANCEIRO
    // ======================================================

    public function financeiro(): void
    {
        if (!Rbac::can('relatorios.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();
        $ano = (int) ($_GET['ano'] ?? date('Y'));
        $mes = (int) ($_GET['mes'] ?? date('n'));

        // Por mês do ano
        $porMes = [];
        for ($m = 1; $m <= 12; $m++) {
            $row = Database::first(
                'SELECT
                    COALESCE(SUM(CASE WHEN tipo = "entrada" THEN valor_kz ELSE 0 END), 0) AS entradas,
                    COALESCE(SUM(CASE WHEN tipo = "despesa" THEN valor_kz ELSE 0 END), 0) AS despesas
                 FROM `transactions`
                 WHERE igreja_id = :i AND YEAR(data) = :a AND MONTH(data) = :m',
                ['i' => $igrejaId, 'a' => $ano, 'm' => $m]
            );
            $porMes[] = [
                'mes'      => $m,
                'entradas' => (float) ($row['entradas'] ?? 0),
                'despesas' => (float) ($row['despesas'] ?? 0),
            ];
        }

        // Por categoria no ano
        $porCategoria = Database::select(
            'SELECT c.nome, c.tipo, c.cor, COALESCE(SUM(t.valor_kz), 0) AS total
             FROM `categories` c
             LEFT JOIN `transactions` t ON t.categoria_id = c.id
                 AND t.igreja_id = :i
                 AND YEAR(t.data) = :a
             WHERE c.igreja_id = :i2 AND c.ativo = 1
             GROUP BY c.id
             HAVING total > 0
             ORDER BY total DESC',
            ['i' => $igrejaId, 'i2' => $igrejaId, 'a' => $ano]
        );

        // Por método no ano
        $porMetodo = Database::select(
            'SELECT metodo, COUNT(*) AS total, COALESCE(SUM(valor_kz), 0) AS valor
             FROM `transactions`
             WHERE igreja_id = :i AND YEAR(data) = :a AND metodo IS NOT NULL
             GROUP BY metodo
             ORDER BY valor DESC',
            ['i' => $igrejaId, 'a' => $ano]
        );

        // Top dizimistas
        $topDizimistas = Database::select(
            'SELECT m.nome, m.codigo, COALESCE(SUM(t.valor_kz), 0) AS total
             FROM `transactions` t
             JOIN `members` m ON m.id = t.member_id
             WHERE t.igreja_id = :i AND t.tipo = "entrada"
               AND YEAR(t.data) = :a
             GROUP BY m.id
             ORDER BY total DESC
             LIMIT 10',
            ['i' => $igrejaId, 'a' => $ano]
        );

        View::render('relatorios/financeiro', [
            'title'         => 'Relatório Financeiro',
            'user'          => Auth::user(),
            'ano'           => $ano,
            'mes'           => $mes,
            'porMes'        => $porMes,
            'porCategoria'  => $porCategoria,
            'porMetodo'     => $porMetodo,
            'topDizimistas' => $topDizimistas,
        ], 'layouts/app');
    }

    // ======================================================
    // FREQUÊNCIA
    // ======================================================

    public function frequencia(): void
    {
        if (!Rbac::can('relatorios.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();

        // Frequência por evento (últimos 20 eventos)
        $porEvento = Database::select(
            'SELECT e.id, e.titulo, e.data, e.tipo,
                    (SELECT COUNT(*) FROM `event_attendances` ea WHERE ea.event_id = e.id AND ea.presente = 1) AS presentes,
                    (SELECT COUNT(*) FROM `members` WHERE igreja_id = e.igreja_id AND deletado_em IS NULL AND status = "activo") AS total_membros
             FROM `events` e
             WHERE e.igreja_id = :i AND e.data <= CURDATE()
             ORDER BY e.data DESC
             LIMIT 20',
            ['i' => $igrejaId]
        );

        // Frequência por membro (últimos 3 meses)
        $porMembro = Database::select(
            'SELECT m.id, m.nome, m.codigo,
                    COUNT(ea.id) AS presencas,
                    (SELECT COUNT(*) FROM `events` WHERE igreja_id = :i2 AND data <= CURDATE() AND data >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)) AS total_eventos
             FROM `members` m
             LEFT JOIN `event_attendances` ea ON ea.member_id = m.id
                 AND ea.presente = 1
                 AND ea.registado_em >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
             WHERE m.igreja_id = :i AND m.deletado_em IS NULL AND m.status = "activo"
             GROUP BY m.id
             ORDER BY presencas DESC, m.nome
             LIMIT 30',
            ['i' => $igrejaId, 'i2' => $igrejaId]
        );

        // Frequência por célula
        $porCelula = Database::select(
            'SELECT c.id, c.nome, COUNT(cm.member_id) AS total_membros,
                    (SELECT COUNT(*) FROM `cell_meetings` cmt WHERE cmt.cell_id = c.id AND cmt.data >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)) AS total_reunioes
             FROM `cells` c
             LEFT JOIN `cell_members` cm ON cm.cell_id = c.id
             WHERE c.igreja_id = :i AND c.ativo = 1
             GROUP BY c.id
             ORDER BY c.nome',
            ['i' => $igrejaId]
        );

        View::render('relatorios/frequencia', [
            'title'      => 'Relatório de Frequência',
            'user'       => Auth::user(),
            'porEvento'  => $porEvento,
            'porMembro'  => $porMembro,
            'porCelula'  => $porCelula,
        ], 'layouts/app');
    }

    // ======================================================
    // ANIVERSARIANTES
    // ======================================================

    public function aniversariantes(): void
    {
        if (!Rbac::can('relatorios.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();
        $mes = (int) ($_GET['mes'] ?? date('n'));
        if ($mes < 1 || $mes > 12) $mes = (int) date('n');

        $aniversariantes = Database::select(
            'SELECT id, nome, codigo, data_nascimento, telefone,
                    TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) AS idade
             FROM `members`
             WHERE igreja_id = :i AND deletado_em IS NULL
               AND MONTH(data_nascimento) = :m
             ORDER BY DAY(data_nascimento)',
            ['i' => $igrejaId, 'm' => $mes]
        );

        View::render('relatorios/aniversariantes', [
            'title'           => 'Aniversariantes do Mês',
            'user'            => Auth::user(),
            'mes'             => $mes,
            'aniversariantes' => $aniversariantes,
        ], 'layouts/app');
    }

    // ======================================================
    // EXPORTAR CSV
    // ======================================================

    public function exportar(string $tipo): never
    {
        if (!Rbac::can('relatorios.exportar')) {
            flash('error', 'Sem permissão.');
            redirect('/relatorios');
        }

        $igrejaId = Tenant::id();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio-' . $tipo . '-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8

        switch ($tipo) {
            case 'membros':
                fputcsv($out, ['Código','Nome','Telefone','E-mail','Género','Estado Civil','Cargo','Status','Membro desde'], ';');
                $rows = Database::select(
                    'SELECT codigo, nome, telefone, email, genero, estado_civil, cargo, status, membro_desde
                     FROM `members` WHERE igreja_id = :i AND deletado_em IS NULL ORDER BY nome',
                    ['i' => $igrejaId]
                );
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r['codigo'], $r['nome'], $r['telefone'] ?? '', $r['email'] ?? '',
                        $r['genero'] ?? '', $r['estado_civil'] ?? '', $r['cargo'] ?? '',
                        $r['status'], $r['membro_desde'] ?? '',
                    ], ';');
                }
                break;

            case 'financeiro':
                fputcsv($out, ['Data','Tipo','Categoria','Descrição','Valor (Kz)','Método','Referência','Membro'], ';');
                $rows = Database::select(
                    'SELECT t.data, t.tipo, c.nome AS categoria, t.descricao,
                            t.valor_kz, t.metodo, t.referencia, m.nome AS membro
                     FROM `transactions` t
                     LEFT JOIN `categories` c ON c.id = t.categoria_id
                     LEFT JOIN `members` m ON m.id = t.member_id
                     WHERE t.igreja_id = :i
                     ORDER BY t.data DESC',
                    ['i' => $igrejaId]
                );
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r['data'], $r['tipo'], $r['categoria'] ?? '', $r['descricao'],
                        number_format((float) $r['valor_kz'], 2, ',', '.'),
                        $r['metodo'] ?? '', $r['referencia'] ?? '', $r['membro'] ?? '',
                    ], ';');
                }
                break;

            case 'aniversariantes':
                fputcsv($out, ['Nome','Código','Data de Nascimento','Idade','Telefone'], ';');
                $rows = Database::select(
                    'SELECT nome, codigo, data_nascimento,
                            TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) AS idade, telefone
                     FROM `members`
                     WHERE igreja_id = :i AND deletado_em IS NULL AND data_nascimento IS NOT NULL
                     ORDER BY MONTH(data_nascimento), DAY(data_nascimento)',
                    ['i' => $igrejaId]
                );
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r['nome'], $r['codigo'],
                        $r['data_nascimento'] ? date('d/m/Y', strtotime($r['data_nascimento'])) : '',
                        $r['idade'] ?? '',
                        $r['telefone'] ?? '',
                    ], ';');
                }
                break;

            default:
                fputcsv($out, ['Relatório desconhecido'], ';');
        }

        fclose($out);
        exit;
    }
}