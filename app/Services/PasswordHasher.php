<?php
namespace App\Services;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Hash de senha Argon2id quando disponível e bcrypt como fallback PHP 7.4. */
final class PasswordHasher
{
    public static function algorithm()
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function hash(string $password): string
    {
        $hash = password_hash($password, self::algorithm());
        if (!is_string($hash)) {
            throw new \RuntimeException('Não foi possível proteger a senha.');
        }
        return $hash;
    }

    public static function verify(string $password, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }
        $information = password_get_info($hash);
        if (($information['algo'] ?? null) === PASSWORD_BCRYPT && strlen($password) > 72) {
            return false;
        }
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return $hash === '' || password_needs_rehash($hash, self::algorithm());
    }

    public static function maximumLength(): int
    {
        // bcrypt ignora bytes além dos primeiros 72; não aceitamos truncamento silencioso.
        return self::algorithm() === PASSWORD_BCRYPT ? 72 : 200;
    }

    public static function requirementsDescription(): string
    {
        return self::algorithm() === PASSWORD_BCRYPT
            ? 'pelo menos 12 caracteres e no máximo 72 bytes neste servidor'
            : 'entre 12 e 200 caracteres';
    }

    public static function isAcceptable(string $password): bool
    {
        $characters = self::length($password);
        if ($characters < 12) {
            return false;
        }
        if (self::algorithm() === PASSWORD_BCRYPT) {
            return strlen($password) <= 72;
        }
        return $characters <= 200;
    }

    /** Conta pontos de código UTF‑8 mesmo quando mbstring não está instalada. */
    private static function length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        $characters = preg_match_all('/./us', $value);
        return $characters === false ? 0 : $characters;
    }
}
