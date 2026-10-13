<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Repositories\UsuarioRepository;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Criação única do primeiro Superadmin sem senha hard-coded ou seed SQL inseguro. */
final class SetupSuperadminService
{
    /** @var UsuarioRepository */
    private $users;
    /** @var AuditoriaService */
    private $audit;

    public function __construct(?UsuarioRepository $users = null, ?AuditoriaService $audit = null)
    {
        $this->users = $users ?: new UsuarioRepository();
        $this->audit = $audit ?: new AuditoriaService();
    }

    public function isAvailable(): bool
    {
        $secret = trim((string) Config::env('SUPERADMIN_BOOTSTRAP_TOKEN', ''));
        if (strlen($secret) < 32) {
            return false;
        }
        $status = $this->users->bootstrapStatus(Database::connection());
        return is_array($status) && empty($status['superadmin_criado_em']);
    }

    public function create(string $name, string $email, string $password, string $providedToken, Request $request): bool
    {
        $configuredToken = trim((string) Config::env('SUPERADMIN_BOOTSTRAP_TOKEN', ''));
        $name = trim($name);
        $email = strtolower(trim($email));
        if (strlen($configuredToken) < 32 || $providedToken === ''
            || !hash_equals($configuredToken, $providedToken)
            || $name === '' || $this->length($name) > 180
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 191
            || !PasswordHasher::isAcceptable($password)
        ) {
            return false;
        }

        $passwordHash = PasswordHasher::hash($password);
        return Database::transaction(function (PDO $pdo) use ($name, $email, $passwordHash, $request): bool {
            $bootstrap = $this->users->lockBootstrapRow($pdo);
            if (!is_array($bootstrap) || !empty($bootstrap['superadmin_criado_em'])) {
                return false;
            }
            $statement = $pdo->prepare("SELECT id FROM usuarios WHERE papel = 'superadmin' LIMIT 1 FOR UPDATE");
            $statement->execute();
            if ($statement->fetchColumn() !== false) {
                $this->users->completeBootstrap($pdo);
                return false;
            }

            $userId = $this->users->createSuperadmin($pdo, $name, $email, $passwordHash);
            $this->users->completeBootstrap($pdo);
            $this->audit->registar(
                'criar_superadmin',
                'superadmin',
                'Conta inicial de Superadmin criada.',
                $request,
                $userId,
                $name,
                null,
                null,
                ['bootstrap' => true],
                $pdo
            );
            return true;
        });
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
