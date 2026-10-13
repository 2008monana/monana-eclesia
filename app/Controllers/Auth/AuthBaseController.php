<?php
namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Response;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Helpers de apresentação partilhados pelas páginas de autenticação. */
abstract class AuthBaseController extends Controller
{
    protected function renderAuth(string $template, array $data, string $title, int $status = 200): Response
    {
        $content = $this->view->render($template, $data);
        $html = $this->view->render('layouts/auth', [
            'content' => $content,
            'title' => $title,
            'single' => false,
        ]);
        return Response::html($html, $status);
    }
}
