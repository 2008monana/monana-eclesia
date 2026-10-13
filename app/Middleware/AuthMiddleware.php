<?php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SessionRepository;
use App\Repositories\UsuarioRepository;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Revalida utilizador, igreja e versão da sessão nas rotas protegidas. */
final class AuthMiddleware
{
    /** @var UsuarioRepository */
    private $users;

    /** @var SessionRepository */
    private $sessions;

    public function __construct(?UsuarioRepository $users = null, ?SessionRepository $sessions = null)
    {
        $this->users = $users ?: new UsuarioRepository();
        $this->sessions = $sessions ?: new SessionRepository();
    }

    /** Devolve null se autenticado; caso contrário, resposta de redireccionamento/erro. */
    public function handle(Request $request): ?Response
    {
        Session::start();
        $userId = (int) Session::get('auth_user_id', 0);
        $sessionHash = (string) Session::get('auth_session_hash', '');
        $sessionVersion = (int) Session::get('auth_version', 0);
        $currentHash = hash('sha256', session_id());

        if ($userId < 1 || $sessionHash === '' || !hash_equals($sessionHash, $currentHash)) {
            if ($userId > 0 || $sessionHash !== '') {
                Session::destroy();
            }
            return Response::redirect(url('login'));
        }

        $user = $this->users->findForAuthentication($userId);
        if ($user === null
            || (int) ($user['ativo'] ?? 0) !== 1
            || empty($user['email_verificado_em'])
            || (int) ($user['versao_sessao'] ?? 0) !== $sessionVersion
            || !$this->sessions->isActive($currentHash, $userId, $sessionVersion)
        ) {
            Session::destroy();
            return Response::redirect(url('login'));
        }

        $role = (string) ($user['papel'] ?? '');
        $churchId = $user['igreja_id'] !== null ? (int) $user['igreja_id'] : null;
        if ($role === 'superadmin') {
            if ($churchId !== null) {
                Session::destroy();
                return Response::text('A conta da plataforma tem uma associação de igreja inválida.', 403);
            }
        } elseif ($churchId === null || (string) ($user['igreja_estado'] ?? '') !== 'activa') {
            Session::destroy();
            return Response::text('A conta da igreja não está disponível. Contacta o Superadmin.', 403);
        }

        unset($user['senha_hash']);
        Session::set('_auth_user', $user);
        Session::set('auth_igreja_id', $churchId);
        Session::set('auth_papel', $role);
        Session::set('user_id', (int) $user['id']);
        Session::set('user_nome', (string) $user['nome_completo']);
        Session::set('user_email', (string) $user['email']);
        Session::set('user_perfil', $role === 'admin_igreja' ? 'admin' : $role);
        Session::set('user_modulos', $user['modulos_permitidos'] ?? '');

        $path = $request->path();
        $passwordChangePaths = ['/trocar-senha', '/sair', '/sessoes/terminar-todas'];
        if ((int) ($user['deve_trocar_senha'] ?? 0) === 1
            && !in_array($path, $passwordChangePaths, true)
        ) {
            return Response::redirect(url('trocar-senha'));
        }

        return null;
    }
}
