<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $errors = [];
    private array $data   = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, string $label = ''): self
    {
        if (trim((string) ($this->data[$field] ?? '')) === '') {
            $this->errors[$field] = ($label ?: $field) . ' é obrigatório.';
        }
        return $this;
    }

    public function email(string $field, string $label = ''): self
    {
        $v = trim((string) ($this->data[$field] ?? ''));
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = ($label ?: $field) . ' não é um e-mail válido.';
        }
        return $this;
    }

    public function minLen(string $field, int $min, string $label = ''): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v !== '' && mb_strlen($v) < $min) {
            $this->errors[$field] = ($label ?: $field) . " deve ter pelo menos {$min} caracteres.";
        }
        return $this;
    }

    public function maxLen(string $field, int $max, string $label = ''): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if (mb_strlen($v) > $max) {
            $this->errors[$field] = ($label ?: $field) . " deve ter no máximo {$max} caracteres.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label = ''): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v !== '' && !in_array($v, $allowed, true)) {
            $this->errors[$field] = ($label ?: $field) . ' tem um valor inválido.';
        }
        return $this;
    }

    public function date(string $field, string $label = ''): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v !== '' && !strtotime($v)) {
            $this->errors[$field] = ($label ?: $field) . ' deve ser uma data válida (AAAA-MM-DD).';
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
}