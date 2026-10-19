<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Tenant;
use App\Core\Validator;
use App\Core\View;

final class PerfilController extends Controller
{
    public function show(): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        $perfil = Database::first(
            'SELECT * FROM `user_profiles` WHERE `user_id` = :u LIMIT 1',
            ['u' => $user['id']]
        );

        $sessoes = Database::select(
            'SELECT * FROM `user_sessions`
             WHERE `user_id` = :u AND `ativo` = 1 AND `expira_em` > NOW()
             ORDER BY `criado_em` DESC',
            ['u' => $user['id']]
        );

        View::render('perfil/index', [
            'title'    => 'O meu perfil — Monana Eclesia',
            'user'     => $user,
            'perfil'   => $perfil,
            'sessoes'  => $sessoes,
        ], 'layouts/app');
    }

    public function saveDados(): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        $data = [
            'nome'     => trim((string) ($_POST['nome'] ?? '')),
            'telefone' => trim((string) ($_POST['telefone'] ?? '')),
        ];

        $v = new Validator($data);
        $v->required('nome', 'Nome')
          ->maxLen('nome', 150, 'Nome');

        if ($v->fails()) {
            flash('error', 'Nome é obrigatório.');
            redirect('/perfil');
        }

        Database::execute(
            'UPDATE `users` SET `nome` = :n, `telefone` = :t WHERE `id` = :id',
            ['n' => $data['nome'], 't' => $data['telefone'] ?: null, 'id' => $user['id']]
        );

        Audit::log('perfil', 'editar_dados', 'users', (string) $user['id'], null, $data);
        flash('success', 'Dados atualizados.');
        redirect('/perfil');
    }

    public function saveSenha(): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        $atual = (string) ($_POST['senha_atual'] ?? '');
        $nova  = (string) ($_POST['senha_nova'] ?? '');
        $conf  = (string) ($_POST['senha_conf'] ?? '');

        if (!password_verify($atual, (string) $user['senha_hash'])) {
            flash('error', 'A palavra-passe atual está incorreta.');
            redirect('/perfil');
        }

        if (strlen($nova) < 8) {
            flash('error', 'A nova palavra-passe deve ter pelo menos 8 caracteres.');
            redirect('/perfil');
        }

        if ($nova !== $conf) {
            flash('error', 'As palavras-passe não coincidem.');
            redirect('/perfil');
        }

        Database::execute(
            'UPDATE `users` SET `senha_hash` = :h, `must_change_password` = 0, `tentativas_login` = 0, `bloqueado_ate` = NULL WHERE `id` = :id',
            ['h' => password_hash($nova, PASSWORD_ARGON2ID), 'id' => $user['id']]
        );

        Audit::log('perfil', 'alterar_senha', 'users', (string) $user['id'], null, null);
        flash('success', 'Palavra-passe alterada.');
        redirect('/perfil');
    }

    public function revokeSession(string $sid): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        Database::execute(
            'UPDATE `user_sessions` SET `ativo` = 0 WHERE `id` = :s AND `user_id` = :u',
            ['s' => (int) $sid, 'u' => $user['id']]
        );

        Audit::log('perfil', 'revogar_sessao', 'user_sessions', $sid, null, null);
        flash('success', 'Sessão revogada.');
        redirect('/perfil');
    }
}