<?php
namespace App\Core;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Validação de entrada no servidor; a apresentação continua a escapar com e(). */
final class Validator
{
    /** @var array<string,mixed> */
    private $data;

    /** @var array<string,array<int,string>> */
    private $errors = [];

    /** @var array<string,mixed> */
    private $validated = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Valida regras simples como ['email' => 'required|email|max:191'].
     * Regras suportadas: required, nullable, string, email, integer, numeric,
     * boolean, date (AAAA-MM-DD), min:n, max:n e in:a,b.
     */
    public function validate(array $rules): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($rules as $field => $fieldRules) {
            $field = (string) $field;
            $ruleList = is_array($fieldRules)
                ? $fieldRules
                : explode('|', (string) $fieldRules);
            $ruleList = array_map('trim', $ruleList);
            $exists = array_key_exists($field, $this->data);
            $value = $exists ? $this->data[$field] : null;
            $empty = !$exists || $value === null || (is_string($value) && trim($value) === '');
            $required = in_array('required', $ruleList, true);
            $nullable = in_array('nullable', $ruleList, true);

            if ($empty) {
                if ($required) {
                    $this->addError($field, 'O campo é obrigatório.');
                }
                if (!$required || $nullable) {
                    continue;
                }
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            $numericMode = in_array('numeric', $ruleList, true) || in_array('integer', $ruleList, true);

            foreach ($ruleList as $ruleExpression) {
                if ($ruleExpression === '' || $ruleExpression === 'required' || $ruleExpression === 'nullable') {
                    continue;
                }

                $parts = explode(':', $ruleExpression, 2);
                $rule = strtolower(trim($parts[0]));
                $argument = isset($parts[1]) ? trim($parts[1]) : '';
                $message = $this->failureMessage($rule, $argument);

                if (!$this->ruleAllows($rule, $argument, $value, $numericMode)) {
                    $this->addError($field, $message);
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /** Devolve o primeiro erro de cada campo, pronto para apresentar num formulário. */
    public function firstErrors(): array
    {
        $first = [];
        foreach ($this->errors as $field => $messages) {
            if (isset($messages[0])) {
                $first[$field] = $messages[0];
            }
        }
        return $first;
    }

    /** Devolve apenas os valores que passaram todas as regras. */
    public function validated(): array
    {
        return $this->validated;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    private function ruleAllows(string $rule, string $argument, $value, bool $numericMode): bool
    {
        switch ($rule) {
            case 'string':
                return is_string($value) || is_numeric($value);

            case 'email':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false;

            case 'numeric':
                return is_numeric($value);

            case 'boolean':
                return in_array($value, [true, false, 0, 1, '0', '1'], true);

            case 'date':
                return $this->isIsoDate($value);

            case 'min':
                return $this->compareBound($value, $argument, true, $numericMode);

            case 'max':
                return $this->compareBound($value, $argument, false, $numericMode);

            case 'in':
                return in_array((string) $value, array_map('trim', explode(',', $argument)), true);

            default:
                throw new \InvalidArgumentException('Regra de validação desconhecida: ' . $rule);
        }
    }

    private function compareBound($value, string $argument, bool $minimum, bool $numericMode): bool
    {
        if (!is_numeric($argument)) {
            throw new \InvalidArgumentException('O limite da validação tem de ser numérico.');
        }

        $bound = (float) $argument;
        if ($numericMode) {
            if (!is_numeric($value)) {
                return false;
            }
            return $minimum ? (float) $value >= $bound : (float) $value <= $bound;
        }

        if (!is_string($value)) {
            return false;
        }
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        return $minimum ? $length >= $bound : $length <= $bound;
    }

    private function isIsoDate($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $date = \DateTime::createFromFormat('!Y-m-d', $value);
        $errors = \DateTime::getLastErrors();
        return $date instanceof \DateTime
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }

    private function failureMessage(string $rule, string $argument): string
    {
        switch ($rule) {
            case 'string':
                return 'O valor tem de ser texto.';
            case 'email':
                return 'Indica um endereço de email válido.';
            case 'integer':
                return 'Indica um número inteiro válido.';
            case 'numeric':
                return 'Indica um valor numérico válido.';
            case 'boolean':
                return 'Escolhe uma opção válida.';
            case 'date':
                return 'Indica uma data válida no formato aaaa-mm-dd.';
            case 'min':
                return 'O valor tem de ter pelo menos ' . $argument . '.';
            case 'max':
                return 'O valor não pode ultrapassar ' . $argument . '.';
            case 'in':
                return 'O valor escolhido não é permitido.';
            default:
                return 'O valor indicado não é válido.';
        }
    }

    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
}
