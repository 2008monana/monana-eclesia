<?php
namespace App\Controllers\Igreja;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\CotaService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Registo diário de cotas, tenant-scoped e sem SQL no controller. */
final class CotaController extends BaseIgrejaController
{
    public function index(): Response
    {
        $guard = $this->authorizeModule('registry');
        if ($guard instanceof Response) {
            return $guard;
        }
        $data = (new CotaService())->listar($this->churchId, $this->request->query('data', date('Y-m-d')));
        $messages = [
            'criado' => 'Pagamento de cota registado.',
            'actualizado' => 'Pagamento de cota actualizado.',
            'apagado' => 'Pagamento de hoje removido.',
        ];
        $notice = $this->request->query('resultado', '');
        $data['mensagem'] = is_string($notice) && isset($messages[$notice]) ? $messages[$notice] : '';
        $data['podeGerir'] = in_array((string) ($this->actor['papel'] ?? ''), ['admin_igreja', 'tesoureiro'], true);
        return $this->renderChurchModule('igreja/cotas/index', $data, 'Cotas', 'cotas');
    }

    public function pdf(): Response
    {
        $guard = $this->authorizeModule('registry');
        if ($guard instanceof Response) {
            return $guard;
        }
        if (!class_exists('Dompdf\\Dompdf')) {
            return Response::text('A biblioteca Dompdf não está instalada neste alojamento.', 503);
        }
        $data = (new CotaService())->listar($this->churchId, $this->request->query('data', date('Y-m-d')));
        $categories = ['mama' => 'Mamã', 'papa' => 'Papá', 'jovem' => 'Jovem', 'crianca' => 'Criança'];
        $periodicity = (string) ($data['configuracao']['periodicidade_cota'] ?? 'mensal');
        $rows = '';
        foreach ($data['registos'] as $record) {
            $referenceTime = strtotime((string) ($record['mes_referencia'] ?? ''));
            $referenceLabel = $referenceTime === false
                ? '—'
                : ($periodicity === 'anual' ? date('d/m/Y', $referenceTime) : date('m/Y', $referenceTime));
            $rows .= '<tr><td>' . e($record['nome_completo'] ?? '') . '</td><td>'
                . e($categories[$record['categoria'] ?? ''] ?? '—') . '</td><td>'
                . e($referenceLabel) . '</td><td>'
                . e(number_format((float) ($record['valor'] ?? 0), 2, ',', '.')) . ' Kz</td><td>'
                . e($record['registrado_nome'] ?? '') . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5">Não existem pagamentos para esta data.</td></tr>';
        }
        $content = '<p>Total de registos: ' . e(count($data['registos'])) . ' · Total: '
            . e(number_format(array_sum(array_map(static function (array $record): float { return (float) ($record['valor'] ?? 0); }, $data['registos'])), 2, ',', '.'))
            . ' Kz</p><table><thead><tr><th>Membro</th><th>Categoria</th><th>Referência</th><th>Valor</th><th>Registado por</th></tr></thead><tbody>'
            . $rows . '</tbody></table>';
        $user = Session::get('_auth_user', []);
        $churchName = is_array($user) ? (string) ($user['igreja_nome'] ?? 'Igreja') : 'Igreja';
        $html = (new View())->render('pdf/layout', [
            'title' => 'Registo de cotas',
            'subtitle' => 'Pagamentos de ' . date('d/m/Y', strtotime($data['data'])),
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
            'Content-Disposition' => 'attachment; filename="cotas-' . date('Y-m-d', strtotime($data['data'])) . '.pdf"',
        ]);
    }

    public function createForm(): Response
    {
        $guard = $this->authorizeModule('registry');
        if ($guard instanceof Response) {
            return $guard;
        }
        return $this->renderChurchModule('igreja/cotas/form', array_merge(
            (new CotaService())->formulario($this->churchId),
            ['titulo' => 'Registar pagamento', 'action' => 'cotas/novo', 'dados' => ['membro_id' => '', 'valor' => '', 'observacao' => ''], 'erros' => []]
        ), 'Registar pagamento', 'cotas');
    }

    public function create(): Response
    {
        $guard = $this->authorizeModule('registry');
        if ($guard instanceof Response) {
            return $guard;
        }
        $service = new CotaService();
        $result = $service->criar(
            $this->churchId,
            (int) ($this->actor['id'] ?? 0),
            $this->paymentInput(),
            $this->request
        );
        if (!$result['valido']) {
            $form = array_merge($service->formulario($this->churchId), [
                'titulo' => 'Registar pagamento',
                'action' => 'cotas/novo',
                'dados' => $result['dados'],
                'erros' => $result['erros'],
            ]);
            return $this->renderChurchModule('igreja/cotas/form', $form, 'Registar pagamento', 'cotas', 422);
        }
        return Response::redirect(url('cotas') . '?resultado=criado', 303);
    }

    public function editForm(): Response
    {
        $guard = $this->authorizeModule('registry', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $paymentId = $this->routeId();
        $service = new CotaService();
        $payment = $paymentId !== null ? $service->encontrar($this->churchId, $paymentId) : null;
        if ($payment === null) {
            return $this->notFound();
        }
        if ((string) $payment['data_pagamento'] !== date('Y-m-d')) {
            return Response::text('Só é possível editar registos do dia corrente.', 403);
        }
        return $this->renderChurchModule('igreja/cotas/form', array_merge(
            $service->formulario($this->churchId),
            ['titulo' => 'Editar pagamento', 'action' => 'cotas/' . $paymentId . '/editar', 'dados' => $payment, 'erros' => [], 'registoId' => $paymentId]
        ), 'Editar pagamento', 'cotas');
    }

    /** Redirecciona formulários antigos com ?id= para o URL canónico. */
    public function editByQuery(): Response
    {
        $guard = $this->authorizeModule('registry', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $paymentId = $this->positiveId($this->request->query('id', null));
        return $paymentId === null
            ? $this->notFound()
            : Response::redirect(url('cotas/' . $paymentId . '/editar'), 301);
    }

    public function update(): Response
    {
        $guard = $this->authorizeModule('registry', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $paymentId = $this->routeId();
        return $paymentId === null ? $this->notFound() : $this->updateRecord($paymentId);
    }

    public function updateByQuery(): Response
    {
        $guard = $this->authorizeModule('registry', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $paymentId = $this->positiveId($this->request->query('id', null));
        return $paymentId === null ? $this->notFound() : $this->updateRecord($paymentId);
    }

    private function updateRecord(int $paymentId): Response
    {
        $service = new CotaService();
        $result = $service->actualizar($this->churchId, $paymentId, $this->paymentInput(), $this->request);
        if (empty($result['encontrado'])) {
            return $this->notFound();
        }
        if (!empty($result['bloqueado'])) {
            return Response::text('Só é possível editar registos do dia corrente.', 403);
        }
        if (!$result['valido']) {
            return $this->renderChurchModule('igreja/cotas/form', array_merge(
                $service->formulario($this->churchId),
                [
                    'titulo' => 'Editar pagamento',
                    'action' => 'cotas/' . $paymentId . '/editar',
                    'dados' => $result['dados'] ?? [],
                    'erros' => $result['erros'] ?? [],
                    'registoId' => $paymentId,
                ]
            ), 'Editar pagamento', 'cotas', 422);
        }
        return Response::redirect(url('cotas') . '?resultado=actualizado', 303);
    }

    public function delete(): Response
    {
        $guard = $this->authorizeModule('registry', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $paymentId = $this->routeId();
        if ($paymentId === null) {
            return $this->notFound();
        }
        $result = (new CotaService())->apagar($this->churchId, $paymentId, $this->request);
        if (empty($result['encontrado'])) {
            return $this->notFound();
        }
        if (!empty($result['bloqueado'])) {
            return Response::text('Só é possível remover registos do dia corrente.', 403);
        }
        return Response::redirect(url('cotas') . '?resultado=apagado', 303);
    }

    /** Endpoint compatível com o AJAX antigo, com tenant e CSRF obrigatórios. */
    public function deleteByPost(): Response
    {
        $guard = $this->authorizeModule('registry', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $paymentId = $this->positiveId($this->request->post('id', null));
        if ($paymentId === null) {
            return Response::json(['success' => false, 'message' => 'Registo inválido.'], 400);
        }
        $result = (new CotaService())->apagar($this->churchId, $paymentId, $this->request);
        if (empty($result['encontrado'])) {
            return Response::json(['success' => false, 'message' => 'Registo não encontrado.'], 404);
        }
        if (empty($result['apagado'])) {
            return Response::json(['success' => false, 'message' => 'Só é possível remover registos do dia corrente.'], 403);
        }
        return Response::json(['success' => true]);
    }

    private function routeId(): ?int
    {
        return $this->positiveId($this->request->route('id', null));
    }

    private function positiveId($value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        return $id !== false && $id !== null && (int) $id > 0 ? (int) $id : null;
    }

    private function paymentInput(): array
    {
        return [
            'membro_id' => $this->request->post('membro_id', ''),
            'valor' => $this->request->post('valor', ''),
            'observacao' => $this->request->post('observacao', ''),
        ];
    }
}
