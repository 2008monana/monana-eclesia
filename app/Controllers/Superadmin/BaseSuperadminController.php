<?php
namespace App\Controllers\Superadmin;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\PapelMiddleware;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Verificações e layout comuns às rotas exclusivas do Superadmin. */
abstract class BaseSuperadminController extends Controller
{
    protected function authorize(): ?Response
    {
        $auth = (new AuthMiddleware())->handle($this->request);
        if ($auth instanceof Response) {
            return $auth;
        }
        $role = (new PapelMiddleware())->handle(['superadmin']);
        if ($role instanceof Response) {
            return $role;
        }
        if (Session::get('auth_igreja_id') !== null) {
            return Response::text('A conta da plataforma não pode ter um tenant activo.', 403);
        }
        return null;
    }

    protected function renderPlatform(string $template, array $data, string $title, string $active, int $status = 200): Response
    {
        $user = Session::get('_auth_user', []);
        $actorName = is_array($user) ? (string) ($user['nome_completo'] ?? 'Superadmin') : 'Superadmin';
        $content = $this->view->render($template, $data);
        $html = $this->view->render('layouts/superadmin', [
            'content' => $content,
            'title' => $title,
            'active' => $active,
            'actorName' => $actorName,
        ]);
        return Response::html($html, $status);
    }

    protected function actor(): array
    {
        $user = Session::get('_auth_user', []);
        return is_array($user) ? $user : [];
    }
}
