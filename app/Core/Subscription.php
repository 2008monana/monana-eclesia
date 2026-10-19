<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Regras de negócio da assinatura.
 *
 * Status possíveis:
 *   trial, activa, expirando, expirada, em_tolerancia, suspensa, cancelada
 */
final class Subscription
{
    private static ?array $cache = null;
    private static ?int $cacheIgrejaId = null;

    public static function current(?int $igrejaId = null): ?array
    {
        $igrejaId = $igrejaId ?? Tenant::id();
        if (!$igrejaId) {
            return null;
        }
        if (self::$cache !== null && self::$cacheIgrejaId === $igrejaId) {
            return self::$cache;
        }

        $row = Database::first(
            'SELECT s.*, p.nome AS plano_nome, p.slug AS plano_slug,
                    p.preco_kz, p.periodicidade_dias,
                    p.limite_membros, p.limite_users, p.limite_storage_mb
             FROM `subscriptions` s
             JOIN `planos` p ON p.id = s.plano_id
             WHERE s.igreja_id = :i
             ORDER BY s.id DESC
             LIMIT 1',
            ['i' => $igrejaId]
        );

        self::$cache = $row;
        self::$cacheIgrejaId = $igrejaId;
        return $row;
    }

    /**
     * Status REAL calculado pelas datas (não confia só no campo `status`).
     */
    public static function realStatus(): string
    {
        $sub = self::current();
        if (!$sub) {
            return 'inexistente';
        }

        $agora = time();
        $expiracao = $sub['expiracao'] ? strtotime((string) $sub['expiracao']) : null;
        $toleranciaHoras = (int) (Settings::get('assinatura_tolerancia_horas', 24) ?? 24);
        $avisoHoras      = (int) (Settings::get('assinatura_aviso_horas', 48) ?? 48);

        if ($sub['status'] === 'suspensa' || $sub['status'] === 'cancelada') {
            return $sub['status'];
        }

        if ($expiracao === null) {
            return $sub['status'];
        }

        if ($agora > $expiracao) {
            $horasDecorridas = ($agora - $expiracao) / 3600;
            if ($horasDecorridas <= $toleranciaHoras) {
                return 'em_tolerancia';
            }
            return 'expirada';
        }

        $horasRestantes = ($expiracao - $agora) / 3600;
        if ($horasRestantes <= $avisoHoras) {
            return 'expirando';
        }

        return $sub['status'];
    }

    public static function canWrite(): bool
    {
        return in_array(self::realStatus(), ['trial', 'activa', 'expirando', 'em_tolerancia'], true);
    }

    public static function bannerInfo(): ?array
    {
        $status = self::realStatus();
        $sub = self::current();
        if (!$sub || $status === 'activa' || $status === 'trial') {
            return null;
        }

        $expiracao = $sub['expiracao'] ? strtotime((string) $sub['expiracao']) : null;
        $diasRestantes = $expiracao ? (int) ceil(($expiracao - time()) / 86400) : null;

        return match ($status) {
            'expirando' => [
                'tipo'    => 'warning',
                'icone'   => 'fa-hourglass-half',
                'titulo'  => 'Assinatura a expirar',
                'msg'     => "A sua assinatura expira em {$diasRestantes} dia(s). Renove para continuar sem interrupções.",
                'link'    => 'assinatura',
                'linktxt' => 'Renovar agora',
            ],
            'em_tolerancia' => [
                'tipo'    => 'warning',
                'icone'   => 'fa-triangle-exclamation',
                'titulo'  => 'Assinatura expirada',
                'msg'     => 'A sua assinatura expirou. Está no período de tolerância — renove para evitar o bloqueio.',
                'link'    => 'assinatura',
                'linktxt' => 'Renovar agora',
            ],
            'expirada' => [
                'tipo'    => 'danger',
                'icone'   => 'fa-ban',
                'titulo'  => 'Assinatura expirada — modo leitura',
                'msg'     => 'Não é possível criar ou editar registos. Renove a assinatura para retomar o acesso completo.',
                'link'    => 'assinatura',
                'linktxt' => 'Renovar',
            ],
            'suspensa' => [
                'tipo'    => 'danger',
                'icone'   => 'fa-circle-pause',
                'titulo'  => 'Assinatura suspensa',
                'msg'     => 'A sua assinatura está suspensa. Contacte o suporte da plataforma.',
                'link'    => 'assinatura',
                'linktxt' => 'Ver detalhes',
            ],
            'cancelada' => [
                'tipo'    => 'danger',
                'icone'   => 'fa-circle-xmark',
                'titulo'  => 'Assinatura cancelada',
                'msg'     => 'A sua assinatura foi cancelada. Contacte o suporte para reativar.',
                'link'    => 'assinatura',
                'linktxt' => 'Ver detalhes',
            ],
            default => null,
        };
    }

    public static function clearCache(): void
    {
        self::$cache = null;
        self::$cacheIgrejaId = null;
    }
}