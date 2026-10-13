<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Repositories\ConfiguracaoRepository;
use DateTimeImmutable;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Validação da configuração por igreja, incluindo âncoras manuais dos ciclos. */
final class ConfiguracaoService
{
    private $repository;
    private $audit;

    public function __construct(?ConfiguracaoRepository $repository = null, ?AuditoriaService $audit = null)
    {
        $this->repository = $repository ?: new ConfiguracaoRepository();
        $this->audit = $audit ?: new AuditoriaService();
    }

    public function encontrar(int $churchId): ?array
    {
        return $this->repository->encontrar($churchId);
    }

    public function actualizar(int $churchId, int $actorId, array $input, Request $request): array
    {
        $before = $this->repository->encontrar($churchId);
        if ($before === null) {
            return ['sucesso' => false, 'erro' => 'Não foi encontrada a configuração desta igreja.', 'dados' => $input];
        }
        $data = [
            'nome_exibicao' => $this->text($input['nome_exibicao'] ?? '', 180),
            'subtitulo' => $this->nullableText($input['subtitulo'] ?? '', 255),
            'rodape' => $this->nullableText($input['rodape'] ?? '', 500),
            'versiculo' => $this->nullableText($input['versiculo'] ?? '', 255),
            'valor_cota' => $this->parseMoney($input['valor_cota'] ?? null),
            'periodicidade_cota' => is_string($input['periodicidade_cota'] ?? null) ? $input['periodicidade_cota'] : '',
            'inicio_cotas' => $this->parseDate($input['inicio_cotas'] ?? null),
            'inicio_contribuicoes' => $this->parseDate($input['inicio_contribuicoes'] ?? null),
        ];
        $errors = [];
        if ($data['nome_exibicao'] === '') {
            $errors['nome_exibicao'] = 'O nome apresentado é obrigatório (máximo 180 caracteres).';
        }
        if ($data['subtitulo'] === false) {
            $errors['subtitulo'] = 'O subtítulo não pode exceder 255 caracteres.';
        }
        if ($data['rodape'] === false) {
            $errors['rodape'] = 'O rodapé não pode exceder 500 caracteres.';
        }
        if ($data['versiculo'] === false) {
            $errors['versiculo'] = 'O texto não pode exceder 255 caracteres.';
        }
        if ($data['valor_cota'] === null || $data['valor_cota'] < 0 || $data['valor_cota'] > 99999999999.99) {
            $errors['valor_cota'] = 'Indica um valor válido em Kz (zero mantém a cota por configurar).';
        }
        if (!in_array($data['periodicidade_cota'], ['mensal', 'anual'], true)) {
            $errors['periodicidade_cota'] = 'Selecciona uma periodicidade válida.';
        }
        foreach (['inicio_cotas', 'inicio_contribuicoes'] as $field) {
            if ($data[$field] === false) {
                $errors[$field] = 'A data deve usar o formato dd/mm/aaaa.';
            }
        }
        if ($errors !== []) {
            return ['sucesso' => false, 'erros' => $errors, 'dados' => array_merge($before, $input)];
        }

        $quotaChanged = round((float) $data['valor_cota'], 2) !== round((float) ($before['valor_cota'] ?? 0), 2)
            || $data['periodicidade_cota'] !== (string) ($before['periodicidade_cota'] ?? '')
            || $data['inicio_cotas'] !== ($before['inicio_cotas'] ?? null);
        if ($quotaChanged && $this->repository->existemCotas($churchId)) {
            $message = 'Valor, periodicidade e início das cotas não podem ser alterados depois do primeiro pagamento; solicita uma revisão financeira segura.';
            foreach (['valor_cota', 'periodicidade_cota', 'inicio_cotas'] as $field) {
                if (($field === 'valor_cota' && round((float) $data[$field], 2) !== round((float) ($before[$field] ?? 0), 2))
                    || ($field === 'periodicidade_cota' && $data[$field] !== (string) ($before[$field] ?? ''))
                    || ($field === 'inicio_cotas' && $data[$field] !== ($before[$field] ?? null))
                ) {
                    $errors[$field] = $message;
                }
            }
        }
        if ($data['inicio_contribuicoes'] !== ($before['inicio_contribuicoes'] ?? null)
            && $this->repository->existemContribuicoesFimAno($churchId)
        ) {
            $errors['inicio_contribuicoes'] = 'O início das contribuições não pode ser alterado depois do primeiro pagamento; solicita uma revisão financeira segura.';
        }
        if ($errors !== []) {
            return ['sucesso' => false, 'erros' => $errors, 'dados' => array_merge($before, $input)];
        }

        $after = $data;
        $after['moeda'] = $before['moeda'] ?? 'AOA';
        try {
            Database::transaction(function (PDO $pdo) use ($churchId, $actorId, $data, $before, $after, $request): void {
                if (!$this->repository->actualizar($churchId, $data)) {
                    throw new \RuntimeException('A configuração deixou de existir.');
                }
                $this->audit->registar(
                    'editar', 'settings', 'Actualizou as configurações da igreja e os inícios manuais dos módulos.',
                    $request, $actorId, null, $churchId, $before, $after, $pdo
                );
            });
        } catch (\Throwable $exception) {
            return ['sucesso' => false, 'erro' => 'Não foi possível guardar a configuração.', 'dados' => array_merge($before, $input)];
        }
        return ['sucesso' => true, 'dados' => $after];
    }

    private function text($value, int $max): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        return $length <= $max ? $value : '';
    }

    private function nullableText($value, int $max)
    {
        if (!is_string($value)) {
            return false;
        }
        $value = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length > $max) {
            return false;
        }
        return $value === '' ? null : $value;
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

    private function parseDate($value)
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date instanceof DateTimeImmutable
            || ($errors !== false && ((int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            return false;
        }
        return $value;
    }
}
