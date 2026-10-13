<?php
namespace App\Services;

use App\Core\Request;
use App\Repositories\UsuarioRepository;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Dados editáveis do perfil do próprio utilizador autenticado. */
final class PerfilService
{
    private $users;
    private $audit;

    public function __construct(?UsuarioRepository $users = null, ?AuditoriaService $audit = null)
    {
        $this->users = $users ?: new UsuarioRepository();
        $this->audit = $audit ?: new AuditoriaService();
    }

    public function encontrar(int $churchId, int $userId): ?array
    {
        return $this->users->encontrarPerfilNaIgreja($churchId, $userId);
    }

    public function actualizarNome(int $churchId, int $userId, $nameInput, Request $request): array
    {
        $name = is_string($nameInput) ? trim($nameInput) : '';
        $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($length < 2 || $length > 180) {
            return ['sucesso' => false, 'erro' => 'O nome deve ter entre 2 e 180 caracteres.', 'nome' => $name];
        }
        $before = $this->users->encontrarPerfilNaIgreja($churchId, $userId);
        if ($before === null || !$this->users->actualizarNomeProprio($churchId, $userId, $name)) {
            return ['sucesso' => false, 'erro' => 'Não foi possível actualizar o perfil desta sessão.', 'nome' => $name];
        }
        $this->audit->registar(
            'editar', 'profile', 'Actualizou o nome do próprio perfil.',
            $request, $userId, $name, $churchId,
            ['nome_completo' => $before['nome_completo']], ['nome_completo' => $name]
        );
        return ['sucesso' => true, 'nome' => $name];
    }
}
