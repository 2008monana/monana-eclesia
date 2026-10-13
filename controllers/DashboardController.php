<?php
// controllers/DashboardController.php
// Controlador do modulo "dashboard" - arquitectura MVC.
// Logica original dos antigos modules/dashboard/*.php preservada,
// agora separada em Model (dados) / Controller (fluxo) / View (HTML).

require_once BASE_PATH . '/models/DashboardModel.php';
require_once BASE_PATH . '/models/AuditModel.php';
class DashboardController extends Controller
{
    protected string $modulo = 'dashboard';

    /** Antes: modules/dashboard/index.php */
    public function index(): void
    {
    }

}
