<?php
namespace App\Services;

use App\Core\Config;
use App\Repositories\DashboardRepository;
use DateTimeImmutable;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Resumo e atalhos do dashboard sem recorrer às rotas procedurais antigas. */
final class DashboardService
{
    private $repository;

    public function __construct(?DashboardRepository $repository = null)
    {
        $this->repository = $repository ?: new DashboardRepository();
    }

    public function resumo(int $churchId, array $allowedModules): array
    {
        $today = new DateTimeImmutable('today');
        $start = $today->modify('first day of this month')->format('Y-m-d');
        $end = $today->modify('first day of next month')->format('Y-m-d');
        $month = (new DateTimeImmutable('first day of this month'))->format('m/Y');
        $year = (int) $today->format('Y');
        $metrics = [];
        $actions = [];
        $available = [];
        $features = [
            'members' => ['flag' => 'MEMBERS_MVC_ENABLED', 'label' => 'Membros'],
            'registry' => ['flag' => 'COTAS_MVC_ENABLED', 'label' => 'Cotas'],
            'lists' => ['flag' => 'LISTS_MVC_ENABLED', 'label' => 'Listas'],
            'expenses' => ['flag' => 'EXPENSES_MVC_ENABLED', 'label' => 'Saídas'],
            'year_end' => ['flag' => 'YEAR_END_MVC_ENABLED', 'label' => 'Fim de ano'],
            'reports' => ['flag' => 'REPORTS_MVC_ENABLED', 'label' => 'Relatórios'],
            'users' => ['flag' => 'USERS_MVC_ENABLED', 'label' => 'Utilizadores'],
            'audit' => ['flag' => 'AUDIT_MVC_ENABLED', 'label' => 'Auditoria'],
            'backups' => ['flag' => 'BACKUPS_MVC_ENABLED', 'label' => 'Backups'],
            'profile' => ['flag' => 'PROFILE_MVC_ENABLED', 'label' => 'Perfil'],
            'settings' => ['flag' => 'SETTINGS_MVC_ENABLED', 'label' => 'Configurações'],
        ];
        foreach ($features as $key => $feature) {
            $enabled = in_array($key, $allowedModules, true) && Config::bool($feature['flag'], false);
            if ($enabled) {
                $available[] = $key;
            }
        }
        if (in_array('members', $available, true)) {
            $metrics[] = ['label' => 'Membros activos', 'valor' => (string) $this->repository->membrosActivos($churchId), 'icone' => 'fa-users'];
            $actions[] = ['label' => 'Gerir membros', 'url' => 'membros', 'icone' => 'fa-users'];
        }
        if (in_array('registry', $available, true)) {
            $quota = $this->repository->cotasDoMes($churchId, $start, $end);
            $metrics[] = ['label' => 'Cotas recebidas em ' . $month, 'valor' => number_format($quota['total'], 2, ',', '.') . ' Kz', 'icone' => 'fa-book-open'];
            $actions[] = ['label' => 'Registar cotas', 'url' => 'cotas', 'icone' => 'fa-book-open'];
        }
        if (in_array('year_end', $available, true)) {
            $amount = $this->repository->contribuicoesDoAno($churchId, $year);
            $metrics[] = ['label' => 'Fim de ano · ' . $year, 'valor' => number_format($amount, 2, ',', '.') . ' Kz', 'icone' => 'fa-gift'];
            $actions[] = ['label' => 'Contribuições de fim de ano', 'url' => 'contribuicoes-fim-de-ano', 'icone' => 'fa-gift'];
        }
        if (in_array('expenses', $available, true)) {
            $amount = $this->repository->despesasDoMes($churchId, $start, $end);
            $metrics[] = ['label' => 'Saídas em ' . $month, 'valor' => number_format($amount, 2, ',', '.') . ' Kz', 'icone' => 'fa-arrow-trend-down'];
            $actions[] = ['label' => 'Consultar saídas', 'url' => 'saidas', 'icone' => 'fa-arrow-trend-down'];
        }
        if (in_array('lists', $available, true)) {
            $actions[] = ['label' => 'Listas de membros', 'url' => 'listas', 'icone' => 'fa-list-check'];
        }
        if (in_array('reports', $available, true)) {
            $actions[] = ['label' => 'Abrir relatórios', 'url' => 'relatorios', 'icone' => 'fa-chart-pie'];
        }
        if (in_array('users', $available, true)) {
            $actions[] = ['label' => 'Gerir utilizadores', 'url' => 'utilizadores', 'icone' => 'fa-users-gear'];
        }
        if (in_array('audit', $available, true)) {
            $actions[] = ['label' => 'Ver auditoria', 'url' => 'auditoria', 'icone' => 'fa-shield-halved'];
        }
        if (in_array('backups', $available, true)) {
            $actions[] = ['label' => 'Cópia de segurança', 'url' => 'copias-de-seguranca', 'icone' => 'fa-box-archive'];
        }
        if (in_array('profile', $available, true)) {
            $actions[] = ['label' => 'Meu perfil', 'url' => 'perfil', 'icone' => 'fa-user-circle'];
        }
        if (in_array('settings', $available, true)) {
            $actions[] = ['label' => 'Configurações', 'url' => 'configuracoes', 'icone' => 'fa-sliders'];
        }
        return [
            'metricas' => $metrics,
            'atalhos' => $actions,
            'activados' => $available,
            'activadosLabels' => array_map(static function ($key) use ($features) { return $features[$key]['label']; }, $available),
            'actividade' => in_array('audit', $available, true) ? $this->repository->actividadeRecente($churchId) : [],
            'mesReferencia' => $month,
        ];
    }
}
