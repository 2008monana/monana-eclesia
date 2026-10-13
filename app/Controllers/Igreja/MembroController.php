<?php
namespace App\Controllers\Igreja;

use App\Core\Response;
use App\Services\MembroService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** CRUD seguro de membros, sempre restrito à igreja da sessão validada. */
final class MembroController extends BaseIgrejaController
{
    /** Lista paginada com filtros aplicados no servidor e estatísticas do tenant. */
    public function index(): Response
    {
        $guard = $this->authorizeModule('members');
        if ($guard instanceof Response) {
            return $guard;
        }

        $data = (new MembroService())->listar($this->churchId, [
            'q' => $this->request->query('q', ''),
            'categoria' => $this->request->query('categoria', ''),
            'estado' => $this->request->query('estado', 'activo'),
            'pagina' => $this->request->query('pagina', 1),
        ]);
        $messages = [
            'criado' => 'Membro adicionado com sucesso.',
            'actualizado' => 'Dados do membro actualizados.',
            'estado' => 'Estado do membro actualizado.',
            'removido' => 'Membro sem histórico removido.',
            'desactivado' => 'Membro desactivado para preservar o histórico financeiro.',
        ];
        $result = $this->request->query('resultado', '');
        $data['mensagem'] = is_string($result) && isset($messages[$result]) ? $messages[$result] : '';
        $data['isAdmin'] = ($this->actor['papel'] ?? '') === 'admin_igreja';
        return $this->renderChurchModule('igreja/membros/index', $data, 'Membros', 'membros');
    }

    public function createForm(): Response
    {
        $guard = $this->authorizeModule('members');
        if ($guard instanceof Response) {
            return $guard;
        }
        return $this->renderChurchModule('igreja/membros/form', [
            'titulo' => 'Adicionar membro',
            'action' => 'membros/novo',
            'membroId' => null,
            'dados' => $this->emptyMember(),
            'erros' => [],
        ], 'Adicionar membro', 'membros');
    }

    public function create(): Response
    {
        $guard = $this->authorizeModule('members');
        if ($guard instanceof Response) {
            return $guard;
        }
        $result = (new MembroService())->criar($this->churchId, $this->memberInput(), $this->request);
        if (!$result['valido']) {
            return $this->renderChurchModule('igreja/membros/form', [
                'titulo' => 'Adicionar membro',
                'action' => 'membros/novo',
                'membroId' => null,
                'dados' => $result['dados'],
                'erros' => $result['erros'],
            ], 'Adicionar membro', 'membros', 422);
        }
        return Response::redirect(url('membros') . '?resultado=criado', 303);
    }

    public function editForm(): Response
    {
        $guard = $this->authorizeModule('members');
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->routeId();
        return $memberId === null ? $this->notFound() : $this->editRecord($memberId);
    }

    /** Redirecciona os antigos GET /membros/editar?id=... para o URL canónico. */
    public function editByQuery(): Response
    {
        $guard = $this->authorizeModule('members');
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->queryId();
        return $memberId === null
            ? $this->notFound()
            : Response::redirect(url('membros/' . $memberId . '/editar'), 301);
    }

    public function update(): Response
    {
        $guard = $this->authorizeModule('members');
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->routeId();
        return $memberId === null ? $this->notFound() : $this->updateRecord($memberId);
    }

    /** Compatibilidade transitória com formulários antigos que enviam ?id=. */
    public function updateByQuery(): Response
    {
        $guard = $this->authorizeModule('members');
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->queryId();
        return $memberId === null ? $this->notFound() : $this->updateRecord($memberId);
    }

    public function show(): Response
    {
        $guard = $this->authorizeModule('members');
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->routeId();
        return $memberId === null ? $this->notFound() : $this->showRecord($memberId);
    }

    /** Redirecciona os antigos GET /membros/ver?id=... para o URL canónico. */
    public function showByQuery(): Response
    {
        $guard = $this->authorizeModule('members');
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->queryId();
        return $memberId === null
            ? $this->notFound()
            : Response::redirect(url('membros/' . $memberId), 301);
    }

    /** Redirecciona o antigo /membros/{id}/ver para o detalhe canónico. */
    public function showByLegacyRoute(): Response
    {
        $guard = $this->authorizeModule('members');
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->routeId();
        return $memberId === null
            ? $this->notFound()
            : Response::redirect(url('membros/' . $memberId), 301);
    }

    /** A alteração do estado é POST + CSRF e calcula o novo estado no servidor. */
    public function toggleStatus(): Response
    {
        $guard = $this->authorizeModule('members');
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->routeId();
        if ($memberId === null || $this->toggleRecord($memberId) === null) {
            return $this->notFound();
        }
        return Response::redirect(url('membros') . '?resultado=estado', 303);
    }

    /** Compatibilidade com o endpoint AJAX antigo; ignora o estado enviado pelo cliente. */
    public function toggleByPost(): Response
    {
        $guard = $this->authorizeModule('members');
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->postId();
        if ($memberId === null) {
            return Response::json(['success' => false, 'message' => 'Membro inválido.'], 400);
        }
        if ($this->toggleRecord($memberId) === null) {
            return Response::json(['success' => false, 'message' => 'Membro não encontrado.'], 404);
        }
        return Response::json(['success' => true]);
    }

    /** A remoção é exclusiva do admin; o service desactiva se houver histórico. */
    public function delete(): Response
    {
        $guard = $this->authorizeModule('members', ['admin_igreja']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->routeId();
        if ($memberId === null) {
            return $this->notFound();
        }
        $result = $this->deleteRecord($memberId);
        if ($result === null) {
            return $this->notFound();
        }
        $notice = !empty($result['removido']) ? 'removido' : 'desactivado';
        return Response::redirect(url('membros') . '?resultado=' . $notice, 303);
    }

    /** Compatibilidade com o endpoint antigo; continua POST, CSRF e restrito ao admin. */
    public function deleteByPost(): Response
    {
        $guard = $this->authorizeModule('members', ['admin_igreja']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $memberId = $this->postId();
        if ($memberId === null) {
            return Response::json(['success' => false, 'message' => 'Membro inválido.'], 400);
        }
        $result = $this->deleteRecord($memberId);
        if ($result === null) {
            return Response::json(['success' => false, 'message' => 'Membro não encontrado.'], 404);
        }
        return Response::json([
            'success' => true,
            'message' => !empty($result['removido'])
                ? 'Membro removido.'
                : 'Membro desactivado para preservar o histórico financeiro.',
        ]);
    }

    private function editRecord(int $memberId): Response
    {
        $member = (new MembroService())->encontrar($this->churchId, $memberId);
        if ($member === null) {
            return $this->notFound();
        }
        return $this->renderChurchModule('igreja/membros/form', [
            'titulo' => 'Editar membro',
            'action' => 'membros/' . $memberId . '/editar',
            'membroId' => $memberId,
            'dados' => $member,
            'erros' => [],
        ], 'Editar membro', 'membros');
    }

    private function updateRecord(int $memberId): Response
    {
        $result = (new MembroService())->actualizar(
            $this->churchId,
            $memberId,
            $this->memberInput(),
            $this->request
        );
        if (empty($result['encontrado'])) {
            return $this->notFound();
        }
        if (!$result['valido']) {
            return $this->renderChurchModule('igreja/membros/form', [
                'titulo' => 'Editar membro',
                'action' => 'membros/' . $memberId . '/editar',
                'membroId' => $memberId,
                'dados' => $result['dados'],
                'erros' => $result['erros'],
            ], 'Editar membro', 'membros', 422);
        }
        return Response::redirect(url('membros') . '?resultado=actualizado', 303);
    }

    private function showRecord(int $memberId): Response
    {
        $member = (new MembroService())->encontrar($this->churchId, $memberId);
        if ($member === null) {
            return $this->notFound();
        }
        return $this->renderChurchModule('igreja/membros/show', [
            'membro' => $member,
            'isAdmin' => ($this->actor['papel'] ?? '') === 'admin_igreja',
        ], 'Dados do membro', 'membros');
    }

    private function toggleRecord(int $memberId): ?array
    {
        return (new MembroService())->alternarEstado($this->churchId, $memberId, $this->request);
    }

    private function deleteRecord(int $memberId): ?array
    {
        return (new MembroService())->remover($this->churchId, $memberId, $this->request);
    }

    private function routeId(): ?int
    {
        return $this->positiveId($this->request->route('id', null));
    }

    private function queryId(): ?int
    {
        return $this->positiveId($this->request->query('id', null));
    }

    private function postId(): ?int
    {
        return $this->positiveId($this->request->post('id', null));
    }

    private function positiveId($value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        return $id !== false && $id !== null && (int) $id > 0 ? (int) $id : null;
    }

    private function memberInput(): array
    {
        return [
            'nome' => $this->request->post('nome', ''),
            'categoria' => $this->request->post('categoria', ''),
            'telefone' => $this->request->post('telefone', ''),
            'endereco' => $this->request->post('endereco', ''),
            'data_nascimento' => $this->request->post('data_nascimento', ''),
        ];
    }

    private function emptyMember(): array
    {
        return [
            'nome_completo' => '',
            'categoria' => '',
            'telefone' => '',
            'endereco' => '',
            'data_nascimento' => '',
        ];
    }
}
