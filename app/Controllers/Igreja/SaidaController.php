<?php
namespace App\Controllers\Igreja;

use App\Core\Response;
use App\Services\SaidaService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** CRUD de saídas com validação do fundo e tenant na camada de serviço/repositório. */
final class SaidaController extends BaseIgrejaController
{
    public function index(): Response
    {
        $guard = $this->authorizeModule('expenses');
        if ($guard instanceof Response) {
            return $guard;
        }
        $data = (new SaidaService())->listar($this->churchId);
        $notice = $this->request->query('resultado', '');
        $data['mensagem'] = $notice === 'guardada' ? 'Saída registada.' : ($notice === 'actualizada' ? 'Saída actualizada.' : '');
        $data['podeGerir'] = in_array((string) ($this->actor['papel'] ?? ''), ['admin_igreja', 'tesoureiro'], true);
        return $this->renderChurchModule('igreja/saidas/index', $data, 'Saídas', 'saidas');
    }

    public function createForm(): Response
    {
        $guard = $this->authorizeModule('expenses', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        return $this->renderChurchModule('igreja/saidas/form', [
            'titulo' => 'Registar saída',
            'action' => 'saidas/nova',
            'saidaId' => null,
            'dados' => ['descricao' => '', 'valor' => '', 'data_saida' => date('Y-m-d'), 'categoria' => '', 'origem_fundo' => 'cotas', 'beneficiario' => ''],
            'erros' => [],
        ], 'Registar saída', 'saidas');
    }

    public function create(): Response
    {
        $guard = $this->authorizeModule('expenses', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $result = (new SaidaService())->criar($this->churchId, (int) ($this->actor['id'] ?? 0), $this->input(), $this->request);
        if (!$result['valido']) {
            return $this->renderChurchModule('igreja/saidas/form', [
                'titulo' => 'Registar saída', 'action' => 'saidas/nova', 'saidaId' => null,
                'dados' => $result['dados'], 'erros' => $result['erros'],
            ], 'Registar saída', 'saidas', 422);
        }
        return Response::redirect(url('saidas') . '?resultado=guardada', 303);
    }

    public function editForm(): Response
    {
        $guard = $this->authorizeModule('expenses', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->routeId();
        $expense = $id !== null ? (new SaidaService())->encontrar($this->churchId, $id) : null;
        if ($expense === null) {
            return $this->notFound();
        }
        return $this->renderChurchModule('igreja/saidas/form', [
            'titulo' => 'Editar saída', 'action' => 'saidas/' . $id . '/editar', 'saidaId' => $id,
            'dados' => $expense, 'erros' => [],
        ], 'Editar saída', 'saidas');
    }

    public function editByQuery(): Response
    {
        $guard = $this->authorizeModule('expenses', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->positiveId($this->request->query('id', null));
        return $id === null ? $this->notFound() : Response::redirect(url('saidas/' . $id . '/editar'), 301);
    }

    public function update(): Response
    {
        $guard = $this->authorizeModule('expenses', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->routeId();
        return $id === null ? $this->notFound() : $this->updateRecord($id);
    }

    public function updateByQuery(): Response
    {
        $guard = $this->authorizeModule('expenses', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->positiveId($this->request->query('id', null));
        return $id === null ? $this->notFound() : $this->updateRecord($id);
    }

    private function updateRecord(int $id): Response
    {
        $service = new SaidaService();
        $result = $service->actualizar($this->churchId, $id, $this->input(), $this->request);
        if (empty($result['encontrado'])) {
            return $this->notFound();
        }
        if (empty($result['valido'])) {
            return $this->renderChurchModule('igreja/saidas/form', [
                'titulo' => 'Editar saída', 'action' => 'saidas/' . $id . '/editar', 'saidaId' => $id,
                'dados' => $result['dados'] ?? [], 'erros' => $result['erros'] ?? [],
            ], 'Editar saída', 'saidas', 422);
        }
        return Response::redirect(url('saidas') . '?resultado=actualizada', 303);
    }

    public function delete(): Response
    {
        $guard = $this->authorizeModule('expenses', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->routeId();
        if ($id === null || !(new SaidaService())->apagar($this->churchId, $id, $this->request)) {
            return $this->notFound();
        }
        return Response::redirect(url('saidas'), 303);
    }

    public function deleteByPost(): Response
    {
        $guard = $this->authorizeModule('expenses', ['admin_igreja', 'tesoureiro']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->positiveId($this->request->post('id', null));
        if ($id === null) {
            return Response::json(['success' => false, 'message' => 'Saída inválida.'], 400);
        }
        if (!(new SaidaService())->apagar($this->churchId, $id, $this->request)) {
            return Response::json(['success' => false, 'message' => 'Saída não encontrada.'], 404);
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

    private function input(): array
    {
        return [
            'descricao' => $this->request->post('descricao', ''),
            'valor' => $this->request->post('valor', ''),
            'data_saida' => $this->request->post('data_saida', ''),
            'categoria' => $this->request->post('categoria', ''),
            'origem_fundo' => $this->request->post('origem_fundo', ''),
            'beneficiario' => $this->request->post('beneficiario', ''),
        ];
    }
}
