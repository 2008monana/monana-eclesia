<?php
namespace App\Controllers\Igreja;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\FimAnoService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Contribuições de fim de ano e respectivo fundo, sempre tenant-scoped. */
final class FimAnoController extends BaseIgrejaController
{
    public function index(): Response
    {
        $guard = $this->authorizeModule('year_end');
        if ($guard instanceof Response) {
            return $guard;
        }
        $data = (new FimAnoService())->listar(
            $this->churchId,
            $this->request->query('ano', date('Y')),
            $this->request->query('categoria', 'todos')
        );
        $data['mensagem'] = (string) Session::get('flash_fim_ano', '');
        Session::remove('flash_fim_ano');
        $data['podeGerir'] = in_array((string) ($this->actor['papel'] ?? ''), ['admin_igreja', 'tesoureiro'], true);
        return $this->renderChurchModule('igreja/fim-ano/index', $data, 'Contribuições de fim de ano', 'fim-ano');
    }

    public function pdf(): Response
    {
        $guard = $this->authorizeModule('year_end');
        if ($guard instanceof Response) {
            return $guard;
        }
        if (!class_exists('Dompdf\\Dompdf')) {
            return Response::text('A biblioteca Dompdf não está instalada neste alojamento.', 503);
        }
        $data = (new FimAnoService())->listar(
            $this->churchId,
            $this->request->query('ano', date('Y')),
            $this->request->query('categoria', 'todos')
        );
        $categoryLabels = ['mama' => 'Mamã', 'papa' => 'Papá', 'jovem' => 'Jovem'];
        $rows = '';
        foreach ($data['membros'] as $member) {
            $rows .= '<tr><td>' . e($member['nome_completo'] ?? '') . '</td><td>'
                . e($categoryLabels[$member['categoria'] ?? ''] ?? '—') . '</td><td>'
                . e(number_format((float) ($member['total_pago'] ?? 0), 2, ',', '.')) . ' Kz</td><td>'
                . e(number_format((float) ($member['saldo_restante'] ?? 0), 2, ',', '.')) . ' Kz</td><td>'
                . (!empty($member['esta_quitado']) ? 'Quitado' : 'Pendente') . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5">Não existem membros para este relatório.</td></tr>';
        }
        $content = '<p>Meta anual por membro: ' . e(number_format((float) $data['metaAnual'], 2, ',', '.'))
            . ' Kz · Total arrecadado: ' . e(number_format((float) $data['totalArrecadado'], 2, ',', '.'))
            . ' Kz · Saldo do fundo: ' . e(number_format((float) $data['saldoFundo'], 2, ',', '.'))
            . ' Kz</p><table><thead><tr><th>Membro</th><th>Categoria</th><th>Total pago</th><th>Saldo restante</th><th>Estado</th></tr></thead><tbody>'
            . $rows . '</tbody></table>';
        $user = Session::get('_auth_user', []);
        $churchName = is_array($user) ? (string) ($user['igreja_nome'] ?? 'Igreja') : 'Igreja';
        $html = (new View())->render('pdf/layout', [
            'title' => 'Contribuições de fim de ano',
            'subtitle' => 'Ano ' . e($data['ano']) . ' · gerado em ' . date('d/m/Y H:i'),
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
            'Content-Disposition' => 'attachment; filename="fim-de-ano-' . (int) $data['ano'] . '.pdf"',
        ]);
    }

    public function registar(): Response
    {
        $guard = $this->authorizeModule('year_end', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $service = new FimAnoService();
        $result = $service->registar(
            $this->churchId,
            (int) ($this->actor['id'] ?? 0),
            [
                'membro_id' => $this->request->post('membro_id', ''),
                'ano' => $this->request->post('ano', ''),
                'valor' => $this->request->post('valor', ''),
            ],
            $this->request
        );
        $errors = is_array($result['erros'] ?? null) ? $result['erros'] : [];
        $message = !empty($result['valido'])
            ? 'Contribuição registada.'
            : (string) (($errors !== [] ? reset($errors) : '') ?: 'Não foi possível registar a contribuição.');
        Session::set('flash_fim_ano', $message);
        $year = (int) $this->request->post('ano', date('Y'));
        return Response::redirect(url('contribuicoes-fim-de-ano') . '?ano=' . max(2000, min(2100, $year)), 303);
    }

    public function remover(): Response
    {
        $guard = $this->authorizeModule('year_end', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->positiveInt($this->request->post('membro_id', null));
        $year = $this->validYear($this->request->post('ano', null));
        $removed = $memberId !== null && $year !== null
            ? (new FimAnoService())->remover($this->churchId, $memberId, $year, $this->request)
            : false;
        Session::set('flash_fim_ano', $removed ? 'Contribuições do membro removidas.' : 'Não foram encontradas contribuições para remover.');
        return Response::redirect(url('contribuicoes-fim-de-ano') . '?ano=' . (int) ($year ?? date('Y')), 303);
    }

    public function alternarEstado(): Response
    {
        $guard = $this->authorizeModule('year_end', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->positiveInt($this->request->post('membro_id', null));
        $year = $this->validYear($this->request->post('ano', null));
        $actionValue = filter_var($this->request->post('acao', null), FILTER_VALIDATE_INT);
        $action = $actionValue === false || $actionValue === null ? null : (int) $actionValue;
        $result = $memberId !== null && $year !== null && in_array($action, [0, 1], true)
            ? (new FimAnoService())->alternarEstado(
                $this->churchId,
                (int) ($this->actor['id'] ?? 0),
                $memberId,
                $year,
                (int) $action,
                $this->request
            )
            : ['sucesso' => false, 'erro' => 'Dados inválidos.'];
        Session::set('flash_fim_ano', !empty($result['sucesso']) ? 'Estado da contribuição actualizado.' : (string) ($result['erro'] ?? 'Não foi possível alterar o estado.'));
        return Response::redirect(url('contribuicoes-fim-de-ano') . '?ano=' . (int) ($year ?? date('Y')), 303);
    }

    public function saldo(): Response
    {
        $guard = $this->authorizeModule('year_end');
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->positiveInt($this->request->query('membro_id', $this->request->post('membro_id', null)));
        $year = $this->validYear($this->request->query('ano', $this->request->post('ano', null)));
        if ($memberId === null || $year === null) {
            return Response::json(['success' => false, 'message' => 'Dados inválidos.'], 400);
        }
        $data = (new FimAnoService())->saldo($this->churchId, $memberId, $year);
        return empty($data['encontrado'])
            ? Response::json(['success' => false, 'message' => 'Membro não encontrado.'], 404)
            : Response::json(['success' => true, 'total_pago' => $data['total_pago'], 'saldo_restante' => $data['saldo_restante'], 'esta_quitado' => $data['esta_quitado']]);
    }

    private function validYear($value): ?int
    {
        $year = filter_var($value, FILTER_VALIDATE_INT);
        return $year !== false && $year !== null && (int) $year >= 2000 && (int) $year <= 2100
            ? (int) $year
            : null;
    }

    private function positiveInt($value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        return $id !== false && $id !== null && (int) $id > 0 ? (int) $id : null;
    }
}
