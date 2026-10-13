<?php
namespace App\Controllers\Superadmin;

use App\Core\Response;
use App\Services\SuperadminService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

final class DashboardController extends BaseSuperadminController
{
    public function index(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        $data = (new SuperadminService())->dashboardData();
        return $this->renderPlatform('superadmin/dashboard', $data, 'Plataforma', 'dashboard');
    }
}
