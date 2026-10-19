<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Regras e envio de notificações de assinatura.
 *
 * Tipos possíveis (enum da BD):
 *   - 48h_antes
 *   - expirada
 *   - tolerancia
 *   - suspensa
 *   - reativada
 */
final class Notifications
{
    /**
     * Processa TODAS as igrejas e envia notificações quando aplicável.
     * Retorna um array com o resumo do que foi feito.
     */
    public static function processarTodas(): array
    {
        $resumo = [
            'enviadas'   => 0,
            'ignoradas'  => 0,
            'erros'      => 0,
            'detalhes'   => [],
        ];

        $igrejas = Database::select(
            'SELECT i.id, i.nome, i.email, i.slug
             FROM `igrejas` i
             WHERE i.deletado_em IS NULL
               AND i.status IN ("em_teste", "activa")
             ORDER BY i.id'
        );

        foreach ($igrejas as $igreja) {
            try {
                $resultado = self::processarIgreja((int) $igreja['id']);
                if ($resultado['enviado']) {
                    $resumo['enviadas']++;
                    $resumo['detalhes'][] = [
                        'igreja' => $igreja['nome'],
                        'tipo'   => $resultado['tipo'],
                    ];
                } else {
                    $resumo['ignoradas']++;
                }
            } catch (\Throwable $e) {
                $resumo['erros']++;
                logger('error', 'Notificação: falha ao processar igreja', [
                    'igreja_id' => $igreja['id'],
                    'msg'       => $e->getMessage(),
                ]);
            }
        }

        // Limpeza de backups expirados
        self::limparBackupsExpirados();

        return $resumo;
    }

    /**
     * Processa uma igreja específica.
     */
    public static function processarIgreja(int $igrejaId): array
    {
        $sub = Subscription::current($igrejaId);
        if (!$sub) {
            return ['enviado' => false, 'motivo' => 'sem assinatura'];
        }

        $status = Subscription::realStatus();
        $expiracao = $sub['expiracao'] ? strtotime((string) $sub['expiracao']) : null;
        if (!$expiracao) {
            return ['enviado' => false, 'motivo' => 'sem data de expiração'];
        }

        $agora = time();
        $horasRestantes = ($expiracao - $agora) / 3600;

        // Descobre o tipo de notificação que se aplica
        $tipo = null;
        if ($status === 'em_tolerancia') {
            $tipo = 'tolerancia';
        } elseif ($status === 'expirada') {
            $tipo = 'expirada';
        } elseif ($status === 'suspensa') {
            $tipo = 'suspensa';
        } elseif ($horasRestantes > 0 && $horasRestantes <= 48) {
            $tipo = '48h_antes';
        }

        if ($tipo === null) {
            return ['enviado' => false, 'motivo' => 'sem notificação aplicável'];
        }

        // Evita reenvio: já foi enviada nos últimos 3 dias?
        $jaEnviada = Database::first(
            'SELECT id FROM `subscription_notifications`
             WHERE igreja_id = :i AND subscription_id = :s AND tipo = :t
               AND enviado_em >= DATE_SUB(NOW(), INTERVAL 3 DAY)
             LIMIT 1',
            ['i' => $igrejaId, 's' => $sub['id'], 't' => $tipo]
        );

        if ($jaEnviada) {
            return ['enviado' => false, 'motivo' => 'já enviada recentemente'];
        }

        // Busca admin da igreja para enviar email
        $admin = Database::first(
            'SELECT u.nome, u.email
             FROM `users` u
             JOIN `user_roles` ur ON ur.user_id = u.id
             JOIN `roles` r ON r.id = ur.role_id
             WHERE u.igreja_id = :i AND u.deletado_em IS NULL AND u.ativo = 1
               AND r.slug = "admin_igreja"
             LIMIT 1',
            ['i' => $igrejaId]
        );

        if (!$admin) {
            return ['enviado' => false, 'motivo' => 'sem admin'];
        }

        $enviado = self::enviarEmail($admin, $sub, $tipo, $igrejaId);

        // Registar tentativa (sucesso ou falha)
        Database::insert(
            'INSERT INTO `subscription_notifications`
                (`igreja_id`, `subscription_id`, `tipo`, `canal`, `status`)
             VALUES (:i, :s, :t, "email", :st)',
            [
                'i'  => $igrejaId,
                's'  => $sub['id'],
                't'  => $tipo,
                'st' => $enviado ? 'enviado' : 'falhou',
            ]
        );

        return ['enviado' => $enviado, 'tipo' => $tipo];
    }

    /**
     * Envia o e-mail conforme o tipo.
     */
    private static function enviarEmail(array $admin, array $sub, string $tipo, int $igrejaId): bool
    {
        $assuntos = [
            '48h_antes'  => 'A sua assinatura expira em 48 horas',
            'tolerancia' => 'A sua assinatura expirou — período de tolerância',
            'expirada'   => 'A sua assinatura expirou — modo leitura',
            'suspensa'   => 'A sua assinatura está suspensa',
        ];

        $mensagens = [
            '48h_antes'  => 'A sua assinatura do plano <strong>' . esc($sub['plano_nome']) . '</strong> expira em <strong>48 horas</strong>. Renove agora para evitar a interrupção do serviço.',
            'tolerancia' => 'A sua assinatura expirou. Está dentro do <strong>período de tolerância</strong> — renove o quanto antes para evitar o bloqueio total.',
            'expirada'   => 'A sua assinatura expirou e já passou o período de tolerância. O sistema está em <strong>modo leitura</strong> — não é possível criar ou editar registos.',
            'suspensa'   => 'A sua assinatura foi <strong>suspensa</strong>. Contacte o suporte da plataforma para regularizar.',
        ];

        $assunto = $assuntos[$tipo] ?? 'Notificação de assinatura';
        $msg = $mensagens[$tipo] ?? 'A sua assinatura precisa de atenção.';

        $link = url('assinatura');

        $body = '
            <div style="font-family:Inter,system-ui,sans-serif;background:#f8f3e7;padding:32px;color:#2e1b0e">
                <div style="max-width:560px;margin:0 auto;background:#fffdf8;border-radius:12px;border:1px solid #ece2cc;overflow:hidden">
                    <div style="background:linear-gradient(180deg,#e3b64a,#b8862b);padding:24px;text-align:center">
                        <h1 style="font-family:Cinzel,serif;color:#fff;margin:0;font-size:22px;letter-spacing:.04em">MONANA ECLESIA</h1>
                    </div>
                    <div style="padding:28px">
                        <h2 style="font-family:Cormorant Garamond,serif;font-size:22px;color:#2e1b0e;margin:0 0 14px">
                            Olá, ' . esc($admin['nome']) . '!
                        </h2>
                        <p style="font-size:14px;line-height:1.6;color:#3a2412;margin:0 0 18px">
                            ' . $msg . '
                        </p>
                        <p style="text-align:center;margin:28px 0">
                            <a href="' . esc($link) . '"
                               style="display:inline-block;background:linear-gradient(180deg,#d9ab45,#b8862b);color:#fff;
                                      padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;
                                      font-size:14px">
                                Ver assinatura
                            </a>
                        </p>
                        <p style="font-size:12px;color:#7c6d58;border-top:1px solid #ece2cc;padding-top:16px;margin:0">
                            Se já renovou, ignore este e-mail.
                        </p>
                    </div>
                    <div style="background:#fbf6ea;padding:16px;text-align:center;font-size:11px;color:#7c6d58">
                        Monana Eclesia · Plataforma Integrada de Gestão de Igrejas
                    </div>
                </div>
            </div>
        ';

        return Mailer::send($admin['email'], $admin['nome'], $assunto, $body);
    }

    /**
     * Atualiza os status das assinaturas com base nas datas.
     */
    public static function atualizarStatus(): array
    {
        $resumo = ['expirando' => 0, 'expirada' => 0, 'suspensa' => 0, 'reativada' => 0];

        $agora = date('Y-m-d H:i:s');
        $toleranciaHoras = (int) (Settings::get('assinatura_tolerancia_horas', 24) ?? 24);

        // Passa para "expirada" as que passaram da tolerância
        $resumo['expirada'] = Database::execute(
            'UPDATE `subscriptions` SET `status` = "expirada"
             WHERE `status` IN ("trial","activa","expirando")
               AND `expiracao` IS NOT NULL
               AND `expiracao` < DATE_SUB(NOW(), INTERVAL :tol HOUR)',
            ['tol' => $toleranciaHoras]
        );

        // Passa para "em_tolerancia" as que acabaram de expirar
        Database::execute(
            'UPDATE `subscriptions` SET `status` = "em_tolerancia"
             WHERE `status` IN ("trial","activa","expirando")
               AND `expiracao` IS NOT NULL
               AND `expiracao` BETWEEN DATE_SUB(NOW(), INTERVAL :tol HOUR) AND NOW()',
            ['tol' => $toleranciaHoras]
        );

        // Passa para "expirando" as que expiram em menos de 48h
        $resumo['expirando'] = Database::execute(
            'UPDATE `subscriptions` SET `status` = "expirando"
             WHERE `status` IN ("trial","activa")
               AND `expiracao` IS NOT NULL
               AND `expiracao` BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 48 HOUR)',
            []
        );

        return $resumo;
    }

    /**
     * Remove backups cujo `expira_em` já passou.
     */
    public static function limparBackupsExpirados(): int
    {
        $expirados = Database::select(
            'SELECT id, ficheiro FROM `backups`
             WHERE `estado` = "concluido"
               AND `expira_em` IS NOT NULL
               AND `expira_em` < NOW()'
        );

        $total = 0;
        foreach ($expirados as $b) {
            $caminho = STORAGE_PATH . '/backups/' . $b['ficheiro'];
            if (is_file($caminho)) {
                @unlink($caminho);
            }
            Database::execute('DELETE FROM `backups` WHERE `id` = :id', ['id' => $b['id']]);
            $total++;
        }

        return $total;
    }

    /**
     * Devolve o histórico de notificações de uma igreja.
     */
    public static function historico(int $igrejaId, int $limite = 50): array
    {
        return Database::select(
            'SELECT * FROM `subscription_notifications`
             WHERE `igreja_id` = :i
             ORDER BY `enviado_em` DESC
             LIMIT ' . (int) $limite,
            ['i' => $igrejaId]
        );
    }
}