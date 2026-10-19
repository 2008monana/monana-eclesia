<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Rbac;
use App\Core\Settings;
use App\Core\Tenant;
use App\Core\Validator;
use App\Core\View;

final class ConfiguracaoController extends Controller
{
    public function index(): void
    {
        if (!Rbac::can('configuracoes.ver')) {
            flash('error', 'Sem permissão.');
            redirect('/dashboard');
        }

        $igrejaId = Tenant::id();
        $igreja   = Database::first(
            'SELECT * FROM `igrejas` WHERE `id` = :i LIMIT 1',
            ['i' => $igrejaId]
        );

        $settings = Settings::allForTenant();

        View::render('configuracoes/index', [
            'title'    => 'Configurações — Monana Eclesia',
            'user'     => Auth::user(),
            'igreja'   => $igreja,
            'settings' => $settings,
        ], 'layouts/app');
    }

    public function saveIgreja(): void
    {
        if (!Rbac::can('configuracoes.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/configuracoes');
        }

        $igrejaId = Tenant::id();

        $data = [
            'nome'      => trim((string) ($_POST['nome'] ?? '')),
            'nif'       => trim((string) ($_POST['nif'] ?? '')),
            'email'     => trim((string) ($_POST['email'] ?? '')),
            'telefone'  => trim((string) ($_POST['telefone'] ?? '')),
            'endereco'  => trim((string) ($_POST['endereco'] ?? '')),
            'cidade'    => trim((string) ($_POST['cidade'] ?? '')),
            'provincia' => trim((string) ($_POST['provincia'] ?? '')),
            'pais'      => trim((string) ($_POST['pais'] ?? 'Angola')),
        ];

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->required('email', 'E-mail')
          ->email('email', 'E-mail');

        if ($v->fails()) {
            flash('error', 'Verifique os campos: ' . implode(', ', array_keys($v->errors())));
            redirect('/configuracoes');
        }

        Database::execute(
            'UPDATE `igrejas` SET
                `nome` = :nome,
                `nif` = :nif,
                `email` = :email,
                `telefone` = :telefone,
                `endereco` = :endereco,
                `cidade` = :cidade,
                `provincia` = :provincia,
                `pais` = :pais
             WHERE `id` = :id',
            [
                'id'        => $igrejaId,
                'nome'      => $data['nome'],
                'nif'       => $data['nif'] ?: null,
                'email'     => $data['email'],
                'telefone'  => $data['telefone'] ?: null,
                'endereco'  => $data['endereco'] ?: null,
                'cidade'    => $data['cidade'] ?: null,
                'provincia' => $data['provincia'] ?: null,
                'pais'      => $data['pais'] ?: 'Angola',
            ]
        );

        Audit::log('configuracoes', 'editar_igreja', 'igrejas', (string) $igrejaId, null, $data);
        flash('success', 'Dados da igreja atualizados.');
        redirect('/configuracoes');
    }

    public function saveTema(): void
    {
        if (!Rbac::can('configuracoes.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/configuracoes');
        }

        $cor      = trim((string) ($_POST['cor_primaria'] ?? '#b8862b'));
        $versiculo = trim((string) ($_POST['versiculo'] ?? ''));

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
            $cor = '#b8862b';
        }

        Settings::set('cor_primaria', $cor, 'texto');
        Settings::set('versiculo_sidebar', $versiculo, 'texto');

        Audit::log('configuracoes', 'editar_tema', 'settings', null, null, [
            'cor_primaria'      => $cor,
            'versiculo_sidebar' => $versiculo,
        ]);
        flash('success', 'Tema atualizado.');
        redirect('/configuracoes');
    }

    public function saveSmtp(): void
    {
        if (!Rbac::can('configuracoes.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/configuracoes');
        }

        $smtp = [
            'host'       => trim((string) ($_POST['smtp_host'] ?? '')),
            'port'       => (int) ($_POST['smtp_port'] ?? 587),
            'username'   => trim((string) ($_POST['smtp_user'] ?? '')),
            'password'   => (string) ($_POST['smtp_pass'] ?? ''),
            'encryption' => (string) ($_POST['smtp_enc'] ?? 'tls'),
            'from_email' => trim((string) ($_POST['smtp_from'] ?? '')),
            'from_name'  => trim((string) ($_POST['smtp_from_name'] ?? '')),
        ];

        Settings::set('smtp', $smtp, 'json');

        Audit::log('configuracoes', 'editar_smtp', 'settings', null, null, [
            'host' => $smtp['host'],
            'user' => $smtp['username'],
        ]);
        flash('success', 'SMTP atualizado.');
        redirect('/configuracoes');
    }

    public function uploadLogo(): void
    {
        if (!Rbac::can('configuracoes.editar')) {
            flash('error', 'Sem permissão.');
            redirect('/configuracoes');
        }

        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Nenhum ficheiro enviado.');
            redirect('/configuracoes');
        }

        $file = $_FILES['logo'];
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg'];
        $mime = mime_content_type($file['tmp_name']);

        if (!isset($allowed[$mime])) {
            flash('error', 'Formato inválido. Use PNG, JPG ou SVG.');
            redirect('/configuracoes');
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            flash('error', 'O ficheiro excede 2 MB.');
            redirect('/configuracoes');
        }

        $igrejaId = Tenant::id();
        $ext  = $allowed[$mime];
        $nome = 'logo-' . $igrejaId . '-' . time() . '.' . $ext;
        $dest = PUBLIC_PATH . '/uploads/logos/' . $nome;

        if (!is_dir(dirname($dest))) {
            @mkdir(dirname($dest), 0775, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            flash('error', 'Falha ao guardar o ficheiro.');
            redirect('/configuracoes');
        }

        Database::execute(
            'UPDATE `igrejas` SET `logo` = :l WHERE `id` = :i',
            ['l' => 'logos/' . $nome, 'i' => $igrejaId]
        );

        Audit::log('configuracoes', 'upload_logo', 'igrejas', (string) $igrejaId, null, ['logo' => $nome]);
        flash('success', 'Logotipo atualizado.');
        redirect('/configuracoes');
    }
}