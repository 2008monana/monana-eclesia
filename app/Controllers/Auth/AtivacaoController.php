<?php
namespace App\Controllers\Auth;

use App\Core\Response;
use App\Services\AuthService;
use App\Services\PasswordHasher;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Activação por link de uso único e definição da senha definitiva. */
final class AtivacaoController extends AuthBaseController
{
    public function form(): Response
    {
        $token = $this->request->query('token', '');
        $token = is_string($token) ? $token : '';
        $details = (new AuthService())->activationDetails($token);
        return $this->renderAuth('auth/activar-conta', [
            'token' => $token,
            'validToken' => is_array($details),
            'churchName' => is_array($details) ? (string) $details['igreja_nome'] : 'a tua igreja',
            'successMessage' => '',
            'errorMessage' => is_array($details) ? '' : 'Este link é inválido, expirou ou já foi utilizado. Contacta o Superadmin para receber outro.',
        ], 'Activar conta', is_array($details) ? 200 : 400);
    }

    public function activate(): Response
    {
        $token = $this->request->post('token', '');
        $password = $this->request->post('senha', '');
        $confirmation = $this->request->post('confirmacao', '');
        $token = is_string($token) ? $token : '';
        $password = is_string($password) ? $password : '';
        $confirmation = is_string($confirmation) ? $confirmation : '';
        $service = new AuthService();
        $details = $service->activationDetails($token);

        if (!hash_equals($password, $confirmation)) {
            return $this->renderAuth('auth/activar-conta', [
                'token' => $token,
                'validToken' => is_array($details),
                'churchName' => is_array($details) ? (string) $details['igreja_nome'] : 'a tua igreja',
                'successMessage' => '',
                'errorMessage' => 'As senhas não coincidem.',
            ], 'Activar conta', 422);
        }
        if (!PasswordHasher::isAcceptable($password)) {
            return $this->renderAuth('auth/activar-conta', [
                'token' => $token,
                'validToken' => is_array($details),
                'churchName' => is_array($details) ? (string) $details['igreja_nome'] : 'a tua igreja',
                'successMessage' => '',
                'errorMessage' => 'A senha deve ter ' . PasswordHasher::requirementsDescription() . '.',
            ], 'Activar conta', 422);
        }
        if (!$service->activateAccount($token, $password, $this->request)) {
            return $this->renderAuth('auth/activar-conta', [
                'token' => '',
                'validToken' => false,
                'churchName' => 'a tua igreja',
                'successMessage' => '',
                'errorMessage' => 'Este link é inválido, expirou ou já foi utilizado. Contacta o Superadmin para receber outro.',
            ], 'Activar conta', 400);
        }

        return $this->renderAuth('auth/activar-conta', [
            'token' => '',
            'validToken' => false,
            'churchName' => 'a tua igreja',
            'successMessage' => 'Conta activada. A tua senha foi definida; já podes entrar no sistema da igreja.',
            'errorMessage' => '',
        ], 'Conta activada');
    }
}
