<?php
namespace App\Services;

use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AuditRepository;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Auditoria não bloqueante: falha do log nunca desfaz uma operação válida. */
final class AuditoriaService
{
    /** @var AuditRepository */
    private $repository;

    public function __construct(?AuditRepository $repository = null)
    {
        $this->repository = $repository ?: new AuditRepository();
    }

    public function registar(
        string $action,
        string $module,
        string $description,
        ?Request $request = null,
        ?int $userId = null,
        ?string $userName = null,
        ?int $churchId = null,
        $before = null,
        $after = null,
        ?PDO $connection = null
    ): void {
        $user = Session::get('_auth_user', []);
        if (is_array($user)) {
            if ($userId === null && isset($user['id'])) {
                $userId = (int) $user['id'];
            }
            if ($userName === null && isset($user['nome_completo'])) {
                $userName = (string) $user['nome_completo'];
            }
            if ($churchId === null && isset($user['igreja_id']) && $user['igreja_id'] !== null) {
                $churchId = (int) $user['igreja_id'];
            }
        }

        $event = [
            'igreja_id' => $churchId,
            'usuario_id' => $userId,
            'usuario_nome' => $userName ?: 'Sistema',
            'acao' => $action,
            'modulo' => $module,
            'descricao' => $description,
            'antes' => $before,
            'depois' => $after,
            'ip' => $request ? $request->ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => $request ? $request->userAgent() : (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ];

        try {
            $this->repository->insert($event, $connection);
        } catch (\Throwable $exception) {
            ErrorHandler::log('AUDITORIA', 'Não foi possível gravar um registo de auditoria.', [
                'acao' => $action,
                'modulo' => $module,
                'erro' => substr($exception->getMessage(), 0, 400),
            ]);
        }
    }
}
