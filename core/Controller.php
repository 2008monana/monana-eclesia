<?php
// core/Controller.php
// ============================================
// CONTROLADOR BASE (Controller) da arquitectura MVC
// ============================================

require_once BASE_PATH . '/core/View.php';

abstract class Controller
{
    /** Módulo pelo qual este controlador responde (chave de permissão). */
    protected string $modulo = '';

    /** Exige sessão iniciada e acesso ao módulo; redireciona caso contrário. */
    protected function requireAuth(?string $modulo = null): void
    {
        $modulo = $modulo ?? $this->modulo;
        if ($modulo !== '') {
            requireModuleAccess($modulo);
        } else {
            checkLogin();
        }
    }

    /** Renderiza uma vista completa (com <html> próprio), ex.: login, PDFs. */
    protected function render(string $view, array $data = []): void
    {
        View::render($view, $data);
    }

    /** Captura o HTML de uma vista (para gerar PDF via Dompdf). */
    protected function capture(string $view, array $data = []): string
    {
        return View::capture($view, $data);
    }

    /** Resposta JSON simples (usado pelos endpoints AJAX). */
    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }

    /** Redireciona para uma rota/página do sistema. */
    protected function redirect(string $path): void
    {
        redirect($path);
    }
}
