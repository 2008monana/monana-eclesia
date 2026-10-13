<?php
namespace App\Controllers\Auth;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Login e logout por rotas novas do front controller. */
final class LoginController extends AuthBaseController
{
    /** GET /login */
    public function index(): Response
    {
        Session::start();
        if ((int) Session::get('auth_user_id', 0) > 0) {
            $guard = (new AuthMiddleware())->handle($this->request);
            if ($guard instanceof Response) {
                return $guard;
            }
            $user = Session::get('_auth_user', []);
            $target = is_array($user) && ($user['papel'] ?? '') === 'superadmin' ? 'superadmin' : 'painel';
            return Response::redirect(url($target));
        }

        $successMessage = $this->request->query('senha') === 'alterada'
            ? 'A senha foi alterada. Entra novamente com a tua nova senha.'
            : '';
        return $this->renderAuth('auth/login', [
            'errorMessage' => '',
            'successMessage' => $successMessage,
            'email' => '',
        ], 'Entrar');
    }

    /** POST /login — a mesma mensagem é usada para email, senha e estado inválidos. */
    public function authenticate(): Response
    {
        $email = $this->request->post('email', '');
        $password = $this->request->post('senha', '');
        $email = is_string($email) ? trim($email) : '';
        $password = is_string($password) ? $password : '';

        // Mesmo entradas malformadas contam para o limite por IP/email; o serviço
        // não faz consulta de conta quando o formato do email é inválido.
        $service = new AuthService();
        $result = $service->login(
            $email,
            $password,
            $this->request->ip(),
            $this->request->userAgent(),
            $this->request
        );

        if (!empty($result['success'])) {
            return Response::redirect((string) $result['redirect']);
        }

        $message = 'Email ou senha inválidos, ou a conta ainda não está disponível.';
        $accept = strtolower((string) $this->request->header('accept', ''));
        if (strpos($accept, 'application/json') !== false
            || strtolower((string) $this->request->header('x-requested-with', '')) === 'xmlhttprequest'
        ) {
            return Response::json(['success' => false, 'message' => $message], 401);
        }
        return $this->renderAuth('auth/login', [
            'errorMessage' => $message,
            'successMessage' => '',
            'email' => $email,
        ], 'Entrar', 401);
    }

    /** POST /sair */
    public function logout(): Response
    {
        (new AuthService())->logout($this->request);
        return Response::redirect(url('login'));
    }
}
