<?php
namespace App\Services;

use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Repositories\PermissionRepository;
use App\Repositories\SessionRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UsuarioRepository;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Gestão de contas locais, permissões e convites por link de uso único. */
final class UsuarioService
{
    private const ROLES = ['pastor', 'tesoureiro', 'secretario', 'lider'];
    private const MODULES = [
        'dashboard' => 'Painel',
        'members' => 'Membros',
        'registry' => 'Cotas',
        'lists' => 'Listas',
        'expenses' => 'Saídas',
        'year_end' => 'Fim de ano',
        'reports' => 'Relatórios',
        'users' => 'Utilizadores',
        'audit' => 'Auditoria',
        'backups' => 'Backups',
        'profile' => 'Perfil',
        'settings' => 'Configurações',
    ];

    private $users;
    private $permissions;
    private $tokens;
    private $sessions;
    private $mail;
    private $audit;

    public function __construct(
        ?UsuarioRepository $users = null,
        ?PermissionRepository $permissions = null,
        ?TokenRepository $tokens = null,
        ?SessionRepository $sessions = null,
        ?MailService $mail = null,
        ?AuditoriaService $audit = null
    ) {
        $this->users = $users ?: new UsuarioRepository();
        $this->permissions = $permissions ?: new PermissionRepository();
        $this->tokens = $tokens ?: new TokenRepository();
        $this->sessions = $sessions ?: new SessionRepository();
        $this->mail = $mail ?: new MailService();
        $this->audit = $audit ?: new AuditoriaService();
    }

    public static function modulosDisponiveis(): array
    {
        return self::MODULES;
    }

    public function listar(int $churchId, $role = '', $status = ''): array
    {
        $role = is_string($role) && in_array($role, self::ROLES, true) ? $role : '';
        $status = is_string($status) && in_array($status, ['activo', 'inactivo'], true) ? $status : '';
        $users = $this->users->listarDaIgreja($churchId, $role, $status);
        $activeCount = 0;
        foreach ($users as $user) {
            $activeCount += (int) ($user['ativo'] ?? 0) === 1 ? 1 : 0;
        }
        return [
            'utilizadores' => $users,
            'filtroPapel' => $role,
            'filtroEstado' => $status,
            'total' => count($users),
            'activos' => $activeCount,
            'inactivos' => count($users) - $activeCount,
            'modulosDisponiveis' => self::MODULES,
        ];
    }

    public function encontrar(int $churchId, int $userId): ?array
    {
        $user = $this->users->encontrarNaIgreja($churchId, $userId);
        if ($user === null || (string) ($user['papel'] ?? '') === 'admin_igreja') {
            return null;
        }
        $user['modulos'] = $this->permissions->allowedModules($userId, $churchId);
        return $user;
    }

    public function validar(array $input): array
    {
        $name = $this->text($input['nome_completo'] ?? '');
        $email = strtolower($this->text($input['email'] ?? ''));
        $role = $this->text($input['papel'] ?? '');
        $modulesInput = is_array($input['modulos'] ?? null) ? $input['modulos'] : [];
        $modules = [];
        foreach ($modulesInput as $module) {
            if (is_string($module) && isset(self::MODULES[$module])) {
                $modules[] = $module;
            }
        }
        $modules = array_values(array_unique($modules));
        if (!in_array('dashboard', $modules, true)) {
            $modules[] = 'dashboard';
        }
        $errors = [];
        if ($name === '' || $this->length($name) < 2 || $this->length($name) > 180) {
            $errors['nome_completo'] = 'Indica um nome entre 2 e 180 caracteres.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 191) {
            $errors['email'] = 'Indica um endereço de email válido.';
        }
        if (!in_array($role, self::ROLES, true)) {
            $errors['papel'] = 'Selecciona um papel permitido para esta igreja.';
        }
        return [
            'valido' => $errors === [],
            'dados' => ['nome_completo' => $name, 'email' => $email, 'papel' => $role, 'modulos' => $modules],
            'erros' => $errors,
        ];
    }

    /** Cria conta sem senha conhecida pelo administrador e envia link de definição. */
    public function criar(int $churchId, int $actorId, array $input, Request $request): array
    {
        $validation = $this->validar($input);
        if (!$validation['valido']) {
            return ['sucesso' => false, 'erros' => $validation['erros']];
        }
        $data = $validation['dados'];
        if ($this->users->emailExiste($data['email'])) {
            return ['sucesso' => false, 'erros' => ['email' => 'Já existe uma conta com esse email.']];
        }
        if (!$this->mail->encryptionReady()) {
            return ['sucesso' => false, 'erros' => ['email' => 'O envio seguro de convites ainda não está configurado.']];
        }

        $temporaryPassword = bin2hex(random_bytes(32));
        $passwordHash = PasswordHasher::hash($temporaryPassword);
        unset($temporaryPassword);
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        try {
            $queuedId = Database::transaction(function (PDO $pdo) use ($churchId, $actorId, $data, $passwordHash, $rawToken, $tokenHash, $request): int {
                $userId = $this->users->criarNaIgreja($pdo, $churchId, [
                    'nome_completo' => $data['nome_completo'],
                    'email' => $data['email'],
                    'senha_hash' => $passwordHash,
                    'papel' => $data['papel'],
                    'modulos' => $data['modulos'],
                ]);
                $this->permissions->substituir($pdo, $userId, $churchId, $data['modulos']);
                $this->tokens->createPasswordToken($pdo, $userId, $tokenHash, gmdate('Y-m-d H:i:s', time() + 3600), $request->ip());
                $emailId = $this->mail->enqueuePasswordReset($pdo, $churchId, $userId, $data['email'], $data['nome_completo'], $rawToken);
                $this->audit->registar(
                    'criar', 'users', 'Criou uma conta local e enfileirou um link de definição de senha.',
                    $request, $actorId, null, $churchId, null,
                    ['usuario_id' => $userId, 'papel' => $data['papel'], 'modulos' => $data['modulos']], $pdo
                );
                return $emailId;
            });
        } catch (\Throwable $exception) {
            ErrorHandler::log('USERS', 'Não foi possível criar a conta local.', ['erro' => substr($exception->getMessage(), 0, 400)]);
            return ['sucesso' => false, 'erros' => ['geral' => 'Não foi possível criar a conta. Confirma os dados e tenta novamente.']];
        }
        $sent = false;
        try {
            $sent = $this->mail->deliver($queuedId);
        } catch (\Throwable $exception) {
            ErrorHandler::log('EMAIL', 'Falha pós-commit ao entregar convite de utilizador.', ['fila_id' => $queuedId, 'erro' => substr($exception->getMessage(), 0, 400)]);
        }
        return ['sucesso' => true, 'email_enviado' => $sent];
    }

    public function actualizar(int $churchId, int $actorId, int $userId, array $input, Request $request): array
    {
        $existing = $this->encontrar($churchId, $userId);
        if ($existing === null || $userId === $actorId) {
            return ['sucesso' => false, 'encontrado' => false, 'erros' => ['geral' => 'Utilizador não encontrado ou não permitido.']];
        }
        $input['email'] = (string) $existing['email'];
        $validation = $this->validar($input);
        if (!$validation['valido']) {
            return ['sucesso' => false, 'encontrado' => true, 'erros' => $validation['erros']];
        }
        $data = $validation['dados'];
        try {
            Database::transaction(function (PDO $pdo) use ($churchId, $actorId, $userId, $data, $existing, $request): void {
                if (!$this->users->actualizarNaIgreja($pdo, $churchId, $userId, $data)) {
                    throw new \RuntimeException('O utilizador deixou de pertencer a esta igreja.');
                }
                $this->permissions->substituir($pdo, $userId, $churchId, $data['modulos']);
                $this->sessions->revokeAllInTransaction($pdo, $userId);
                $this->audit->registar(
                    'editar', 'users', 'Actualizou o papel e as permissões de um utilizador.',
                    $request, $actorId, null, $churchId,
                    ['papel' => $existing['papel'], 'modulos' => $existing['modulos']],
                    ['papel' => $data['papel'], 'modulos' => $data['modulos']], $pdo
                );
            });
        } catch (\Throwable $exception) {
            ErrorHandler::log('USERS', 'Não foi possível actualizar a conta local.', ['erro' => substr($exception->getMessage(), 0, 400)]);
            return ['sucesso' => false, 'encontrado' => true, 'erros' => ['geral' => 'Não foi possível actualizar este utilizador.']];
        }
        return ['sucesso' => true, 'encontrado' => true];
    }

    public function alterarEstado(int $churchId, int $actorId, int $userId, bool $active, Request $request): array
    {
        $user = $this->users->encontrarNaIgreja($churchId, $userId);
        if ($user === null || $userId === $actorId || (string) ($user['papel'] ?? '') === 'admin_igreja') {
            return ['sucesso' => false, 'erro' => 'Utilizador não encontrado ou esta acção não é permitida.'];
        }
        try {
            Database::transaction(function (PDO $pdo) use ($churchId, $actorId, $userId, $active, $user, $request): void {
                if (!$this->users->definirEstado($churchId, $userId, $active)) {
                    throw new \RuntimeException('O utilizador deixou de pertencer a esta igreja.');
                }
                if (!$active) {
                    $this->sessions->revokeAllInTransaction($pdo, $userId);
                }
                $this->audit->registar(
                    $active ? 'activar' : 'desactivar', 'users',
                    ($active ? 'Activou' : 'Desactivou') . ' uma conta local.',
                    $request, $actorId, null, $churchId,
                    ['usuario_id' => $userId, 'activo' => (int) $user['ativo']],
                    ['usuario_id' => $userId, 'activo' => $active ? 1 : 0], $pdo
                );
            });
        } catch (\Throwable $exception) {
            ErrorHandler::log('USERS', 'Não foi possível alterar o estado da conta.', ['erro' => substr($exception->getMessage(), 0, 400)]);
            return ['sucesso' => false, 'erro' => 'Não foi possível alterar o estado do utilizador.'];
        }
        return ['sucesso' => true];
    }

    /** Revoga a conta em vez de apagar dados de auditoria ou referências. */
    public function remover(int $churchId, int $actorId, int $userId, Request $request): array
    {
        return $this->alterarEstado($churchId, $actorId, $userId, false, $request);
    }

    /** Novo link temporário para a caixa registada; nunca revela uma senha. */
    public function enviarLinkSenha(int $churchId, int $actorId, int $userId, Request $request): array
    {
        $user = $this->encontrar($churchId, $userId);
        if ($user === null || $userId === $actorId || (int) ($user['ativo'] ?? 0) !== 1) {
            return ['sucesso' => false, 'erro' => 'Utilizador activo não encontrado nesta igreja.'];
        }
        if (!$this->mail->encryptionReady()) {
            return ['sucesso' => false, 'erro' => 'O envio seguro de links ainda não está configurado.'];
        }
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        try {
            $emailId = Database::transaction(function (PDO $pdo) use ($churchId, $actorId, $userId, $user, $rawToken, $tokenHash, $request): int {
                $this->tokens->invalidatePasswordTokens($pdo, $userId);
                $this->tokens->createPasswordToken($pdo, $userId, $tokenHash, gmdate('Y-m-d H:i:s', time() + 3600), $request->ip());
                $queueId = $this->mail->enqueuePasswordReset(
                    $pdo, $churchId, $userId, (string) $user['email'], (string) $user['nome_completo'], $rawToken
                );
                $this->audit->registar(
                    'redefinir_senha', 'users', 'Enfileirou um link de uso único para definição de senha.',
                    $request, $actorId, null, $churchId, null, ['usuario_id' => $userId], $pdo
                );
                return $queueId;
            });
        } catch (\Throwable $exception) {
            ErrorHandler::log('USERS', 'Não foi possível criar o link de redefinição.', ['erro' => substr($exception->getMessage(), 0, 400)]);
            return ['sucesso' => false, 'erro' => 'Não foi possível preparar o link.'];
        }
        $sent = false;
        try {
            $sent = $this->mail->deliver($emailId);
        } catch (\Throwable $exception) {
            ErrorHandler::log('EMAIL', 'Falha pós-commit ao entregar redefinição da conta.', ['fila_id' => $emailId, 'erro' => substr($exception->getMessage(), 0, 400)]);
        }
        return ['sucesso' => true, 'email_enviado' => $sent];
    }

    private function text($value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
