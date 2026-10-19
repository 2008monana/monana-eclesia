<?php

declare(strict_types=1);

namespace App\Core;

final class Settings
{
    private static array $cache = [];

    /**
     * Lê uma setting da igreja atual (ou global se $igrejaId = null).
     */
    public static function get(string $chave, mixed $default = null, ?int $igrejaId = null): mixed
    {
        $igrejaId = $igrejaId ?? Tenant::id();
        $cacheKey = ($igrejaId ?? 'global') . ':' . $chave;

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $row = Database::first(
            'SELECT `valor`, `tipo` FROM `settings`
             WHERE `chave` = :c
               AND ((:i IS NULL AND `igreja_id` IS NULL) OR `igreja_id` = :i2)
             LIMIT 1',
            ['c' => $chave, 'i' => $igrejaId, 'i2' => $igrejaId]
        );

        if (!$row) {
            return self::$cache[$cacheKey] = $default;
        }

        $valor = self::castValue($row['valor'], $row['tipo']);
        return self::$cache[$cacheKey] = $valor;
    }

    /**
     * Define/atualiza uma setting para a igreja atual (ou global).
     */
    public static function set(string $chave, mixed $valor, string $tipo = 'texto', ?int $igrejaId = null): void
    {
        $igrejaId = $igrejaId ?? Tenant::id();

        // Verifica se existe
        $existe = Database::first(
            'SELECT id FROM `settings`
             WHERE `chave` = :c
               AND ((:i IS NULL AND `igreja_id` IS NULL) OR `igreja_id` = :i2)
             LIMIT 1',
            ['c' => $chave, 'i' => $igrejaId, 'i2' => $igrejaId]
        );

        $valorStr = self::stringifyValue($valor, $tipo);

        if ($existe) {
            Database::execute(
                'UPDATE `settings` SET `valor` = :v, `tipo` = :t, `actualizado_em` = NOW() WHERE `id` = :id',
                ['v' => $valorStr, 't' => $tipo, 'id' => $existe['id']]
            );
        } else {
            Database::execute(
                'INSERT INTO `settings` (`igreja_id`, `chave`, `valor`, `tipo`) VALUES (:i, :c, :v, :t)',
                ['i' => $igrejaId, 'c' => $chave, 'v' => $valorStr, 't' => $tipo]
            );
        }

        unset(self::$cache[($igrejaId ?? 'global') . ':' . $chave]);
    }

    /**
     * Lê todas as settings da igreja atual (com fallback para globais).
     */
    public static function allForTenant(): array
    {
        $igrejaId = Tenant::id();

        $rows = Database::select(
            'SELECT `chave`, `valor`, `tipo` FROM `settings`
             WHERE (:i IS NULL AND `igreja_id` IS NULL) OR `igreja_id` = :i2',
            ['i' => $igrejaId, 'i2' => $igrejaId]
        );

        $result = [];
        foreach ($rows as $r) {
            $result[$r['chave']] = self::castValue($r['valor'], $r['tipo']);
        }
        return $result;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    private static function castValue(?string $valor, string $tipo): mixed
    {
        if ($valor === null) {
            return null;
        }
        return match ($tipo) {
            'numero'   => is_numeric($valor) ? (float) $valor : $valor,
            'booleano' => in_array(strtolower($valor), ['1', 'true', 'sim'], true),
            'json'     => json_decode($valor, true) ?? $valor,
            default    => $valor,
        };
    }

    private static function stringifyValue(mixed $valor, string $tipo): string
    {
        return match ($tipo) {
            'booleano' => $valor ? '1' : '0',
            'json'     => json_encode($valor, JSON_UNESCAPED_UNICODE),
            default    => (string) $valor,
        };
    }
}