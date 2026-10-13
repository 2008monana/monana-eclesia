<?php
namespace App\Controllers\Igreja;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\ListaService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Listas dominicais tenant-scoped. */
final class ListaController extends BaseIgrejaController
{
    public function index(): Response
    {
        return $this->showList('todos');
    }

    public function mamas(): Response
    {
        return $this->showList('mama');
    }

    public function papas(): Response
    {
        return $this->showList('papa');
    }

    public function jovens(): Response
    {
        return $this->showList('jovem');
    }

    public function criancas(): Response
    {
        return $this->showList('crianca');
    }

    public function pdf(): Response
    {
        $guard = $this->authorizeModule('lists');
        if ($guard instanceof Response) {
            return $guard;
        }
        if (!class_exists('Dompdf\\Dompdf')) {
            return Response::text('A biblioteca Dompdf não está instalada neste alojamento.', 503);
        }
        $category = $this->request->query('categoria', 'todos');
        $data = (new ListaService())->listar(
            $this->churchId,
            $this->request->query('mes', date('n')),
            $this->request->query('ano', date('Y')),
            $category
        );
        $categoryLabels = ['todos' => 'Todos', 'mama' => 'Mamãs', 'papa' => 'Papás', 'jovem' => 'Jovens', 'crianca' => 'Crianças'];
        $header = '<th>Membro</th>';
        foreach ($data['domingos'] as $sunday) {
            $header .= '<th>' . e(date('d/m', strtotime((string) $sunday))) . '</th>';
        }
        $header .= '<th>Quitados</th><th>Estado</th>';
        $rows = '';
        foreach ($data['membros'] as $member) {
            $rows .= '<tr><td>' . e($member['nome_completo'] ?? '') . '</td>';
            $payments = is_array($member['pagamentos_mes'] ?? null) ? $member['pagamentos_mes'] : [];
            foreach ($data['domingos'] as $sunday) {
                $amount = (float) ($payments[$sunday] ?? 0);
                $rows .= '<td>' . ($amount > 0 ? e(number_format($amount, 2, ',', '.') . ' Kz') : '—') . '</td>';
            }
            $settled = ($member['estado_cota'] ?? '') === 'quitado';
            $rows .= '<td>' . e((int) ($member['periodos_quitados'] ?? 0)) . ' / ' . e((int) ($member['periodos_devidos'] ?? 0))
                . '</td><td>' . ($settled ? 'Quitado' : (($member['estado_cota'] ?? '') === 'pendente' ? 'Em falta' : 'Não iniciado')) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="' . (count($data['domingos']) + 3) . '">Não há membros para esta selecção.</td></tr>';
        }
        $content = '<p>Categoria: ' . e($categoryLabels[$data['categoria']] ?? 'Todos') . ' · Membros: '
            . e(count($data['membros'])) . ' · Períodos devidos: ' . e($data['periodosDevidos']) . '</p>'
            . '<table><thead><tr>' . $header . '</tr></thead><tbody>' . $rows . '</tbody></table>';
        $user = Session::get('_auth_user', []);
        $churchName = is_array($user) ? (string) ($user['igreja_nome'] ?? 'Igreja') : 'Igreja';
        $monthNames = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
        $html = (new View())->render('pdf/layout', [
            'title' => 'Lista de pagamentos',
            'subtitle' => ($monthNames[(int) $data['mes']] ?? '') . ' de ' . $data['ano'],
            'churchName' => $churchName,
            'churchShortName' => $churchName,
            'content' => $content,
        ]);
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="listas-' . (int) $data['ano'] . '-' . sprintf('%02d', (int) $data['mes']) . '.pdf"',
        ]);
    }

    private function showList(string $fixedCategory): Response
    {
        $guard = $this->authorizeModule('lists');
        if ($guard instanceof Response) {
            return $guard;
        }
        $category = $fixedCategory === 'todos'
            ? $this->request->query('categoria', 'todos')
            : $fixedCategory;
        $data = (new ListaService())->listar(
            $this->churchId,
            $this->request->query('mes', date('n')),
            $this->request->query('ano', date('Y')),
            $category
        );
        return $this->renderChurchModule('igreja/listas/index', $data, 'Listas', 'listas');
    }
}
