<?php
namespace App\Services;

use App\Core\Request;
use App\Core\Validator;
use App\Repositories\MembroRepository;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Regras e validação do módulo de membros, sem SQL. */
final class MembroService
{
    private const PAGE_SIZE = 25;

    /** @var MembroRepository */
    private $members;

    /** @var AuditoriaService */
    private $audit;

    public function __construct(?MembroRepository $members = null, ?AuditoriaService $audit = null)
    {
        $this->members = $members ?: new MembroRepository();
        $this->audit = $audit ?: new AuditoriaService();
    }

    public function listar(int $churchId, array $input): array
    {
        $query = $this->stringValue($input['q'] ?? '');
        $query = trim($query);
        if (strlen($query) > 120) {
            $query = substr($query, 0, 120);
        }
        $category = $this->stringValue($input['categoria'] ?? '');
        if (!in_array($category, ['mama', 'papa', 'jovem', 'crianca'], true)) {
            $category = '';
        }
        $status = $this->stringValue($input['estado'] ?? 'activo');
        if (!in_array($status, ['activo', 'inactivo', 'todos'], true)) {
            $status = 'activo';
        }
        $pageValue = filter_var($input['pagina'] ?? 1, FILTER_VALIDATE_INT);
        $page = $pageValue === false ? 1 : max(1, (int) $pageValue);
        $filters = ['q' => $query, 'categoria' => $category, 'estado' => $status];
        $total = $this->members->contar($churchId, $filters);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);

        return [
            'membros' => $this->members->listar(
                $churchId,
                $filters,
                self::PAGE_SIZE,
                ($page - 1) * self::PAGE_SIZE
            ),
            'estatisticas' => $this->members->estatisticas($churchId),
            'filtros' => $filters,
            'pagina' => $page,
            'paginas' => $pages,
            'totalFiltrado' => $total,
            'porPagina' => self::PAGE_SIZE,
        ];
    }

    public function encontrar(int $churchId, int $memberId): ?array
    {
        if ($churchId < 1 || $memberId < 1) {
            return null;
        }
        return $this->members->encontrar($churchId, $memberId);
    }

    /** Devolve dados normalizados e erros por campo, sem persistir. */
    public function validar(array $input): array
    {
        $name = $input['nome'] ?? $input['nome_completo'] ?? '';
        $category = $input['categoria'] ?? '';
        $phone = $input['telefone'] ?? '';
        $address = $input['endereco'] ?? '';
        $birthDate = $input['data_nascimento'] ?? '';

        $name = $this->trimValue($name);
        $category = $this->trimValue($category);
        $phone = $this->trimValue($phone);
        $address = $this->trimValue($address);
        $birthDate = $this->trimValue($birthDate);

        $validator = new Validator([
            'nome_completo' => $name,
            'categoria' => $category,
            'telefone' => $phone,
            'endereco' => $address,
            'data_nascimento' => $birthDate,
        ]);
        $valid = $validator->validate([
            'nome_completo' => 'required|string|min:1|max:180',
            'categoria' => 'required|string|in:mama,papa,jovem,crianca',
            'telefone' => 'nullable|string|max:40',
            'endereco' => 'nullable|string|max:255',
            'data_nascimento' => 'nullable|date',
        ]);
        $values = $validator->validated();
        $data = [
            'nome_completo' => $this->displayString($name),
            'categoria' => $this->displayString($category),
            'telefone' => $this->displayString($phone),
            'endereco' => $this->displayString($address),
            'data_nascimento' => $this->displayString($birthDate),
        ];
        if ($valid) {
            $data = [
                'nome_completo' => (string) ($values['nome_completo'] ?? ''),
                'categoria' => (string) ($values['categoria'] ?? ''),
                'telefone' => $this->nullableString($values['telefone'] ?? null),
                'endereco' => $this->nullableString($values['endereco'] ?? null),
                'data_nascimento' => $this->nullableString($values['data_nascimento'] ?? null),
            ];
        }
        return [
            'valido' => $valid,
            'dados' => $data,
            'erros' => $validator->firstErrors(),
        ];
    }

    /** Valida e cria dentro do tenant fornecido pelo controlador autenticado. */
    public function criar(int $churchId, array $input, Request $request): array
    {
        $validation = $this->validar($input);
        if (!$validation['valido']) {
            return ['valido' => false, 'dados' => $validation['dados'], 'erros' => $validation['erros']];
        }

        $data = $validation['dados'];
        $memberId = $this->members->criar($churchId, $data);
        $this->audit->registar(
            'criar',
            'members',
            'Foi criado o membro ' . $data['nome_completo'] . '.',
            $request,
            null,
            null,
            $churchId,
            null,
            $this->snapshot($data, $memberId)
        );
        return ['valido' => true, 'id' => $memberId, 'dados' => $data, 'erros' => []];
    }

    /** Valida e actualiza apenas um membro que pertença à igreja activa. */
    public function actualizar(int $churchId, int $memberId, array $input, Request $request): array
    {
        $existing = $this->members->encontrar($churchId, $memberId);
        if ($existing === null) {
            return ['encontrado' => false, 'valido' => false, 'erros' => []];
        }
        $validation = $this->validar($input);
        if (!$validation['valido']) {
            return [
                'encontrado' => true,
                'valido' => false,
                'dados' => $validation['dados'],
                'erros' => $validation['erros'],
                'membro' => $existing,
            ];
        }

        $data = $validation['dados'];
        if (!$this->members->actualizar($churchId, $memberId, $data)) {
            return ['encontrado' => false, 'valido' => false, 'erros' => []];
        }
        $after = $data;
        $after['ativo'] = (int) ($existing['ativo'] ?? 1);
        $this->audit->registar(
            'editar',
            'members',
            'Foi actualizado o membro #' . $memberId . '.',
            $request,
            null,
            null,
            $churchId,
            $this->snapshot($existing, $memberId),
            $this->snapshot($after, $memberId)
        );
        return ['encontrado' => true, 'valido' => true, 'dados' => $data, 'erros' => []];
    }

    public function alternarEstado(int $churchId, int $memberId, Request $request): ?array
    {
        $member = $this->members->alternarEstado($churchId, $memberId);
        if ($member === null) {
            return null;
        }
        $action = (int) $member['ativo'] === 1 ? 'activar' : 'desactivar';
        $this->audit->registar(
            $action,
            'members',
            'Foi ' . ($action === 'activar' ? 'activado' : 'desactivado') . ' o membro #' . $memberId . '.',
            $request,
            null,
            null,
            $churchId,
            ['id' => $memberId, 'ativo' => 1 - (int) $member['ativo']],
            ['id' => $memberId, 'ativo' => (int) $member['ativo']]
        );
        return $member;
    }

    public function remover(int $churchId, int $memberId, Request $request): ?array
    {
        $result = $this->members->removerOuDesactivar($churchId, $memberId);
        if ($result === null) {
            return null;
        }
        $member = $result['membro'];
        $action = $result['removido'] ? 'excluir' : 'desactivar';
        $this->audit->registar(
            $action,
            'members',
            $result['removido']
                ? 'Foi removido o membro sem histórico financeiro #' . $memberId . '.'
                : 'O membro #' . $memberId . ' foi desactivado para preservar o histórico financeiro.',
            $request,
            null,
            null,
            $churchId,
            $this->snapshot($member, $memberId),
            $result['removido'] ? null : ['id' => $memberId, 'ativo' => 0]
        );
        return $result;
    }

    private function snapshot(array $member, int $memberId): array
    {
        return [
            'id' => $memberId,
            'nome_completo' => (string) ($member['nome_completo'] ?? ''),
            'categoria' => (string) ($member['categoria'] ?? ''),
            'ativo' => (int) ($member['ativo'] ?? 1),
        ];
    }

    private function trimValue($value)
    {
        return is_string($value) ? trim($value) : $value;
    }

    private function displayString($value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function nullableString($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $normalised = trim((string) $value);
        return $normalised === '' ? null : $normalised;
    }

    private function stringValue($value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }
}
