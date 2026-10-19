<?php

declare(strict_types=1);

namespace App\Core;

final class Audit
{
    /**
     * Registra uma ação em audit_logs.
     *
     * @param string      $modulo  Ex: 'membros'
     * @param string      $acao    Ex: 'criar', 'editar', 'eliminar'
     * @param string|null $entidade Ex: 'members'
     * @param string|null $entidadeId
     * @param array|null  $dadosAntes
     * @param array|null  $dadosDepois
     */
    public static function log(
        string $modulo,
        string $acao,
        ?string $entidade = null,
        ?string $entidadeId = null,
        ?array $dadosAntes = null,
        ?array $dadosDepois = null
    ): void {
        try {
            $user       = Auth::user();
            $supervisor = Auth::supervisor();

            Database::execute(
                'INSERT INTO `audit_logs`
                    (`user_id`, `supervisor_id`, `igreja_id`, `modulo`, `acao`,
                     `entidade`, `entidade_id`, `dados_antes`, `dados_depois`,
                     `ip`, `user_agent`)
                 VALUES
                    (:user_id, :supervisor_id, :igreja_id, :modulo, :acao,
                     :entidade, :entidade_id, :dados_antes, :dados_depois,
                     :ip, :user_agent)',
                [
                    'user_id'       => $user['id'] ?? null,
                    'supervisor_id' => $supervisor['id'] ?? null,
                    'igreja_id'     => Tenant::id(),
                    'modulo'        => $modulo,
                    'acao'          => $acao,
                    'entidade'      => $entidade,
                    'entidade_id'   => $entidadeId,
                    'dados_antes'   => $dadosAntes ? json_encode($dadosAntes, JSON_UNESCAPED_UNICODE) : null,
                    'dados_depois'  => $dadosDepois ? json_encode($dadosDepois, JSON_UNESCAPED_UNICODE) : null,
                    'ip'            => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent'    => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]
            );
        } catch (\Throwable $e) {
            logger('warning', 'Falha ao gravar audit_log', ['msg' => $e->getMessage()]);
        }
    }
}