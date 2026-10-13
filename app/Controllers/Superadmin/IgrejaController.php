<?php
namespace App\Controllers\Superadmin;

use App\Core\ErrorHandler;
use App\Core\Response;
use App\Services\ProvisionamentoIgrejaService;
use App\Services\SuperadminService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** CRUD de igrejas e reenvio de convites do painel da plataforma. */
final class IgrejaController extends BaseSuperadminController
{
    public function index(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        $notice = '';
        switch ((string) $this->request->query('resultado', '')) {
            case 'criada_enviada':
                $notice = 'Igreja criada e email de activação entregue pelo transporte configurado.';
                break;
            case 'criada_fila':
                $notice = 'Igreja criada. O email ficou na fila para reenvio após configurar SMTP.';
                break;
            case 'actualizada':
                $notice = 'Dados da igreja actualizados.';
                break;
            case 'estado':
                $notice = 'Estado da igreja actualizado.';
                break;
            case 'email_enviado':
                $notice = 'Email de activação reenviado.';
                break;
            case 'email_falhou':
                $notice = 'O reenvio não foi concluído. Consulta o estado e erro na fila de emails.';
                break;
        }
        return $this->renderPlatform('superadmin/igrejas-index', [
            'churches' => (new SuperadminService())->churches(),
            'notice' => $notice,
        ], 'Igrejas', 'igrejas');
    }

    public function createForm(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        return $this->renderPlatform('superadmin/igreja-form-criar', [
            'plans' => (new SuperadminService())->plans(true),
            'form' => [],
            'errorMessage' => '',
        ], 'Criar igreja', 'igrejas');
    }

    public function create(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        $actor = $this->actor();
        try {
            $result = (new ProvisionamentoIgrejaService())->criar(
                $this->request->all(),
                $this->request,
                (int) ($actor['id'] ?? 0),
                (string) ($actor['nome_completo'] ?? 'Superadmin')
            );
            $destination = !empty($result['email_enviado']) ? 'criada_enviada' : 'criada_fila';
            return Response::redirect(url('superadmin/igrejas?resultado=' . $destination));
        } catch (\Throwable $exception) {
            ErrorHandler::log('SUPERADMIN', 'Falha ao provisionar uma igreja.', [
                'erro' => substr($exception->getMessage(), 0, 500),
            ]);
            $message = $exception instanceof \InvalidArgumentException || $exception instanceof \DomainException
                ? $exception->getMessage()
                : 'Não foi possível criar a igreja. Confirma a base de dados e a configuração de cifragem do email.';
            return $this->renderPlatform('superadmin/igreja-form-criar', [
                'plans' => (new SuperadminService())->plans(true),
                'form' => $this->request->all(),
                'errorMessage' => $message,
            ], 'Criar igreja', 'igrejas', 422);
        }
    }

    public function editForm(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->routeId();
        if ($id < 1) {
            return $this->notFound();
        }
        $service = new SuperadminService();
        $church = $service->church($id);
        if ($church === null) {
            return $this->notFound();
        }
        $notice = '';
        if ($this->request->query('resultado') === 'actualizada') {
            $notice = 'Dados da igreja actualizados.';
        } elseif ($this->request->query('resultado') === 'estado') {
            $notice = 'Estado da igreja actualizado.';
        } elseif ($this->request->query('resultado') === 'email_enviado') {
            $notice = 'Email de activação reenviado.';
        } elseif ($this->request->query('resultado') === 'email_falhou') {
            $notice = 'O reenvio falhou; confirma a configuração SMTP ou o estado da conta.';
        }
        return $this->renderPlatform('superadmin/igreja-form-editar', [
            'church' => $church,
            'plans' => $service->plans(false),
            'emails' => $service->emailsForChurch($id),
            'notice' => $notice,
            'errorMessage' => '',
        ], 'Gerir igreja', 'igrejas');
    }

    public function update(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->routeId();
        $service = new SuperadminService();
        $church = $id > 0 ? $service->church($id) : null;
        if ($church === null) {
            return $this->notFound();
        }
        $actor = $this->actor();
        try {
            $service->updateChurch(
                $id,
                $this->request->all(),
                $this->request,
                (int) ($actor['id'] ?? 0),
                (string) ($actor['nome_completo'] ?? 'Superadmin')
            );
            return Response::redirect(url('superadmin/igrejas/' . $id . '/editar?resultado=actualizada'));
        } catch (\Throwable $exception) {
            ErrorHandler::log('SUPERADMIN', 'Falha ao actualizar uma igreja.', ['erro' => substr($exception->getMessage(), 0, 400)]);
            return $this->renderPlatform('superadmin/igreja-form-editar', [
                'church' => array_merge($church, $this->request->all()),
                'plans' => $service->plans(false),
                'emails' => $service->emailsForChurch($id),
                'notice' => '',
                'errorMessage' => $exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'Não foi possível actualizar os dados da igreja.',
            ], 'Gerir igreja', 'igrejas', 422);
        }
    }

    public function setStatus(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        $id = $this->routeId();
        $state = $this->request->post('estado', '');
        $reason = $this->request->post('motivo', '');
        $state = is_string($state) ? $state : '';
        $reason = is_string($reason) ? $reason : '';
        $actor = $this->actor();
        try {
            $changed = (new SuperadminService())->setChurchStatus(
                $id,
                $state,
                $reason,
                $this->request,
                (int) ($actor['id'] ?? 0),
                (string) ($actor['nome_completo'] ?? 'Superadmin')
            );
            if (!$changed) {
                return $this->notFound();
            }
            return Response::redirect(url('superadmin/igrejas/' . $id . '/editar?resultado=estado'));
        } catch (\Throwable $exception) {
            ErrorHandler::log('SUPERADMIN', 'Falha ao alterar o estado de uma igreja.', ['erro' => substr($exception->getMessage(), 0, 400)]);
            return Response::redirect(url('superadmin/igrejas/' . $id . '/editar'));
        }
    }

    public function resendEmail(): Response
    {
        $guard = $this->authorize();
        if ($guard instanceof Response) {
            return $guard;
        }
        $emailId = $this->routeId();
        $actor = $this->actor();
        $service = new SuperadminService();
        $sent = $service->retryEmail(
            $emailId,
            $this->request,
            (int) ($actor['id'] ?? 0),
            (string) ($actor['nome_completo'] ?? 'Superadmin')
        );
        $message = $service->email($emailId);
        $churchId = is_array($message) ? (int) ($message['igreja_id'] ?? 0) : 0;
        if ($churchId < 1) {
            return $this->notFound();
        }
        return Response::redirect(url('superadmin/igrejas/' . $churchId . '/editar?resultado=' . ($sent ? 'email_enviado' : 'email_falhou')));
    }

    private function routeId(): int
    {
        $value = (string) $this->request->route('id', '');
        return ctype_digit($value) ? (int) $value : 0;
    }
}
