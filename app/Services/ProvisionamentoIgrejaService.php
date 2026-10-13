<?php
namespace App\Services;

use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Middleware\PapelMiddleware;
use App\Repositories\IgrejaRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\PlanoRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UsuarioRepository;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Criação atómica de igreja, configuração, admin, permissões, token e email. */
final class ProvisionamentoIgrejaService
{
    /** @var IgrejaRepository */
    private $churches;
    /** @var PlanoRepository */
    private $plans;
    /** @var UsuarioRepository */
    private $users;
    /** @var PermissionRepository */
    private $permissions;
    /** @var TokenRepository */
    private $tokens;
    /** @var MailService */
    private $mail;
    /** @var AuditoriaService */
    private $audit;

    public function __construct(
        ?IgrejaRepository $churches = null,
        ?PlanoRepository $plans = null,
        ?UsuarioRepository $users = null,
        ?PermissionRepository $permissions = null,
        ?TokenRepository $tokens = null,
        ?MailService $mail = null,
        ?AuditoriaService $audit = null
    ) {
        $this->churches = $churches ?: new IgrejaRepository();
        $this->plans = $plans ?: new PlanoRepository();
        $this->users = $users ?: new UsuarioRepository();
        $this->permissions = $permissions ?: new PermissionRepository();
        $this->tokens = $tokens ?: new TokenRepository();
        $this->mail = $mail ?: new MailService();
        $this->audit = $audit ?: new AuditoriaService();
    }

    /**
     * O commit inclui a mensagem na fila; a tentativa SMTP acontece depois,
     * portanto uma falha de email não desfaz a igreja nem o utilizador.
     */
    public function criar(array $input, Request $request, int $actorId, string $actorName): array
    {
        $churchName = trim((string) ($input['nome_igreja'] ?? ''));
        $adminName = trim((string) ($input['nome_admin'] ?? ''));
        $email = strtolower(trim((string) ($input['email_admin'] ?? '')));
        $planId = filter_var($input['plano_id'] ?? null, FILTER_VALIDATE_INT);
        $phone = trim((string) ($input['telefone'] ?? ''));
        $city = trim((string) ($input['cidade'] ?? ''));

        if ($churchName === '' || $this->length($churchName) > 180
            || $adminName === '' || $this->length($adminName) > 180
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 191
            || $planId === false || (int) $planId < 1
            || $this->length($phone) > 40 || $this->length($city) > 120
        ) {
            throw new \InvalidArgumentException('Confirma os dados da igreja, do administrador e do plano.');
        }
        if (!$this->mail->encryptionReady()) {
            throw new \RuntimeException('Configura APP_ENCRYPTION_KEY antes de criar contas que recebem links de activação.');
        }
        if ($this->users->findByEmail($email) !== null) {
            throw new \DomainException('Já existe uma conta com esse email. Usa outro endereço.');
        }

        // Senha provisória aleatória é apenas temporária, nunca enviada nem devolvida.
        $temporaryPassword = bin2hex(random_bytes(32));
        $temporaryPasswordHash = PasswordHasher::hash($temporaryPassword);
        unset($temporaryPassword);
        $activationToken = bin2hex(random_bytes(32));
        $activationHash = hash('sha256', $activationToken);

        $provisioned = Database::transaction(function (PDO $pdo) use (
            $churchName,
            $adminName,
            $email,
            $planId,
            $phone,
            $city,
            $temporaryPasswordHash,
            $activationToken,
            $activationHash,
            $request,
            $actorId,
            $actorName
        ): array {
            if (!$this->plans->activeExistsInTransaction($pdo, (int) $planId)) {
                throw new \DomainException('O plano escolhido não está activo.');
            }

            $slug = $this->uniqueSlug($pdo, $churchName);
            $churchId = $this->churches->create($pdo, [
                'plano_id' => (int) $planId,
                'nome' => $churchName,
                'slug' => $slug,
                'email_admin' => $email,
                'telefone' => $phone,
                'cidade' => $city,
            ]);
            $this->churches->createConfiguration($pdo, $churchId, $churchName);

            $userId = $this->users->createChurchAdmin($pdo, [
                'igreja_id' => $churchId,
                'nome_completo' => $adminName,
                'email' => $email,
                'senha_hash' => $temporaryPasswordHash,
            ]);
            $this->permissions->createDefaults($pdo, $userId, $churchId, [
                'dashboard', 'members', 'registry', 'lists', 'year_end', 'expenses', 'reports',
                'users', 'audit', 'backups', 'profile', 'settings',
            ]);
            $this->tokens->createActivationToken(
                $pdo,
                $userId,
                $activationHash,
                gmdate('Y-m-d H:i:s', time() + 86400)
            );
            $emailId = $this->mail->enqueueActivation(
                $pdo,
                $churchId,
                $userId,
                $email,
                $adminName,
                $churchName,
                $activationToken
            );
            $this->audit->registar(
                'criar_igreja',
                'superadmin',
                'Igreja criada e conta de administração enfileirada.',
                $request,
                $actorId,
                $actorName,
                null,
                null,
                ['igreja_id' => $churchId, 'plano_id' => (int) $planId],
                $pdo
            );
            return ['igreja_id' => $churchId, 'usuario_id' => $userId, 'email_id' => $emailId];
        });

        $sent = false;
        try {
            $sent = $this->mail->deliver((int) $provisioned['email_id']);
        } catch (\Throwable $exception) {
            // O provisionamento já foi confirmado; só regista a falha para reenvio.
            ErrorHandler::log('EMAIL', 'Falha pós-commit ao tentar entregar activação.', [
                'fila_id' => (int) $provisioned['email_id'],
                'erro' => substr($exception->getMessage(), 0, 400),
            ]);
        }
        $provisioned['email_enviado'] = $sent;
        return $provisioned;
    }

    private function uniqueSlug(PDO $pdo, string $name): string
    {
        $source = $name;
        if (function_exists('iconv')) {
            $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
            if (is_string($transliterated) && $transliterated !== '') {
                $source = $transliterated;
            }
        } else {
            $source = strtr($source, [
                'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e',
                'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
                'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ã' => 'a', 'É' => 'e', 'Ê' => 'e',
                'Í' => 'i', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ú' => 'u', 'Ç' => 'c',
            ]);
        }
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $source), '-'));
        if ($base === '') {
            $base = 'igreja';
        }
        $base = substr($base, 0, 150);
        $slug = $base;
        for ($attempt = 0; $attempt < 5 && $this->churches->slugExists($pdo, $slug); $attempt++) {
            $slug = $base . '-' . strtolower(bin2hex(random_bytes(3)));
        }
        if ($this->churches->slugExists($pdo, $slug)) {
            throw new \RuntimeException('Não foi possível reservar um endereço único para a igreja.');
        }
        return $slug;
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
