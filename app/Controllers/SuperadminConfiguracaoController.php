<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Settings;
use App\Core\View;

final class SuperadminConfiguracaoController extends Controller
{
    public function index(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        // Configurações globais (igreja_id IS NULL)
        $settings = [
            'app_nome'                    => Settings::get('app_nome', 'Monana Eclesia', null),
            'app_moeda'                   => Settings::get('app_moeda', 'AOA', null),
            'app_idioma'                  => Settings::get('app_idioma', 'pt-AO', null),
            'app_fuso'                    => Settings::get('app_fuso', 'Africa/Luanda', null),
            'assinatura_tolerancia_horas' => Settings::get('assinatura_tolerancia_horas', '24', null),
            'assinatura_aviso_horas'      => Settings::get('assinatura_aviso_horas', '48', null),
            'backup_retencao_dias'        => Settings::get('backup_retencao_dias', '30', null),
            'smtp'                        => Settings::get('smtp', [], null),
        ];

        View::render('superadmin/configuracoes/index', [
            'title'      => 'Configurações Globais — Superadmin',
            'supervisor' => $supervisor,
            'settings'   => $settings,
        ], 'layouts/superadmin');
    }

    public function saveGeral(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $fields = [
            'app_nome'                    => 'texto',
            'app_moeda'                   => 'texto',
            'app_idioma'                  => 'texto',
            'app_fuso'                    => 'texto',
            'assinatura_tolerancia_horas' => 'numero',
            'assinatura_aviso_horas'      => 'numero',
            'backup_retencao_dias'        => 'numero',
        ];

        $antes = [];
        foreach ($fields as $chave => $tipo) {
            $antes[$chave] = Settings::get($chave, null, null);
        }

        foreach ($fields as $chave => $tipo) {
            if (isset($_POST[$chave])) {
                $valor = trim((string) $_POST[$chave]);
                Settings::set($chave, $valor, $tipo, null);
            }
        }

        Audit::log('superadmin', 'editar_configuracoes_globais', 'settings', null, $antes, $fields);
        flash('success', 'Configurações gerais atualizadas.');
        redirect('/superadmin/configuracoes');
    }

    public function saveSmtp(): void
    {
        $supervisor = Auth::supervisor();
        if (!$supervisor) redirect('/superadmin/login');

        $smtp = [
            'host'       => trim((string) ($_POST['smtp_host'] ?? '')),
            'port'       => (int) ($_POST['smtp_port'] ?? 587),
            'username'   => trim((string) ($_POST['smtp_user'] ?? '')),
            'password'   => (string) ($_POST['smtp_pass'] ?? ''),
            'encryption' => (string) ($_POST['smtp_enc'] ?? 'tls'),
            'from_email' => trim((string) ($_POST['smtp_from'] ?? '')),
            'from_name'  => trim((string) ($_POST['smtp_from_name'] ?? '')),
        ];

        Settings::set('smtp', $smtp, 'json', null);

        Audit::log('superadmin', 'editar_smtp_global', 'settings', null, null, [
            'host' => $smtp['host'],
            'user' => $smtp['username'],
        ]);
        flash('success', 'SMTP global atualizado.');
        redirect('/superadmin/configuracoes');
    }
}