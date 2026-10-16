<?php
namespace App\Core;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Base para controladores HTTP: recebe Request e devolve Response. */
abstract class Controller
{
    /** @var Request */
    protected $request;

    /** @var Response */
    protected $response;

    /** @var View */
    protected $view;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
        $this->view = new View();
    }

    /** Prepara uma resposta HTML a partir de uma vista PHP. */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->view->render($template, $data), $status);
    }

    protected function json(array $payload, int $status = 200): Response
    {
        return Response::json($payload, $status);
    }

    protected function redirect(string $location, int $status = 302): Response
    {
        return Response::redirect($location, $status);
    }

    protected function notFound(): Response
    {
        return Response::html($this->view->render('errors/404'), 404);
    }
}
