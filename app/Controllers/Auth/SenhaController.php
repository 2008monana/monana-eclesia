<?php
namespace App\Controllers\Auth;

use App\Core\Response;
use App\Core\Session;
use App\Services\PasswordHasher;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Recuperação, redefinição e troca obrigatória de senha. */
final class SenhaController extends AuthBaseController
{
    public function forgotForm(): Response
    {
        return $this->renderAuth('auth/recuperar-senha', [
            'successMessage' => '',
            'errorMessage' => '',
        ], 'Recuperar senha');
    }

    public function requestReset(): Response
    {
        $email = $this->request->post('email', '');
        $email = is_string($email) ? substr(trim($email), 0, 191) : '';
        (new AuthService())->requestPasswordReset($email, $this->request->ip(), $this->request);

        // O texto é exactamente igual exista ou não conta associada ao endereço.
        return $this->renderAuth('auth/recuperar-senha', [
            'successMessage' => 'Se existir uma conta associada a esse email, enviaremos instruções para redefinir a senha.',
            'errorMessage' => '',
        ], 'Recuperar senha');
    }

    public function resetForm(): Response
    {
        $token = $this->request->query('token', '');
        $token = is_string($token) ? $token : '';
        $valid = (new AuthService())->isResetTokenValid($token);
        return $this->renderAuth('auth/resetar-senha', [
            'token' => $token,
            'validToken' => $valid,
            'successMessage' => '',
            'errorMessage' => $valid ? '' : 'Este link é inválido, expirou ou já foi utilizado. Pede um novo link.',
        ], 'Redefinir senha', $valid ? 200 : 400);
    }

    public function submitReset(): Response
    {
        $token = $this->request->post('token', '');
        $password = $this->request->post('senha', '');
        $confirmation = $this->request->post('confirmacao', '');
        $token = is_string($token) ? $token : '';
        $password = is_string($password) ? $password : '';
        $confirmation = is_string($confirmation) ? $confirmation : '';

        if (!hash_equals($password, $confirmation)) {
            return $this->renderAuth('auth/resetar-senha', [
                'token' => $token,
                'validToken' => (new AuthService())->isResetTokenValid($token),
                'successMessage' => '',
                'errorMessage' => 'As senhas não coincidem.',
            ], 'Redefinir senha', 422);
        }

        if (!PasswordHasher::isAcceptable($password)) {
            return $this->renderAuth('auth/resetar-senha', [
                'token' => $token,
                'validToken' => (new AuthService())->isResetTokenValid($token),
                'successMessage' => '',
                'errorMessage' => 'A nova senha deve ter ' . PasswordHasher::requirementsDescription() . '.',
            ], 'Redefinir senha', 422);
        }

        if (!(new AuthService())->resetPassword($token, $password, $this->request)) {
            return $this->renderAuth('auth/resetar-senha', [
                'token' => '',
                'validToken' => false,
                'successMessage' => '',
                'errorMessage' => 'Este link é inválido, expirou ou já foi utilizado. Pede um novo link.',
            ], 'Redefinir senha', 400);
        }
        Session::destroy();
        return $this->renderAuth('auth/resetar-senha', [
            'token' => '',
            'validToken' => false,
            'successMessage' => 'A senha foi redefinida. Já podes entrar com a nova senha.',
            'errorMessage' => '',
        ], 'Senha redefinida');
    }

    public function changeForm(): Response
    {
        $guard = (new AuthMiddleware())->handle($this->request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $user = Session::get('_auth_user', []);
        return $this->renderAuth('auth/trocar-senha', [
            'mustChange' => is_array($user) && (int) ($user['deve_trocar_senha'] ?? 0) === 1,
            'errorMessage' => '',
        ], 'Alterar senha');
    }

    public function change(): Response
    {
        $guard = (new AuthMiddleware())->handle($this->request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $user = Session::get('_auth_user', []);
        if (!is_array($user) || empty($user['id'])) {
            return Response::redirect(url('login'));
        }
        $mustChange = (int) ($user['deve_trocar_senha'] ?? 0) === 1;
        $current = $this->request->post('senha_actual', '');
        $password = $this->request->post('senha', '');
        $confirmation = $this->request->post('confirmacao', '');
        $current = is_string($current) ? $current : '';
        $password = is_string($password) ? $password : '';
        $confirmation = is_string($confirmation) ? $confirmation : '';

        $error = '';
        if (!hash_equals($password, $confirmation)) {
            $error = 'As senhas não coincidem.';
        } elseif (!PasswordHasher::isAcceptable($password)) {
            $error = 'A nova senha deve ter ' . PasswordHasher::requirementsDescription() . '.';
        } elseif (!$mustChange && $current === '') {
            $error = 'Indica a senha actual.';
        }

        if ($error === '' && !(new AuthService())->changePassword((int) $user['id'], $current, $password, $this->request)) {
            $error = 'Não foi possível alterar a senha. Confirma a senha actual e tenta novamente.';
        }
        if ($error !== '') {
            return $this->renderAuth('auth/trocar-senha', [
                'mustChange' => $mustChange,
                'errorMessage' => $error,
            ], 'Alterar senha', 422);
        }
        return Response::redirect(url('login?senha=alterada'));
    }

    public function terminateAll(): Response
    {
        $guard = (new AuthMiddleware())->handle($this->request);
        if ($guard instanceof Response) {
            return $guard;
        }
        $user = Session::get('_auth_user', []);
        if (is_array($user) && !empty($user['id'])) {
            (new AuthService())->terminateAllSessions((int) $user['id'], $this->request);
        }
        return Response::redirect(url('login'));
    }
}
