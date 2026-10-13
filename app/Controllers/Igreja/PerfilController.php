<?php
namespace App\Controllers\Igreja;

use App\Core\Response;
use App\Core\Session;
use App\Services\PerfilService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Perfil próprio do utilizador; email e papel não são alterados nesta página. */
final class PerfilController extends BaseIgrejaController
{
    public function index(): Response
    {
        $guard = $this->authorizeModule('profile');
        if ($guard instanceof Response) {
            return $guard;
        }
        $user = (new PerfilService())->encontrar($this->churchId, (int) $this->actor['id']);
        if ($user === null) {
            return Response::text('O perfil não pertence à igreja autenticada.', 403);
        }
        $message = (string) Session::get('flash_perfil', '');
        Session::remove('flash_perfil');
        return $this->renderChurchModule('igreja/perfil/index', [
            'utilizador' => $user,
            'mensagem' => $message,
            'erro' => '',
        ], 'Meu perfil', 'perfil');
    }

    public function actualizar(): Response
    {
        $guard = $this->authorizeModule('profile');
        if ($guard instanceof Response) {
            return $guard;
        }
        $service = new PerfilService();
        $result = $service->actualizarNome($this->churchId, (int) $this->actor['id'], $this->request->post('nome_completo', ''), $this->request);
        if (empty($result['sucesso'])) {
            $user = $service->encontrar($this->churchId, (int) $this->actor['id']);
            return $this->renderChurchModule('igreja/perfil/index', [
                'utilizador' => $user,
                'mensagem' => '',
                'erro' => (string) ($result['erro'] ?? 'Não foi possível guardar o perfil.'),
                'nomeEntrada' => (string) ($result['nome'] ?? ''),
            ], 'Meu perfil', 'perfil', 422);
        }
        $user = Session::get('_auth_user', []);
        if (is_array($user)) {
            $user['nome_completo'] = $result['nome'];
            Session::set('_auth_user', $user);
            Session::set('user_nome', $result['nome']);
        }
        Session::set('flash_perfil', 'O nome do perfil foi actualizado.');
        return Response::redirect(url('perfil'), 303);
    }
}
