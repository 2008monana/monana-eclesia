<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Resolve a igreja (tenant) da sessão atual.
 *
 * Na F1, apenas expõe o "slot". A partir da F2, o TenantMiddleware
 * preenche `Tenant::setFromUser($user)` após o login.
 */
final class Tenant
{
    private static ?int $igrejaId = null;

    /** Define a igreja ativa (chamado no login / middleware) */
    public static function set(?int $igrejaId): void
    {
        self::$igrejaId = $igrejaId;
        if ($igrejaId !== null) {
            $_SESSION['igreja_id'] = $igrejaId;
        } else {
            unset($_SESSION['igreja_id']);
        }
    }

    /** Retorna o id da igreja ativa (ou null) */
    public static function id(): ?int
    {
        if (self::$igrejaId !== null) {
            return self::$igrejaId;
        }
        if (isset($_SESSION['igreja_id'])) {
            return (int) $_SESSION['igreja_id'];
        }
        return null;
    }

    /** Há igreja ativa? */
    public static function has(): bool
    {
        return self::id() !== null;
    }
}