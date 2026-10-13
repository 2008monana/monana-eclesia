<?php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Obtém o inquilino apenas do utilizador revalidado na sessão. */
final class TenantMiddleware
{
    public function handle(Request $request): ?Response
    {
        $authResponse = (new AuthMiddleware())->handle($request);
        if ($authResponse instanceof Response) {
            return $authResponse;
        }

        $user = Session::get('_auth_user', []);
        if (!is_array($user) || ($user['papel'] ?? '') === 'superadmin'
            || empty($user['igreja_id'])
        ) {
            return Response::text('Esta área está disponível apenas para utilizadores de uma igreja.', 403);
        }
        return null;
    }

    /** A única fonte do tenant é a sessão já validada pelo AuthMiddleware. */
    public static function id(): ?int
    {
        $user = Session::get('_auth_user', []);
        if (!is_array($user) || ($user['papel'] ?? '') === 'superadmin') {
            return null;
        }
        $id = (int) ($user['igreja_id'] ?? 0);
        return $id > 0 ? $id : null;
    }
}
