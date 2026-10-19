<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Mailer;
use App\Core\View;

final class AuthController extends Controller
{
    // ======================================================
    // USUÁRIO DA IGREJA
    // ======================================================

    public function showUserLogin(): void
    {
        View::render('auth/login-user', [
            'title' => 'Entrar — Monana Eclesia',
        ], 'layouts/auth');
    }

    public function userLogin(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $pass  = (string) ($_POST['password'] ?? '');

        if ($email === '' || $pass === '') {
            flash('error', 'Preencha e-mail e palavra-passe.');
            redirect('/login');
        }

        $result = Auth::attemptUser($email, $pass);
        if (!$result['ok']) {
            flash('error', $result['message']);
            redirect('/login');
        }

        $user = Auth::user();
        if ($user && (int) $user['must_change_password'] === 1) {
            redirect('/force-change');
        }

        redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logoutUser();
        flash('success', 'Sessão terminada.');
        redirect('/login');
    }

    // ======================================================
    // SUPERVISOR (plataforma)
    // ======================================================

    public function showSupervisorLogin(): void
    {
        if (Auth::check('supervisor')) {
            redirect('/superadmin');
        }

        View::render('auth/login-supervisor', [
            'title' => 'Superadmin — Monana Eclesia',
        ], 'layouts/auth');
    }

    public function supervisorLogin(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $pass  = (string) ($_POST['password'] ?? '');

        if ($email === '' || $pass === '') {
            flash('error', 'Preencha e-mail e palavra-passe.');
            redirect('/superadmin/login');
        }

        $result = Auth::attemptSupervisor($email, $pass);
        if (!$result['ok']) {
            flash('error', $result['message']);
            redirect('/superadmin/login');
        }

        redirect('/superadmin');
    }

    public function supervisorLogout(): void
    {
        Auth::logoutSupervisor();
        flash('success', 'Sessão de superadmin terminada.');
        redirect('/superadmin/login');
    }

    // ======================================================
    // RECUPERAÇÃO DE SENHA
    // ======================================================

    public function showForgot(): void
    {
        View::render('auth/forgot', [
            'title' => 'Recuperar palavra-passe',
        ], 'layouts/auth');
    }

    public function sendReset(): void
    {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        // Resposta genérica para não revelar se o email existe
        $generic = 'Se o e-mail existir, enviámos as instruções.';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('success', $generic);
            redirect('/forgot');
        }

        $user = Database::first(
            'SELECT * FROM `users` WHERE LOWER(`email`) = :email AND `ativo` = 1 AND `deletado_em` IS NULL LIMIT 1',
            ['email' => $email]
        );

        if ($user) {
            $token  = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', time() + 3600); // 1h

            // Na BD guarda-se só o hash; o token em claro vai apenas no e-mail.
            Database::execute(
                'UPDATE `users` SET `token_reset` = :t, `token_reset_expira` = :e WHERE `id` = :id',
                ['t' => hash('sha256', $token), 'e' => $expira, 'id' => $user['id']]
            );

            $link = url('reset/' . $token);
            $body = '
                <p>Olá, <strong>' . esc($user['nome']) . '</strong>,</p>
                <p>Recebemos um pedido para redefinir a sua palavra-passe no <strong>Monana Eclesia</strong>.</p>
                <p><a href="' . esc($link) . '" style="display:inline-block;padding:10px 18px;background:#b8862b;color:#fff;border-radius:8px;text-decoration:none">Redefinir palavra-passe</a></p>
                <p>Se não fez este pedido, ignore este e-mail. O link expira em 1 hora.</p>
                <p>— Equipa Monana Eclesia</p>
            ';

            Mailer::send($user['email'], $user['nome'], 'Redefinição de palavra-passe', $body);
        }

        flash('success', $generic);
        redirect('/forgot');
    }

    public function showReset(string $token): void
    {
        $user = Database::first(
            'SELECT id FROM `users` WHERE `token_reset` = :t AND `token_reset_expira` > NOW() LIMIT 1',
            ['t' => hash('sha256', $token)]
        );

        if (!$user) {
            flash('error', 'Link inválido ou expirado.');
            redirect('/forgot');
        }

        View::render('auth/reset', [
            'title' => 'Definir nova palavra-passe',
            'token' => $token,
        ], 'layouts/auth');
    }

    public function resetPassword(): void
    {
        $token = (string) ($_POST['token'] ?? '');
        $pass  = (string) ($_POST['password'] ?? '');
        $conf  = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($pass) < 8 || $pass !== $conf) {
            flash('error', 'A palavra-passe deve ter pelo menos 8 caracteres e coincidir.');
            redirect('/reset/' . urlencode($token));
        }

        $user = Database::first(
            'SELECT id FROM `users` WHERE `token_reset` = :t AND `token_reset_expira` > NOW() LIMIT 1',
            ['t' => hash('sha256', $token)]
        );

        if (!$user) {
            flash('error', 'Link inválido ou expirado.');
            redirect('/forgot');
        }

        Database::execute(
            'UPDATE `users`
             SET `senha_hash` = :hash,
                 `token_reset` = NULL,
                 `token_reset_expira` = NULL,
                 `must_change_password` = 0,
                 `tentativas_login` = 0,
                 `bloqueado_ate` = NULL
             WHERE `id` = :id',
            ['hash' => password_hash($pass, PASSWORD_ARGON2ID), 'id' => $user['id']]
        );
        Database::execute('UPDATE `user_sessions` SET `ativo` = 0 WHERE `user_id` = :id', ['id' => $user['id']]);

        flash('success', 'Palavra-passe alterada. Já pode entrar.');
        redirect('/login');
    }

    // ======================================================
    // TROCA OBRIGATÓRIA (must_change_password)
    // ======================================================

    public function showForceChange(): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }
        if ((int) $user['must_change_password'] === 0) {
            redirect('/dashboard');
        }

        View::render('auth/force-change', [
            'title' => 'Definir nova palavra-passe',
        ], 'layouts/auth');
    }

    public function forceChange(): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        $pass = (string) ($_POST['password'] ?? '');
        $conf = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($pass) < 8 || $pass !== $conf) {
            flash('error', 'A palavra-passe deve ter pelo menos 8 caracteres e coincidir.');
            redirect('/force-change');
        }

        Database::execute(
            'UPDATE `users` SET `senha_hash` = :h, `must_change_password` = 0 WHERE `id` = :id',
            ['h' => password_hash($pass, PASSWORD_ARGON2ID), 'id' => $user['id']]
        );

        flash('success', 'Palavra-passe definida. Bem-vindo(a)!');
        redirect('/dashboard');
    }
}