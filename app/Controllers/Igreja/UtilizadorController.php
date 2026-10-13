<?php
namespace App\Controllers\Igreja;

use App\Core\Response;
use App\Core\Session;
use App\Services\UsuarioService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Administração de utilizadores locais; o Superadmin continua em /superadmin. */
final class UtilizadorController extends BaseIgrejaController
{
    public function index(): Response
    {
        $guard = $this->guardUsers();
        if ($guard instanceof Response) {
            return $guard;
        }
        $data = (new UsuarioService())->listar(
            $this->churchId,
            $this->request->query('papel', ''),
            $this->request->query('estado', '')
        );
        $data['mensagem'] = (string) Session::get('flash_utilizadores', '');
        Session::remove('flash_utilizadores');
        return $this->renderChurchModule('igreja/utilizadores/index', $data, 'Utilizadores', 'utilizadores');
    }

    public function createForm(): Response
    {
        $guard = $this->guardUsers();
        if ($guard instanceof Response) {
            return $guard;
        }
        return $this->form(null, [
            'nome_completo' => '', 'email' => '', 'papel' => 'tesoureiro', 'modulos' => ['dashboard'],
        ], [], 'Novo utilizador');
    }

    public function create(): Response
    {
        $guard = $this->guardUsers();
        if ($guard instanceof Response) {
            return $guard;
        }
        $input = $this->input();
        $result = (new UsuarioService())->criar($this->churchId, (int) $this->actor['id'], $input, $this->request);
        if (empty($result['sucesso'])) {
            return $this->form(null, $input, (array) ($result['erros'] ?? []), 'Novo utilizador', 422);
        }
        Session::set('flash_utilizadores', !empty($result['email_enviado'])
            ? 'Utilizador criado. Foi enviado um link seguro para definir a senha.'
            : 'Utilizador criado; o email ficou na fila para reenvio quando o transporte estiver disponível.');
        return Response::redirect(url('utilizadores'), 303);
    }

    public function editForm(): Response
    {
        $guard = $this->guardUsers();
        if ($guard instanceof Response) {
            return $guard;
        }
        $user = $this->serviceUser($this->routeId());
        if ($user === null) {
            return $this->notFound();
        }
        return $this->form((int) $user['id'], $user, [], 'Editar utilizador');
    }

    public function editByQuery(): Response
    {
        $guard = $this->guardUsers();
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->positiveInt($this->request->query('id', null));
        return $id === null ? $this->notFound() : Response::redirect(url('utilizadores/' . $id . '/editar'), 301);
    }

    public function update(): Response
    {
        $guard = $this->guardUsers();
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->routeId();
        return $id === null ? $this->notFound() : $this->saveUpdate($id);
    }

    /** Compatibilidade com formulários antigos que enviavam id na query. */
    public function updateByQuery(): Response
    {
        $guard = $this->guardUsers();
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->positiveInt($this->request->post('id', $this->request->query('id', null)));
        return $id === null ? $this->notFound() : $this->saveUpdate($id);
    }

    public function toggleStatus(): Response
    {
        return $this->changeStatus(false);
    }

    public function remover(): Response
    {
        return $this->changeStatus(true);
    }

    public function enviarLinkSenha(): Response
    {
        $guard = $this->guardUsers();
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->targetId();
        $result = $id === null
            ? ['sucesso' => false, 'erro' => 'Utilizador não encontrado.']
            : (new UsuarioService())->enviarLinkSenha($this->churchId, (int) $this->actor['id'], $id, $this->request);
        if (!empty($result['sucesso'])) {
            Session::set('flash_utilizadores', !empty($result['email_enviado'])
                ? 'Foi enviado um link de uso único para definir uma nova senha.'
                : 'O link ficou na fila para reenvio; não foi mostrada nem guardada qualquer senha em claro.');
        } else {
            Session::set('flash_utilizadores', (string) ($result['erro'] ?? 'Não foi possível preparar o link.'));
        }
        return Response::redirect(url('utilizadores'), 303);
    }

    private function saveUpdate(int $id): Response
    {
        $service = new UsuarioService();
        $input = $this->input();
        $result = $service->actualizar($this->churchId, (int) $this->actor['id'], $id, $input, $this->request);
        if (empty($result['sucesso'])) {
            if (empty($result['encontrado'])) {
                return $this->notFound();
            }
            $existing = $service->encontrar($this->churchId, $id);
            if ($existing === null) {
                return $this->notFound();
            }
            return $this->form($id, array_merge($existing, $input), (array) ($result['erros'] ?? []), 'Editar utilizador', 422);
        }
        Session::set('flash_utilizadores', 'Dados, papel e permissões actualizados. As sessões anteriores foram terminadas.');
        return Response::redirect(url('utilizadores'), 303);
    }

    private function changeStatus(bool $remove): Response
    {
        $guard = $this->guardUsers();
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->targetId();
        $activeValue = filter_var($this->request->post('activo', $this->request->post('ativo', null)), FILTER_VALIDATE_INT);
        $active = $activeValue === false || $activeValue === null ? false : (int) $activeValue === 1;
        $service = new UsuarioService();
        $result = $id === null
            ? ['sucesso' => false, 'erro' => 'Utilizador não encontrado.']
            : ($remove
                ? $service->remover($this->churchId, (int) $this->actor['id'], $id, $this->request)
                : $service->alterarEstado($this->churchId, (int) $this->actor['id'], $id, $active, $this->request));
        Session::set('flash_utilizadores', !empty($result['sucesso'])
            ? ($remove || !$active ? 'A conta foi desactivada; o histórico foi preservado.' : 'A conta foi activada.')
            : (string) ($result['erro'] ?? 'Não foi possível alterar esta conta.'));
        return Response::redirect(url('utilizadores'), 303);
    }

    private function form(?int $id, array $data, array $errors, string $title, int $status = 200): Response
    {
        $data['titulo'] = $title;
        $data['utilizadorId'] = $id;
        $data['erros'] = $errors;
        $data['modulosDisponiveis'] = UsuarioService::modulosDisponiveis();
        $data['roles'] = [
            'pastor' => 'Pastor',
            'tesoureiro' => 'Tesoureiro',
            'secretario' => 'Secretário(a)',
            'lider' => 'Líder',
        ];
        return $this->renderChurchModule('igreja/utilizadores/form', $data, $title, 'utilizadores', $status);
    }

    private function serviceUser(?int $id): ?array
    {
        return $id === null ? null : (new UsuarioService())->encontrar($this->churchId, $id);
    }

    private function input(): array
    {
        return [
            'nome_completo' => $this->request->post('nome_completo', $this->request->post('nome', '')),
            'email' => $this->request->post('email', ''),
            'papel' => $this->request->post('papel', $this->request->post('perfil', '')),
            'modulos' => $this->request->post('modulos', []),
        ];
    }

    private function guardUsers(): ?Response
    {
        return $this->authorizeModule('users', ['admin_igreja']);
    }

    private function routeId(): ?int
    {
        return $this->positiveInt($this->request->route('id', null));
    }

    private function targetId(): ?int
    {
        return $this->positiveInt($this->request->post('id', $this->request->route('id', null)));
    }

    private function positiveInt($value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        return $id !== false && $id !== null && (int) $id > 0 ? (int) $id : null;
    }
}
