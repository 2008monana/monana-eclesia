<?php
namespace App\Services;

use App\Core\Request;
use App\Core\Validator;
use App\Repositories\SaidaRepository;
use DateTimeImmutable;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Validação e regras de saídas, mantendo os dois fundos independentes. */
final class SaidaService
{
    /** @var SaidaRepository */
    private $repository;
    /** @var AuditoriaService */
    private $audit;

    public function __construct(?SaidaRepository $repository = null, ?AuditoriaService $audit = null)
    {
        $this->repository = $repository ?: new SaidaRepository();
        $this->audit = $audit ?: new AuditoriaService();
    }

    public function listar(int $churchId): array
    {
        $expenses = $this->repository->listar($churchId);
        $income = $this->repository->totaisEntradas($churchId);
        $spent = $this->repository->totaisDespesas($churchId);
        return [
            'saidas' => $expenses,
            'saldos' => [
                'cotas' => round((float) $income['cotas'] - (float) $spent['cotas'], 2),
                'contribuicoes' => round((float) $income['contribuicoes'] - (float) $spent['contribuicoes'], 2),
            ],
            'totais' => $spent,
        ];
    }

    public function encontrar(int $churchId, int $expenseId): ?array
    {
        return $churchId > 0 && $expenseId > 0 ? $this->repository->encontrar($churchId, $expenseId) : null;
    }

    public function validar(array $input): array
    {
        $data = [
            'descricao' => $this->text($input['descricao'] ?? ''),
            'valor' => $this->parseMoney($input['valor'] ?? ''),
            'data_saida' => $this->text($input['data_saida'] ?? ''),
            'categoria' => $this->text($input['categoria'] ?? ''),
            'origem_fundo' => $this->text($input['origem_fundo'] ?? ''),
            'beneficiario' => $this->text($input['beneficiario'] ?? ''),
        ];
        $validator = new Validator($data);
        $valid = $validator->validate([
            'descricao' => 'required|string|min:2|max:255',
            'valor' => 'required|numeric|min:0.01|max:99999999999.99',
            'data_saida' => 'required|date',
            'categoria' => 'required|string|min:2|max:120',
            'origem_fundo' => 'required|string|in:cotas,contribuicoes',
            'beneficiario' => 'nullable|string|max:180',
        ]);
        $clean = $validator->validated();
        $display = [
            'descricao' => $this->text($input['descricao'] ?? ''),
            'valor' => $this->text($input['valor'] ?? ''),
            'data_saida' => $this->text($input['data_saida'] ?? ''),
            'categoria' => $this->text($input['categoria'] ?? ''),
            'origem_fundo' => $this->text($input['origem_fundo'] ?? ''),
            'beneficiario' => $this->text($input['beneficiario'] ?? ''),
        ];
        if ($valid) {
            $display = [
                'descricao' => (string) $clean['descricao'],
                'valor' => round((float) $clean['valor'], 2),
                'data_saida' => (string) $clean['data_saida'],
                'categoria' => (string) $clean['categoria'],
                'origem_fundo' => (string) $clean['origem_fundo'],
                'beneficiario' => $this->nullable($clean['beneficiario'] ?? null),
            ];
        }
        return ['valido' => $valid, 'dados' => $display, 'erros' => $validator->firstErrors()];
    }

    public function criar(int $churchId, int $actorId, array $input, Request $request): array
    {
        $validation = $this->validar($input);
        if (!$validation['valido']) {
            return ['valido' => false, 'dados' => $validation['dados'], 'erros' => $validation['erros']];
        }
        $data = $validation['dados'];
        $id = $this->repository->criar($churchId, $actorId, $data);
        $this->audit->registar('criar', 'expenses', 'Registou uma saída de ' . $this->kz($data['valor']) . ' Kz do fundo ' . $data['origem_fundo'] . '.', $request, null, null, $churchId, null, ['id' => $id, 'valor' => $data['valor'], 'origem_fundo' => $data['origem_fundo']]);
        return ['valido' => true, 'id' => $id];
    }

    public function actualizar(int $churchId, int $expenseId, array $input, Request $request): array
    {
        $before = $this->repository->encontrar($churchId, $expenseId);
        if ($before === null) {
            return ['encontrado' => false, 'valido' => false, 'erros' => []];
        }
        $validation = $this->validar($input);
        if (!$validation['valido']) {
            return ['encontrado' => true, 'valido' => false, 'dados' => $validation['dados'], 'erros' => $validation['erros']];
        }
        $data = $validation['dados'];
        $this->repository->actualizar($churchId, $expenseId, $data);
        $this->audit->registar('editar', 'expenses', 'Actualizou a saída #' . $expenseId . '.', $request, null, null, $churchId, $before, $data);
        return ['encontrado' => true, 'valido' => true];
    }

    public function apagar(int $churchId, int $expenseId, Request $request): bool
    {
        $before = $this->repository->encontrar($churchId, $expenseId);
        if ($before === null || !$this->repository->apagar($churchId, $expenseId)) {
            return false;
        }
        $this->audit->registar('excluir', 'expenses', 'Removeu a saída #' . $expenseId . '.', $request, null, null, $churchId, $before, null);
        return true;
    }

    private function text($value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function parseMoney($value): ?float
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $value = trim(str_replace(["\xc2\xa0", ' ', 'Kz', 'kz'], '', (string) $value));
        if ($value === '') {
            return null;
        }
        if (strpos($value, ',') !== false) {
            if (!preg_match('/^\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?$|^\d+(?:,\d{1,2})?$/', $value)) {
                return null;
            }
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value);
        } elseif (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            return null;
        }
        return is_finite((float) $value) ? round((float) $value, 2) : null;
    }

    private function nullable($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function kz(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
