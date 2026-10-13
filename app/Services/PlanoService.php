<?php
namespace App\Services;

use App\Core\Request;
use App\Core\Validator;
use App\Repositories\PlanoRepository;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Validação e casos de uso do catálogo de planos, sem preços pré-inventados. */
final class PlanoService
{
    /** @var PlanoRepository */
    private $plans;
    /** @var AuditoriaService */
    private $audit;

    public function __construct(?PlanoRepository $plans = null, ?AuditoriaService $audit = null)
    {
        $this->plans = $plans ?: new PlanoRepository();
        $this->audit = $audit ?: new AuditoriaService();
    }

    public function all(): array
    {
        return $this->plans->all();
    }

    public function find(int $id): ?array
    {
        return $this->plans->find($id);
    }

    public function create(array $input, Request $request, int $actorId, string $actorName): int
    {
        $values = $this->validated($input);
        $id = $this->plans->create($values);
        $this->audit->registar(
            'criar_plano',
            'superadmin',
            'Plano comercial criado ou configurado.',
            $request,
            $actorId,
            $actorName,
            null,
            null,
            ['plano_id' => $id, 'nome' => $values['nome'], 'preco_kz' => $values['preco_kz']]
        );
        return $id;
    }

    public function update(int $id, array $input, Request $request, int $actorId, string $actorName): bool
    {
        $before = $this->plans->find($id);
        if ($before === null) {
            return false;
        }
        $values = $this->validated($input);
        $this->plans->update($id, $values);
        $this->audit->registar(
            'editar_plano',
            'superadmin',
            'Plano actualizado pelo Superadmin.',
            $request,
            $actorId,
            $actorName,
            null,
            ['nome' => $before['nome'], 'preco_kz' => $before['preco_kz'], 'ativo' => $before['ativo']],
            ['nome' => $values['nome'], 'preco_kz' => $values['preco_kz'], 'ativo' => $values['ativo']]
        );
        return true;
    }

    public function delete(int $id, Request $request, int $actorId, string $actorName): bool
    {
        $before = $this->plans->find($id);
        if ($before === null) {
            return false;
        }
        if ((int) ($before['igrejas_associadas'] ?? 0) > 0) {
            throw new \DomainException('Este plano está associado a igrejas. Desactiva-o em vez de o apagar.');
        }
        if (!$this->plans->delete($id)) {
            return false;
        }
        $this->audit->registar(
            'apagar_plano',
            'superadmin',
            'Plano sem igrejas associadas apagado.',
            $request,
            $actorId,
            $actorName,
            null,
            ['plano_id' => $id, 'nome' => $before['nome']],
            null
        );
        return true;
    }

    private function validated(array $input): array
    {
        $price = $this->parseMoney($input['preco_kz'] ?? '');
        if ($price === null) {
            throw new \InvalidArgumentException('Indica um preço válido, por exemplo 1.250,00.');
        }
        $data = [
            'nome' => trim((string) ($input['nome'] ?? '')),
            'limite_membros' => $input['limite_membros'] ?? '',
            'limite_utilizadores' => $input['limite_utilizadores'] ?? '',
            'preco_kz' => $price,
            'ativo' => isset($input['ativo']) && (string) $input['ativo'] !== '0' ? '1' : '0',
        ];
        $validator = new Validator($data);
        $valid = $validator->validate([
            'nome' => 'required|string|max:100',
            'limite_membros' => 'required|integer|min:0|max:2147483647',
            'limite_utilizadores' => 'required|integer|min:0|max:2147483647',
            'preco_kz' => 'required|numeric|min:0|max:99999999999.99',
            'ativo' => 'required|in:0,1',
        ]);
        if (!$valid) {
            $errors = $validator->firstErrors();
            throw new \InvalidArgumentException((string) reset($errors));
        }

        return [
            'nome' => $data['nome'],
            'limite_membros' => (int) $data['limite_membros'],
            'limite_utilizadores' => (int) $data['limite_utilizadores'],
            'preco_kz' => $price,
            'ativo' => (int) $data['ativo'],
        ];
    }

    /** Aceita 1250.00, 1.250,00 e 1 250,00 sem perder a precisão decimal. */
    private function parseMoney($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $raw = trim(str_ireplace(['Kz', ' '], '', (string) $value));
        if ($raw === '') {
            return null;
        }
        if (strpos($raw, ',') !== false) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (substr_count($raw, '.') > 1) {
            $parts = explode('.', $raw);
            $decimal = array_pop($parts);
            $raw = implode('', $parts) . '.' . $decimal;
        }
        if (!preg_match('/^\d{1,11}(?:\.\d{1,2})?$/', $raw)) {
            return null;
        }
        return $raw;
    }
}
