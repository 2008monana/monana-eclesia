<?php
namespace App\Controllers\Igreja;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditoriaConsultaService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Leitura e PDF da auditoria limitada à igreja autenticada. */
final class AuditoriaController extends BaseIgrejaController
{
    public function index(): Response
    {
        $guard = $this->authorizeModule('audit', ['admin_igreja']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $data = $this->reportData();
        return $this->renderChurchModule('igreja/auditoria/index', $data, 'Auditoria', 'auditoria');
    }

    public function pdf(): Response
    {
        $guard = $this->authorizeModule('audit', ['admin_igreja']);
        if ($guard instanceof Response) {
            return $guard;
        }
        if (!class_exists('Dompdf\\Dompdf')) {
            return Response::text('A biblioteca Dompdf não está instalada neste alojamento.', 503);
        }
        $data = $this->reportData();
        $rows = '';
        foreach ($data['logs'] as $index => $log) {
            $rows .= '<tr><td>' . e($index + 1) . '</td><td>'
                . e(date('d/m/Y H:i', strtotime((string) ($log['criado_em'] ?? '')))) . '</td><td>'
                . e($log['usuario_nome'] ?? 'Sistema') . '</td><td>' . e($log['acao'] ?? '') . '</td><td>'
                . e($log['modulo'] ?? '') . '</td><td>' . e($log['descricao'] ?? '') . '</td><td>'
                . e($log['ip'] ?? '') . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="7">Não existem registos para os filtros seleccionados.</td></tr>';
        }
        $content = '<table><thead><tr><th>#</th><th>Data e hora</th><th>Utilizador</th><th>Acção</th><th>Módulo</th><th>Descrição</th><th>IP</th></tr></thead><tbody>'
            . $rows . '</tbody></table>';
        $user = Session::get('_auth_user', []);
        $churchName = is_array($user) ? (string) ($user['igreja_nome'] ?? 'Igreja') : 'Igreja';
        $html = (new View())->render('pdf/layout', [
            'title' => 'Relatório de auditoria',
            'subtitle' => 'Até ' . count($data['logs']) . ' registos · gerado em ' . date('d/m/Y H:i'),
            'churchName' => $churchName,
            'churchShortName' => $churchName,
            'content' => $content,
        ]);
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="auditoria-' . date('Y-m-d') . '.pdf"',
        ]);
    }

    private function reportData(): array
    {
        $data = (new AuditoriaConsultaService())->listar($this->churchId, [
            'usuario_id' => $this->request->query('usuario_id', 0),
            'modulo' => $this->request->query('modulo', ''),
            'acao' => $this->request->query('acao', ''),
            'data_inicio' => $this->request->query('data_inicio', ''),
            'data_fim' => $this->request->query('data_fim', ''),
        ]);
        $data['quantidade'] = count($data['logs']);
        return $data;
    }
}
