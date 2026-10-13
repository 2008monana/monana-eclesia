<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Repositories\EmailQueueRepository;
use App\Repositories\IgrejaRepository;
use App\Repositories\PlanoRepository;
use App\Repositories\SessionRepository;
use App\Repositories\UsuarioRepository;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Casos de uso do painel de plataforma, sem expor SQL aos controladores. */
final class SuperadminService
{
    /** @var IgrejaRepository */
    private $churches;
    /** @var PlanoRepository */
    private $plans;
    /** @var EmailQueueRepository */
    private $emails;
    /** @var MailService */
    private $mail;
    /** @var AuditoriaService */
    private $audit;
    /** @var SessionRepository */
    private $sessions;
    /** @var UsuarioRepository */
    private $users;

    public function __construct(
        ?IgrejaRepository $churches = null,
        ?PlanoRepository $plans = null,
        ?EmailQueueRepository $emails = null,
        ?MailService $mail = null,
        ?AuditoriaService $audit = null,
        ?SessionRepository $sessions = null,
        ?UsuarioRepository $users = null
    ) {
        $this->churches = $churches ?: new IgrejaRepository();
        $this->plans = $plans ?: new PlanoRepository();
        $this->emails = $emails ?: new EmailQueueRepository();
        $this->mail = $mail ?: new MailService($this->emails);
        $this->audit = $audit ?: new AuditoriaService();
        $this->sessions = $sessions ?: new SessionRepository();
        $this->users = $users ?: new UsuarioRepository();
    }

    public function dashboardData(): array
    {
        return [
            'counts' => $this->churches->counts(),
            'churches' => array_slice($this->churches->all(), 0, 10),
            'emails' => $this->emails->latest(12),
            'plans' => $this->plans->all(),
        ];
    }

    public function churches(): array
    {
        return $this->churches->all();
    }

    public function church(int $id): ?array
    {
        return $this->churches->find($id);
    }

    public function emailsForChurch(int $churchId): array
    {
        return $this->emails->latestForChurch($churchId);
    }

    public function email(int $id): ?array
    {
        return $this->emails->findById($id);
    }

    public function plans(bool $activeOnly = false): array
    {
        return $this->plans->all($activeOnly);
    }

    public function updateChurch(int $id, array $input, Request $request, int $actorId, string $actorName): bool
    {
        $existing = $this->churches->find($id);
        if ($existing === null) {
            return false;
        }
        if ((string) ($existing['estado'] ?? '') === 'encerrada') {
            throw new \DomainException('Uma igreja encerrada não pode ser editada.');
        }
        $name = trim((string) ($input['nome'] ?? ''));
        $planId = filter_var($input['plano_id'] ?? null, FILTER_VALIDATE_INT);
        $phone = trim((string) ($input['telefone'] ?? ''));
        $city = trim((string) ($input['cidade'] ?? ''));
        if ($name === '' || $this->length($name) > 180
            || $planId === false || (int) $planId < 1
            || $this->length($phone) > 40 || $this->length($city) > 120
        ) {
            throw new \InvalidArgumentException('Confirma o nome, o plano, o telefone e a cidade.');
        }
        if ($this->plans->find((int) $planId) === null) {
            throw new \InvalidArgumentException('O plano indicado não existe.');
        }

        $updated = Database::transaction(function (\PDO $pdo) use (
            $id,
            $name,
            $planId,
            $phone,
            $city,
            $existing,
            $request,
            $actorId,
            $actorName
        ): bool {
            if (!$this->churches->update($id, [
                'nome' => $name,
                'plano_id' => (int) $planId,
                'telefone' => $phone,
                'cidade' => $city,
            ])) {
                return false;
            }
            $this->churches->setConfigurationDisplayName($id, $name);
            $this->audit->registar(
                'editar_igreja',
                'superadmin',
                'Dados da igreja actualizados pelo Superadmin.',
                $request,
                $actorId,
                $actorName,
                null,
                ['nome' => $existing['nome'], 'plano_id' => $existing['plano_id']],
                ['nome' => $name, 'plano_id' => (int) $planId],
                $pdo
            );
            return true;
        });
        if (!$updated) {
            throw new \DomainException('A igreja já foi encerrada ou deixou de existir.');
        }
        return true;
    }

    public function setChurchStatus(int $id, string $status, string $reason, Request $request, int $actorId, string $actorName): bool
    {
        $existing = $this->churches->find($id);
        if ($existing === null) {
            return false;
        }
        if (!in_array($status, ['activa', 'suspensa', 'encerrada'], true)) {
            throw new \InvalidArgumentException('Estado da igreja inválido.');
        }
        $currentState = (string) ($existing['estado'] ?? '');
        if ($currentState === 'encerrada') {
            throw new \DomainException('Uma igreja encerrada não pode ser reactivada nem alterada.');
        }
        if ($status === 'activa' && $currentState !== 'suspensa') {
            throw new \DomainException('Só uma igreja suspensa pode ser reactivada por esta acção.');
        }
        if ($status === 'suspensa' && $currentState !== 'activa') {
            throw new \DomainException('Só uma igreja activa pode ser suspensa.');
        }
        $reason = trim($reason);
        if (in_array($status, ['suspensa', 'encerrada'], true) && $this->length($reason) > 500) {
            throw new \InvalidArgumentException('O motivo não pode ultrapassar 500 caracteres.');
        }
        return Database::transaction(function (\PDO $pdo) use (
            $id,
            $status,
            $reason,
            $request,
            $actorId,
            $actorName,
            $currentState
        ): bool {
            $updated = $this->churches->setStatus(
                $id,
                $status,
                in_array($status, ['suspensa', 'encerrada'], true) && $reason !== '' ? $reason : null
            );
            if (!$updated) {
                return false;
            }
            if ($status !== 'activa') {
                $this->users->incrementSessionVersionsForChurch($pdo, $id);
                $this->sessions->revokeAllForChurchInTransaction($pdo, $id);
            }
            $this->audit->registar(
                $status === 'encerrada' ? 'encerrar_igreja' : ($status === 'suspensa' ? 'suspender_igreja' : 'reactivar_igreja'),
                'superadmin',
                $status === 'encerrada'
                    ? 'Acesso encerrado sem apagar dados da igreja.'
                    : ($status === 'suspensa' ? 'Igreja suspensa; todas as sessões foram revogadas.' : 'Igreja reactivada pelo Superadmin.'),
                $request,
                $actorId,
                $actorName,
                null,
                ['estado' => $currentState],
                ['estado' => $status, 'motivo' => in_array($status, ['suspensa', 'encerrada'], true) ? $reason : null],
                $pdo
            );
            return true;
        });
    }

    public function resendActivation(int $emailId, Request $request, int $actorId, string $actorName): bool
    {
        $message = $this->emails->findById($emailId);
        if ($message === null || (string) ($message['template'] ?? '') !== 'activacao') {
            return false;
        }
        return $this->retryEmail($emailId, $request, $actorId, $actorName);
    }

    /** Permite ao Superadmin repetir emails de activação ou redefinição ainda válidos. */
    public function retryEmail(int $emailId, Request $request, int $actorId, string $actorName): bool
    {
        $message = $this->emails->findById($emailId);
        $template = is_array($message) ? (string) ($message['template'] ?? '') : '';
        if ($message === null || empty($message['igreja_id'])
            || !in_array($template, ['activacao', 'redefinicao_senha'], true)
        ) {
            return false;
        }
        $sent = $this->mail->retry($emailId);
        $this->audit->registar(
            'reenviar_email',
            'superadmin',
            $sent ? 'Email da conta reenviado.' : 'Tentativa de reenvio de email da conta falhou.',
            $request,
            $actorId,
            $actorName,
            null,
            null,
            ['email_fila_id' => $emailId, 'tipo' => $template, 'entregue' => $sent]
        );
        return $sent;
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
