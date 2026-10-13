<?php
namespace App\Controllers\Igreja;

use App\Core\Response;
use App\Core\Session;
use App\Services\RelatorioService;
use App\Core\View;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Relatórios e PDF com agregações recebidas exclusivamente do repository tenant-scoped. */
final class RelatorioController extends BaseIgrejaController
{
    public function financeiro(): Response
    {
        $guard = $this->authorizeModule('reports');
        if ($guard instanceof Response) {
            return $guard;
        }
        $data = (new RelatorioService())->periodo(
            $this->churchId,
            $this->request->query('inicio', date('Y-m-01')),
            $this->request->query('fim', date('Y-m-d'))
        );
        $data['tipo'] = 'financeiro';
        return $this->renderChurchModule('igreja/relatorios/index', $data, 'Relatório financeiro', 'relatorios');
    }

    public function anual(): Response
    {
        $guard = $this->authorizeModule('reports');
        if ($guard instanceof Response) {
            return $guard;
        }
        $data = (new RelatorioService())->anual($this->churchId, $this->request->query('ano', date('Y')));
        $data['tipo'] = 'anual';
        return $this->renderChurchModule('igreja/relatorios/anual', $data, 'Relatório anual', 'relatorios');
    }

    public function saidas(): Response
    {
        $guard = $this->authorizeModule('reports');
        if ($guard instanceof Response) {
            return $guard;
        }
        $data = (new RelatorioService())->saidas(
            $this->churchId,
            $this->request->query('inicio', date('Y-m-01')),
            $this->request->query('fim', date('Y-m-d'))
        );
        $data['tipo'] = 'saidas';
        return $this->renderChurchModule('igreja/relatorios/index', $data, 'Relatório de saídas', 'relatorios');
    }

    public function excel(): Response
    {
        $guard = $this->authorizeModule('reports');
        if ($guard instanceof Response) {
            return $guard;
        }
        $data = (new RelatorioService())->periodo(
            $this->churchId,
            $this->request->query('inicio', date('Y-m-01')),
            $this->request->query('fim', date('Y-m-d'))
        );
        $summary = $data['resumo'];
        $rows = [
            ['Relatório MonanaEclésia', 'Valor (Kz)'],
            ['Período', date('d/m/Y', strtotime($data['inicio'])) . ' a ' . date('d/m/Y', strtotime($data['fim']))],
            ['Entradas — Cotas', $summary['cotas']],
            ['Entradas — Contribuições', $summary['contribuicoes']],
            ['Saídas — Fundo de cotas', $summary['saidas_cotas']],
            ['Saídas — Fundo de contribuições', $summary['saidas_contribuicoes']],
            ['Saldo — Fundo de cotas', $summary['saldo_cotas']],
            ['Saldo — Fundo de contribuições', $summary['saldo_contribuicoes']],
        ];
        $csv = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $csv .= $this->csvCell((string) $row[0]) . ',' . $this->csvCell((string) $row[1]) . "\r\n";
        }
        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="relatorio-financeiro.csv"',
        ]);
    }

    public function pdf(): Response
    {
        $guard = $this->authorizeModule('reports');
        if ($guard instanceof Response) {
            return $guard;
        }
        $data = (new RelatorioService())->periodo(
            $this->churchId,
            $this->request->query('inicio', date('Y-m-01')),
            $this->request->query('fim', date('Y-m-d'))
        );
        $summary = $data['resumo'];
        $content = '<table><thead><tr><th>Movimento</th><th>Valor (Kz)</th></tr></thead><tbody>'
            . $this->pdfRow('Entradas — Cotas', (float) $summary['cotas'])
            . $this->pdfRow('Entradas — Contribuições', (float) $summary['contribuicoes'])
            . $this->pdfRow('Saídas — Fundo de cotas', (float) $summary['saidas_cotas'])
            . $this->pdfRow('Saídas — Fundo de contribuições', (float) $summary['saidas_contribuicoes'])
            . $this->pdfRow('Saldo — Fundo de cotas', (float) $summary['saldo_cotas'])
            . $this->pdfRow('Saldo — Fundo de contribuições', (float) $summary['saldo_contribuicoes'])
            . '</tbody></table>';
        return $this->renderPdf('Relatório financeiro', date('d/m/Y', strtotime($data['inicio'])) . ' a ' . date('d/m/Y', strtotime($data['fim'])), $content, 'relatorio-financeiro.pdf');
    }

    public function pdfSaidas(): Response
    {
        $guard = $this->authorizeModule('reports');
        if ($guard instanceof Response) {
            return $guard;
        }
        $data = (new RelatorioService())->saidas(
            $this->churchId,
            $this->request->query('inicio', date('Y-m-01')),
            $this->request->query('fim', date('Y-m-d'))
        );
        $content = '<table><thead><tr><th>Fundo</th><th>Categoria</th><th>Total (Kz)</th></tr></thead><tbody>';
        foreach ($data['categorias'] as $row) {
            $fund = ($row['origem_fundo'] ?? '') === 'cotas' ? 'Cotas' : 'Contribuições';
            $content .= '<tr><td>' . e($fund) . '</td><td>' . e($row['categoria'] ?? '') . '</td><td>'
                . e(number_format((float) ($row['total'] ?? 0), 2, ',', '.')) . '</td></tr>';
        }
        $content .= '</tbody></table>';
        return $this->renderPdf('Relatório de saídas', date('d/m/Y', strtotime($data['inicio'])) . ' a ' . date('d/m/Y', strtotime($data['fim'])), $content, 'relatorio-saidas.pdf');
    }

    private function renderPdf(string $title, string $subtitle, string $content, string $filename): Response
    {
        if (!class_exists('Dompdf\\Dompdf')) {
            return Response::text('A biblioteca Dompdf não está instalada neste alojamento.', 503);
        }
        $user = Session::get('_auth_user', []);
        $churchName = is_array($user) ? (string) ($user['igreja_nome'] ?? 'Igreja') : 'Igreja';
        $html = (new View())->render('pdf/layout', [
            'title' => $title,
            'subtitle' => $subtitle,
            'churchName' => $churchName,
            'churchShortName' => $churchName,
            'content' => $content,
        ]);
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function pdfRow(string $label, float $amount): string
    {
        return '<tr><td>' . e($label) . '</td><td>' . e(number_format($amount, 2, ',', '.')) . '</td></tr>';
    }

    private function csvCell(string $value): string
    {
        if (preg_match('/^[\s]*[=+@\-]/', $value)) {
            $value = "'" . $value;
        }
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
