<?php
namespace App\Controllers\Auth;

use App\Core\Response;
use App\Services\SetupSuperadminService;
use App\Services\PasswordHasher;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Assistente de utilização única para semear o primeiro Superadmin por FTP. */
final class SetupSuperadminController extends AuthBaseController
{
    public function form(): Response
    {
        $service = new SetupSuperadminService();
        if (!$service->isAvailable()) {
            return Response::html($this->view->render('errors/404'), 404);
        }
        return $this->renderAuth('auth/setup-superadmin', [
            'errorMessage' => '',
            'successMessage' => '',
        ], 'Configurar plataforma', 200);
    }

    public function create(): Response
    {
        $service = new SetupSuperadminService();
        if (!$service->isAvailable()) {
            return Response::html($this->view->render('errors/404'), 404);
        }

        $name = $this->request->post('nome', '');
        $email = $this->request->post('email', '');
        $password = $this->request->post('senha', '');
        $confirmation = $this->request->post('confirmacao', '');
        $token = $this->request->post('chave', '');
        $name = is_string($name) ? $name : '';
        $email = is_string($email) ? $email : '';
        $password = is_string($password) ? $password : '';
        $confirmation = is_string($confirmation) ? $confirmation : '';
        $token = is_string($token) ? $token : '';

        $created = false;
        if (hash_equals($password, $confirmation)) {
            $created = $service->create($name, $email, $password, $token, $this->request);
        }
        if (!$created) {
            return $this->renderAuth('auth/setup-superadmin', [
                'errorMessage' => 'Não foi possível concluir a configuração. Confirma os dados, a chave, uma senha com ' . PasswordHasher::requirementsDescription() . ' e que ainda não existe um Superadmin.',
                'successMessage' => '',
            ], 'Configurar plataforma', 422);
        }

        return $this->renderAuth('auth/setup-superadmin', [
            'errorMessage' => '',
            'successMessage' => 'Superadmin criado. Remove agora SUPERADMIN_BOOTSTRAP_TOKEN do .env e entra com as credenciais escolhidas.',
        ], 'Plataforma configurada');
    }
}
