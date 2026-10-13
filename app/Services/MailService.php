<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\View;
use App\Repositories\EmailQueueRepository;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Renderiza, cifra, enfileira e tenta entregar emails sem controlar transacções de negócio. */
final class MailService
{
    /** @var EmailQueueRepository */
    private $queue;

    /** @var CryptoService */
    private $crypto;

    /** @var MailerInterface */
    private $mailer;

    /** @var View */
    private $view;

    public function __construct(
        ?EmailQueueRepository $queue = null,
        ?CryptoService $crypto = null,
        ?MailerInterface $mailer = null,
        ?View $view = null
    ) {
        $this->queue = $queue ?: new EmailQueueRepository();
        $this->crypto = $crypto ?: new CryptoService();
        $this->mailer = $mailer ?: self::configuredMailer();
        $this->view = $view ?: new View();
    }

    public static function configuredMailer(): MailerInterface
    {
        $configuration = (array) Config::get('mail', []);
        $driver = strtolower((string) ($configuration['mailer'] ?? 'log'));
        return $driver === 'smtp' ? new SmtpMailer($configuration) : new LogMailer();
    }

    /**
     * Persiste o email de activação dentro da transacção do provisionamento.
     * O token vai cifrado no payload; corpo HTML/texto guardam apenas {{LINK}}.
     */
    public function enqueueActivation(
        PDO $transaction,
        int $churchId,
        int $userId,
        string $recipient,
        string $name,
        string $churchName,
        string $rawToken
    ): int {
        $this->assertToken($rawToken);
        $encrypted = $this->crypto->encrypt([
            'tipo' => 'activacao',
            'token' => $rawToken,
        ]);
        $html = $this->view->render('emails/activacao', [
            'name' => $name,
            'churchName' => $churchName,
            'activationLink' => '{{LINK}}',
        ]);
        $text = 'Olá ' . $name . '. Activa a conta de ' . $churchName
            . ' e define a tua senha: {{LINK}}. Este link expira em 24 horas.';

        return $this->queue->create(
            $churchId,
            $userId,
            $recipient,
            'Activa a tua conta — MonanaEclésia',
            $html,
            $text,
            'activacao',
            $encrypted,
            $transaction
        );
    }

    /** Persiste um link de redefinição de uso único; validade validada pelo token. */
    public function enqueuePasswordReset(
        PDO $transaction,
        ?int $churchId,
        int $userId,
        string $recipient,
        string $name,
        string $rawToken
    ): int {
        $this->assertToken($rawToken);
        $encrypted = $this->crypto->encrypt([
            'tipo' => 'redefinicao_senha',
            'token' => $rawToken,
        ]);
        $html = $this->view->render('emails/redefinicao-senha', [
            'name' => $name,
            'resetLink' => '{{LINK}}',
        ]);
        $text = 'Olá ' . $name . '. Redefine a senha da tua conta: {{LINK}}. Este link expira em 60 minutos.';

        return $this->queue->create(
            $churchId,
            $userId,
            $recipient,
            'Redefinição de senha — MonanaEclésia',
            $html,
            $text,
            'redefinicao_senha',
            $encrypted,
            $transaction
        );
    }

    /** Tenta entregar uma mensagem já confirmada na fila; falhas ficam registadas. */
    public function deliver(int $id): bool
    {
        $message = $this->queue->findById($id);
        if ($message === null) {
            return false;
        }
        if ((string) ($message['estado'] ?? '') === 'enviado') {
            return true;
        }
        if (!$this->queue->isReady($id)) {
            return false;
        }

        try {
            $html = (string) ($message['corpo_html'] ?? '');
            $text = (string) ($message['corpo_texto'] ?? '');
            $encrypted = (string) ($message['dados_cifrados'] ?? '');
            if ($encrypted !== '') {
                $payload = $this->crypto->decrypt($encrypted);
                $token = (string) ($payload['token'] ?? '');
                $this->assertToken($token);
                $linkPath = ($payload['tipo'] ?? '') === 'activacao'
                    ? 'activar-conta?token='
                    : 'resetar-senha?token=';
                $link = url($linkPath . rawurlencode($token));
                $htmlLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html = str_replace('{{LINK}}', $htmlLink, $html);
                $text = str_replace('{{LINK}}', $link, $text);
            }

            $this->mailer->send(
                (string) $message['destinatario'],
                (string) $message['assunto'],
                $html,
                $text
            );
            $this->queue->markSent($id);
            return true;
        } catch (\Throwable $exception) {
            $safeError = preg_replace('/[a-f0-9]{64}/i', '[token omitido]', $exception->getMessage());
            $safeError = is_string($safeError) ? $safeError : 'Falha ao entregar email.';
            try {
                $this->queue->markFailed($id, $safeError);
            } catch (\Throwable $storageException) {
                ErrorHandler::logException($storageException);
            }
            ErrorHandler::log('EMAIL', 'Falha na entrega de uma mensagem enfileirada.', [
                'id_fila' => $id,
                'tipo' => (string) ($message['template'] ?? 'generico'),
                'erro' => substr($safeError, 0, 500),
            ]);
            return false;
        }
    }

    /** Reenvia uma entrada da fila após pedido explícito do Superadmin. */
    public function retry(int $id): bool
    {
        $message = $this->queue->findById($id);
        $template = is_array($message) ? (string) ($message['template'] ?? '') : '';
        if ($message === null || empty($message['dados_cifrados'])
            || (string) ($message['estado'] ?? '') === 'enviado'
            || !in_array($template, ['activacao', 'redefinicao_senha'], true)
        ) {
            return false;
        }

        try {
            $payload = $this->crypto->decrypt((string) $message['dados_cifrados']);
            $token = (string) ($payload['token'] ?? '');
            $this->assertToken($token);
            $tokenHash = hash('sha256', $token);
            $payloadType = (string) ($payload['tipo'] ?? '');
            if (($template === 'activacao' && $payloadType !== 'activacao')
                || ($template === 'redefinicao_senha' && $payloadType !== 'redefinicao_senha')
            ) {
                return false;
            }

            if ($template === 'activacao'
                && !(new \App\Repositories\TokenRepository())->findActivationToken($tokenHash)
            ) {
                if (!$this->renewActivation($message)) {
                    return false;
                }
            } elseif ($template === 'redefinicao_senha'
                && !(new \App\Repositories\TokenRepository())->findPasswordToken($tokenHash)
            ) {
                return false;
            }
        } catch (\Throwable $exception) {
            ErrorHandler::log('EMAIL', 'Não foi possível preparar o reenvio de uma mensagem.', [
                'id_fila' => $id,
                'erro' => substr($exception->getMessage(), 0, 400),
            ]);
            return false;
        }

        $this->queue->scheduleRetry($id);
        return $this->deliver($id);
    }

    private function renewActivation(array $message): bool
    {
        $userId = (int) ($message['usuario_id'] ?? 0);
        $emailId = (int) ($message['id'] ?? 0);
        if ($userId < 1 || $emailId < 1) {
            return false;
        }
        $user = (new \App\Repositories\UsuarioRepository())->findForAuthentication($userId);
        if (!is_array($user) || (int) ($user['ativo'] ?? 1) !== 0
            || !empty($user['email_verificado_em']) || empty($user['igreja_id'])
            || (string) ($user['igreja_estado'] ?? '') !== 'pendente'
        ) {
            return false;
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $encrypted = $this->crypto->encrypt(['tipo' => 'activacao', 'token' => $rawToken]);
        $html = $this->view->render('emails/activacao', [
            'name' => (string) $user['nome_completo'],
            'churchName' => (string) ($user['igreja_nome'] ?? 'a tua igreja'),
            'activationLink' => '{{LINK}}',
        ]);
        $text = 'Olá ' . (string) $user['nome_completo'] . '. Activa a conta de '
            . (string) ($user['igreja_nome'] ?? 'a tua igreja')
            . ' e define a tua senha: {{LINK}}. Este link expira em 24 horas.';

        \App\Core\Database::transaction(function (\PDO $pdo) use ($userId, $tokenHash, $emailId, $html, $text, $encrypted): void {
            $tokens = new \App\Repositories\TokenRepository();
            $tokens->invalidateActivationTokens($pdo, $userId);
            $tokens->createActivationToken($pdo, $userId, $tokenHash, gmdate('Y-m-d H:i:s', time() + 86400));
            $this->queue->renewPayload($emailId, $html, $text, $encrypted, $pdo);
        });
        return true;
    }

    public function queue(): EmailQueueRepository
    {
        return $this->queue;
    }

    public function encryptionReady(): bool
    {
        return $this->crypto->isConfigured();
    }

    private function assertToken(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new \InvalidArgumentException('O token de email tem formato inválido.');
        }
    }
}
