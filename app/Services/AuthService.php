<?php
namespace App\Services;

use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PapelMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\SessionRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UsuarioRepository;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Regras de autenticação, activação, recuperação e gestão de sessões. */
final class AuthService
{
    /** @var UsuarioRepository */
    private $users;
    /** @var LoginAttemptRepository */
    private $attempts;
    /** @var SessionRepository */
    private $sessions;
    /** @var TokenRepository */
    private $tokens;
    /** @var MailService */
    private $mail;
    /** @var AuditoriaService */
    private $audit;
    /** @var RateLimitMiddleware */
    private $rateLimit;

    public function __construct(
        ?UsuarioRepository $users = null,
        ?LoginAttemptRepository $attempts = null,
        ?SessionRepository $sessions = null,
        ?TokenRepository $tokens = null,
        ?MailService $mail = null,
        ?AuditoriaService $audit = null,
        ?RateLimitMiddleware $rateLimit = null
    ) {
        $this->users = $users ?: new UsuarioRepository();
        $this->attempts = $attempts ?: new LoginAttemptRepository();
        $this->sessions = $sessions ?: new SessionRepository();
        $this->tokens = $tokens ?: new TokenRepository();
        $this->mail = $mail ?: new MailService();
        $this->audit = $audit ?: new AuditoriaService();
        $this->rateLimit = $rateLimit ?: new RateLimitMiddleware($this->attempts);
    }

    /** Mensagem genérica para não revelar existência nem estado de contas. */
    public function login(string $email, string $password, string $ip, string $userAgent, Request $request): array
    {
        $email = strtolower(trim($email));
        $ip = $ip !== '' ? $ip : 'desconhecido';

        if ($this->rateLimit->isLimited($email, $ip)) {
            $this->attempts->record($email, $ip, false);
            $this->audit->registar('login_falhou', 'auth', 'Tentativa de autenticação recusada.', $request);
            return ['success' => false, 'limited' => true];
        }
        if (strlen($email) > 191 || strlen($password) > 200 || $password === '') {
            $this->attempts->record($email, $ip, false);
            $this->audit->registar('login_falhou', 'auth', 'Tentativa de autenticação recusada.', $request);
            return ['success' => false, 'limited' => false];
        }

        $user = filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            ? $this->users->findByEmail($email)
            : null;
        $storedHash = is_array($user) ? (string) ($user['senha_hash'] ?? '') : '';
        if ($storedHash === '') {
            $storedHash = self::dummyHash();
        }
        $passwordMatches = PasswordHasher::verify($password, $storedHash);
        $role = is_array($user) ? (string) ($user['papel'] ?? '') : '';
        $churchId = is_array($user) && $user['igreja_id'] !== null ? (int) $user['igreja_id'] : null;
        $roleValid = PapelMiddleware::allows($role, PapelMiddleware::ROLES);
        $tenantValid = $role === 'superadmin'
            ? $churchId === null
            : ($churchId !== null && (string) ($user['igreja_estado'] ?? '') === 'activa');
        $eligible = is_array($user)
            && $passwordMatches
            && (int) ($user['ativo'] ?? 0) === 1
            && !empty($user['email_verificado_em'])
            && $roleValid
            && $tenantValid;

        $this->attempts->record($email, $ip, $eligible);
        if (!$eligible) {
            $this->audit->registar('login_falhou', 'auth', 'Tentativa de autenticação recusada.', $request);
            return ['success' => false, 'limited' => false];
        }

        $userId = (int) $user['id'];
        $passwordHash = (string) $user['senha_hash'];
        if (PasswordHasher::needsRehash($passwordHash)) {
            $passwordHash = PasswordHasher::hash($password);
            $this->users->updateLogin($userId, $passwordHash);
        } else {
            $this->users->updateLogin($userId);
        }

        Session::start();
        if (!Session::regenerate()) {
            throw new \RuntimeException('Não foi possível renovar o identificador da sessão.');
        }
        $_SESSION = ['_monana_last_activity' => time()];
        CsrfMiddleware::rotate();

        $version = max(1, (int) ($user['versao_sessao'] ?? 1));
        $sessionHash = hash('sha256', session_id());
        $this->sessions->create(
            $userId,
            $churchId,
            $version,
            $sessionHash,
            $ip,
            $userAgent,
            Session::lifetime()
        );

        Session::set('auth_user_id', $userId);
        Session::set('auth_igreja_id', $churchId);
        Session::set('auth_papel', $role);
        Session::set('auth_version', $version);
        Session::set('auth_session_hash', $sessionHash);
        unset($user['senha_hash']);
        Session::set('_auth_user', $user);

        // Mantém aliases usados pelo código legado durante a migração faseada.
        Session::set('user_id', $userId);
        Session::set('user_nome', (string) $user['nome_completo']);
        Session::set('user_email', (string) $user['email']);
        Session::set('user_perfil', $role === 'admin_igreja' ? 'admin' : $role);
        Session::set('user_modulos', $user['modulos_permitidos'] ?? '');

        $this->audit->registar(
            'login',
            'auth',
            'Autenticação realizada com sucesso.',
            $request,
            $userId,
            (string) $user['nome_completo'],
            $churchId
        );

        $destination = $role === 'superadmin' ? 'superadmin' : 'painel';
        if ((int) ($user['deve_trocar_senha'] ?? 0) === 1) {
            $destination = 'trocar-senha';
        }
        return ['success' => true, 'limited' => false, 'redirect' => url($destination)];
    }

    public function logout(Request $request): void
    {
        Session::start();
        $userId = (int) Session::get('auth_user_id', 0);
        $sessionHash = (string) Session::get('auth_session_hash', '');
        $user = Session::get('_auth_user', []);
        if ($userId > 0) {
            if ($sessionHash !== '') {
                $this->sessions->revokeCurrent($userId, $sessionHash);
            }
            $this->audit->registar(
                'logout',
                'auth',
                'Sessão terminada.',
                $request,
                $userId,
                is_array($user) ? (string) ($user['nome_completo'] ?? 'Utilizador') : 'Utilizador',
                Session::get('auth_igreja_id') !== null ? (int) Session::get('auth_igreja_id') : null
            );
        }
        Session::destroy();
    }

    /** Responde sem distinção para email existente, inexistente ou bloqueado. */
    public function requestPasswordReset(string $email, string $ip, Request $request): void
    {
        $email = strtolower(trim($email));
        $ip = $ip !== '' ? $ip : 'desconhecido';
        if ($this->rateLimit->isLimited($email, $ip)) {
            return;
        }

        // Todas as requisições consomem o mesmo limite, sem revelar se o email existe.
        $this->attempts->record($email, $ip, false);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $user = $this->users->findByEmail($email);
        if (!$this->isEligibleForEmail($user)) {
            return;
        }

        try {
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $queueId = Database::transaction(function (PDO $pdo) use ($user, $rawToken, $tokenHash, $ip, $request): int {
                $userId = (int) $user['id'];
                $this->tokens->invalidatePasswordTokens($pdo, $userId);
                $this->tokens->createPasswordToken(
                    $pdo,
                    $userId,
                    $tokenHash,
                    gmdate('Y-m-d H:i:s', time() + 3600),
                    $ip
                );
                $id = $this->mail->enqueuePasswordReset(
                    $pdo,
                    $user['igreja_id'] !== null ? (int) $user['igreja_id'] : null,
                    $userId,
                    (string) $user['email'],
                    (string) $user['nome_completo'],
                    $rawToken
                );
                $this->audit->registar(
                    'pedido_reset_senha',
                    'auth',
                    'Pedido de redefinição de senha registado.',
                    $request,
                    $userId,
                    (string) $user['nome_completo'],
                    $user['igreja_id'] !== null ? (int) $user['igreja_id'] : null,
                    null,
                    null,
                    $pdo
                );
                return $id;
            });
            // A entrega é posterior ao commit: falha SMTP nunca desfaz o pedido.
            $this->mail->deliver($queueId);
        } catch (\Throwable $exception) {
            ErrorHandler::log('AUTH', 'Não foi possível preparar um email de redefinição.', [
                'erro' => substr($exception->getMessage(), 0, 400),
            ]);
        }
    }

    public function activationDetails(string $rawToken): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }
        $activation = $this->tokens->findActivationToken(hash('sha256', $rawToken));
        if (!is_array($activation) || (int) ($activation['ativo'] ?? 1) !== 0
            || !empty($activation['email_verificado_em'])
            || (string) ($activation['igreja_estado'] ?? '') !== 'pendente'
        ) {
            return null;
        }
        return [
            'nome' => (string) ($activation['nome_completo'] ?? ''),
            'igreja_nome' => (string) ($activation['igreja_nome'] ?? 'a tua igreja'),
        ];
    }

    public function isResetTokenValid(string $rawToken): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return false;
        }
        $token = $this->tokens->findPasswordToken(hash('sha256', $rawToken));
        return is_array($token) && (int) ($token['ativo'] ?? 0) === 1;
    }

    public function resetPassword(string $rawToken, string $newPassword, Request $request): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken) || !PasswordHasher::isAcceptable($newPassword)) {
            return false;
        }
        $ip = $request->ip() !== '' ? $request->ip() : 'desconhecido';
        if ($this->rateLimit->isLimited('', $ip)) {
            return false;
        }
        if (!$this->isResetTokenValid($rawToken)) {
            $this->attempts->record('', $ip, false);
            return false;
        }
        $hash = hash('sha256', $rawToken);
        $passwordHash = PasswordHasher::hash($newPassword);

        try {
            $userId = Database::transaction(function (PDO $pdo) use ($hash, $passwordHash, $request) {
                $token = $this->tokens->findPasswordTokenInTransaction($pdo, $hash);
                if (!is_array($token) || (int) ($token['ativo'] ?? 0) !== 1) {
                    return null;
                }
                $id = (int) $token['usuario_id'];
                if (!$this->users->updatePasswordHashInTransaction($pdo, $id, $passwordHash)) {
                    return null;
                }
                if ($this->tokens->consumePasswordToken($pdo, $hash) !== $id) {
                    throw new \RuntimeException('O token deixou de ser válido durante a redefinição.');
                }
                $this->tokens->invalidatePasswordTokens($pdo, $id);
                $this->sessions->revokeAllInTransaction($pdo, $id);
                $user = $this->users->findForAuthentication($id);
                $this->audit->registar(
                    'resetar_senha',
                    'auth',
                    'Senha redefinida através de token de uso único.',
                    $request,
                    $id,
                    is_array($user) ? (string) $user['nome_completo'] : 'Utilizador',
                    is_array($user) && $user['igreja_id'] !== null ? (int) $user['igreja_id'] : null,
                    null,
                    null,
                    $pdo
                );
                return $id;
            });
        } catch (\Throwable $exception) {
            ErrorHandler::log('AUTH', 'Falha ao redefinir senha.', ['erro' => substr($exception->getMessage(), 0, 400)]);
            return false;
        }

        if ($userId === null) {
            return false;
        }
        $this->wipeMailSecrets((int) $userId, ['redefinicao_senha']);
        return true;
    }

    public function activateAccount(string $rawToken, string $newPassword, Request $request): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken) || !PasswordHasher::isAcceptable($newPassword)) {
            return false;
        }
        $ip = $request->ip() !== '' ? $request->ip() : 'desconhecido';
        if ($this->rateLimit->isLimited('', $ip)) {
            return false;
        }
        if ($this->activationDetails($rawToken) === null) {
            $this->attempts->record('', $ip, false);
            return false;
        }
        $hash = hash('sha256', $rawToken);
        $passwordHash = PasswordHasher::hash($newPassword);

        try {
            $userId = Database::transaction(function (PDO $pdo) use ($hash, $passwordHash, $request) {
                $token = $this->tokens->findActivationTokenInTransaction($pdo, $hash);
                if (!is_array($token)
                    || (int) ($token['ativo'] ?? 1) !== 0
                    || !empty($token['email_verificado_em'])
                    || (string) ($token['igreja_estado'] ?? '') !== 'pendente'
                    || empty($token['igreja_id'])
                ) {
                    return null;
                }
                $id = (int) $token['usuario_id'];
                $churchId = $this->users->updateActivation($pdo, $id, $passwordHash);
                if ($churchId === null) {
                    return null;
                }
                if ($this->tokens->consumeActivationToken($pdo, $hash) !== $id) {
                    throw new \RuntimeException('O token deixou de ser válido durante a activação.');
                }
                $this->tokens->invalidateActivationTokens($pdo, $id);
                if (!$this->users->activateChurchIfPending($pdo, $churchId)) {
                    throw new \RuntimeException('A igreja deixou de estar pendente durante a activação.');
                }
                $this->audit->registar(
                    'activar_conta',
                    'auth',
                    'Conta de administração activada e senha definida.',
                    $request,
                    $id,
                    'Administração da igreja',
                    $churchId,
                    null,
                    null,
                    $pdo
                );
                return $id;
            });
        } catch (\Throwable $exception) {
            ErrorHandler::log('AUTH', 'Falha ao activar uma conta.', ['erro' => substr($exception->getMessage(), 0, 400)]);
            return false;
        }

        if ($userId === null) {
            return false;
        }
        $this->wipeMailSecrets((int) $userId, ['activacao']);
        return true;
    }

    /** Alteração autenticada exige senha actual, salvo troca obrigatória. */
    public function changePassword(int $userId, string $currentPassword, string $newPassword, Request $request): bool
    {
        if (!PasswordHasher::isAcceptable($newPassword)) {
            return false;
        }
        $user = $this->users->findForPasswordChange($userId);
        if ($user === null || (int) ($user['ativo'] ?? 0) !== 1) {
            return false;
        }
        $forced = (int) ($user['deve_trocar_senha'] ?? 0) === 1;
        if (!$forced && !PasswordHasher::verify($currentPassword, (string) $user['senha_hash'])) {
            return false;
        }

        $newHash = PasswordHasher::hash($newPassword);
        try {
            Database::transaction(function (PDO $pdo) use ($userId, $newHash, $user, $request): void {
                if (!$this->users->updatePasswordHashInTransaction($pdo, $userId, $newHash)) {
                    throw new \RuntimeException('A conta já não está activa.');
                }
                $this->sessions->revokeAllInTransaction($pdo, $userId);
                $this->tokens->invalidatePasswordTokens($pdo, $userId);
                $this->audit->registar(
                    'alterar_senha',
                    'auth',
                    'Senha alterada pelo próprio utilizador.',
                    $request,
                    $userId,
                    (string) $user['nome_completo'],
                    $user['igreja_id'] !== null ? (int) $user['igreja_id'] : null,
                    null,
                    null,
                    $pdo
                );
            });
        } catch (\Throwable $exception) {
            ErrorHandler::log('AUTH', 'Falha ao alterar a senha.', ['erro' => substr($exception->getMessage(), 0, 400)]);
            return false;
        }

        $this->wipeMailSecrets($userId, ['redefinicao_senha']);
        Session::destroy();
        return true;
    }

    /** Revoga todas as sessões activas do utilizador e termina a sessão corrente. */
    public function terminateAllSessions(int $userId, Request $request): void
    {
        $user = $this->users->findForAuthentication($userId);
        if ($user === null) {
            Session::destroy();
            return;
        }
        Database::transaction(function (PDO $pdo) use ($userId, $user, $request): void {
            $this->users->incrementSessionVersion($pdo, $userId);
            $this->sessions->revokeAllInTransaction($pdo, $userId);
            $this->audit->registar(
                'terminar_sessoes',
                'auth',
                'Todas as sessões do utilizador foram terminadas.',
                $request,
                $userId,
                (string) $user['nome_completo'],
                $user['igreja_id'] !== null ? (int) $user['igreja_id'] : null,
                null,
                null,
                $pdo
            );
        });
        Session::destroy();
    }

    private function wipeMailSecrets(int $userId, array $templates): void
    {
        try {
            $this->mail->queue()->wipeSecretsForUser($userId, $templates);
        } catch (\Throwable $exception) {
            ErrorHandler::log('AUTH', 'Não foi possível limpar payloads cifrados de email consumidos.', [
                'templates' => implode(',', $templates),
                'erro' => substr($exception->getMessage(), 0, 400),
            ]);
        }
    }

    private function isEligibleForEmail(?array $user): bool
    {
        if (!is_array($user) || (int) ($user['ativo'] ?? 0) !== 1 || empty($user['email_verificado_em'])) {
            return false;
        }
        $role = (string) ($user['papel'] ?? '');
        if (!PapelMiddleware::allows($role, PapelMiddleware::ROLES)) {
            return false;
        }
        if ($role === 'superadmin') {
            return $user['igreja_id'] === null;
        }
        return $user['igreja_id'] !== null && (string) ($user['igreja_estado'] ?? '') === 'activa';
    }

    private static function dummyHash(): string
    {
        static $hash = null;
        if (!is_string($hash)) {
            $hash = PasswordHasher::hash('monana-dummy-' . bin2hex(random_bytes(16)));
        }
        return $hash;
    }
}
