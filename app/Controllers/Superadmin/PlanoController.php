<?php
namespace App\Controllers\Superadmin;

use App\Core\ErrorHandler;
use App\Core\Response;
use App\Services\PlanoService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** CRUD de planos (apagar apenas quando nenhum tenant os utiliza). */
final class PlanoController extends BaseSuperadminController
{
    public function index(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        $notice = '';
        switch ((string) $this->request->query('resultado', '')) {
            case 'criado': $notice = 'Plano guardado.'; break;
            case 'actualizado': $notice = 'Plano actualizado.'; break;
            case 'apagado': $notice = 'Plano apagado.'; break;
        }
        return $this->renderPlatform('superadmin/planos-index', [
            'plans' => (new PlanoService())->all(),
            'notice' => $notice,
        ], 'Planos', 'planos');
    }

    public function createForm(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        return $this->renderPlatform('superadmin/plano-form', [
            'plan' => ['limite_membros' => 0, 'limite_utilizadores' => 0, 'preco_kz' => '0.00', 'ativo' => 0],
            'isEdit' => false,
            'errorMessage' => '',
        ], 'Novo plano', 'planos');
    }

    public function create(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        $actor = $this->actor();
        try {
            (new PlanoService())->create(
                $this->request->all(),
                $this->request,
                (int) ($actor['id'] ?? 0),
                (string) ($actor['nome_completo'] ?? 'Superadmin')
            );
            return Response::redirect(url('superadmin/planos?resultado=criado'));
        } catch (\Throwable $exception) {
            ErrorHandler::log('SUPERADMIN', 'Falha ao criar plano.', ['erro' => substr($exception->getMessage(), 0, 400)]);
            return $this->renderPlatform('superadmin/plano-form', [
                'plan' => $this->request->all(),
                'isEdit' => false,
                'errorMessage' => $exception instanceof \InvalidArgumentException
                    ? $exception->getMessage()
                    : 'Não foi possível guardar o plano. Confirma se o nome já está a ser utilizado.',
            ], 'Novo plano', 'planos', 422);
        }
    }

    public function editForm(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->routeId();
        $plan = $id > 0 ? (new PlanoService())->find($id) : null;
        if ($plan === null) {
            return $this->notFound();
        }
        return $this->renderPlatform('superadmin/plano-form', [
            'plan' => $plan,
            'isEdit' => true,
            'errorMessage' => '',
        ], 'Editar plano', 'planos');
    }

    public function update(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->routeId();
        $service = new PlanoService();
        $before = $id > 0 ? $service->find($id) : null;
        if ($before === null) {
            return $this->notFound();
        }
        $actor = $this->actor();
        try {
            $service->update(
                $id,
                $this->request->all(),
                $this->request,
                (int) ($actor['id'] ?? 0),
                (string) ($actor['nome_completo'] ?? 'Superadmin')
            );
            return Response::redirect(url('superadmin/planos?resultado=actualizado'));
        } catch (\Throwable $exception) {
            ErrorHandler::log('SUPERADMIN', 'Falha ao actualizar plano.', ['erro' => substr($exception->getMessage(), 0, 400)]);
            return $this->renderPlatform('superadmin/plano-form', [
                'plan' => array_merge($before, $this->request->all()),
                'isEdit' => true,
                'errorMessage' => $exception instanceof \InvalidArgumentException
                    ? $exception->getMessage()
                    : 'Não foi possível actualizar o plano. Confirma se o nome já está a ser utilizado.',
            ], 'Editar plano', 'planos', 422);
        }
    }

    public function delete(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->routeId();
        $actor = $this->actor();
        try {
            if (!(new PlanoService())->delete(
                $id,
                $this->request,
                (int) ($actor['id'] ?? 0),
                (string) ($actor['nome_completo'] ?? 'Superadmin')
            )) {
                return $this->notFound();
            }
            return Response::redirect(url('superadmin/planos?resultado=apagado'));
        } catch (\Throwable $exception) {
            ErrorHandler::log('SUPERADMIN', 'Falha ao apagar plano.', ['erro' => substr($exception->getMessage(), 0, 400)]);
            return Response::redirect(url('superadmin/planos'));
        }
    }

    private function routeId(): int
    {
        $value = (string) $this->request->route('id', '');
        return ctype_digit($value) ? (int) $value : 0;
    }
}
