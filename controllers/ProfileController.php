<?php
// controllers/ProfileController.php
// Controlador do modulo "profile" - arquitectura MVC.
// Logica original dos antigos modules/profile/*.php preservada,
// agora separada em Model (dados) / Controller (fluxo) / View (HTML).

require_once BASE_PATH . '/models/ProfileModel.php';
require_once BASE_PATH . '/models/AuditModel.php';
class ProfileController extends Controller
{
    protected string $modulo = 'profile';

    /** Antes: modules/profile/index.php */
    public function index(): void
    {
    }

}
