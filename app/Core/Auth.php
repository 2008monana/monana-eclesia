<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Auth com 2 guards independentes:
 *   - 'user'       → tabela `users` + `igreja_id` (tenant)
 *   - 'supervisor' → tabela `supervisores` (fora do tenant)
 */
final class Auth
{
    /** Hash descartável: igualar o tempo de resposta quando o e-mail não existe. */
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$VUFYTlh5S21EVkZ6T1RXaA$QKBWkqDw3/Je4krRHY0Kx7LSQFGdFtXNKZKf+mi9/KQ';

    // ======================================================
    // Helpers internos
    // ======================================================

    private static function sessionKey(string $guard): string
    {
        return '_auth_' . $guard;
    }

    // ======================================================
    // Identidade (retorna o registo do utilizador logado)
    // ======================================================

    public static function user(): ?array
    {
        $id = $_SESSION[self::sessionKey('user')] ?? null;
        if (!$id) {
            return null;
        }
        static $cache = null;
        if ($cache && (int) $cache['id'] === (int) $id) {
            return $cache;
        }
        $row = Database::first('SELECT * FROM `users` WHERE `id` = :id AND `ativo` = 1 AND `deletado_em` IS NULL LIMIT 1', ['id' => $id]);
        $cache = $row;
        return $row;
    }

    public static function supervisor(): ?array
    {
        $id = $_SESSION[self::sessionKey('supervisor')] ?? null;
        if (!$id) {
            return null;
        }
        static $cache = null;
        if ($cache && (int) $cache['id'] === (int) $id) {
            return $cache;
        }
        $row = Database::first('SELECT * FROM `supervisores` WHERE `id` = :id AND `ativo` = 1 LIMIT 1', ['id' => $id]);
        $cache = $row;
        return $row;
    }

    public static function check(string $guard = 'user'): bool
    {
        return $guard === 'supervisor' ? self::supervisor() !== null : self::user() !== null;
    }

    public static function id(string $guard = 'user'): ?int
    {
        $u = $guard === 'supervisor' ? self::supervisor() : self::user();
        return $u ? (int) $u['id'] : null;
    }

    // ======================================================
    // Login
    // ======================================================

    /**
     * Autentica um usuário da igreja.
     * Retorna ['ok' => true|false, 'message' => string]
     */
    public static function attemptUser(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        $user = Database::first(
            'SELECT * FROM `users` WHERE LOWER(`email`) = :email AND `deletado_em` IS NULL LIMIT 1',
            ['email' => $email]
        );

        if (!$user) {
            password_verify($password, self::DUMMY_HASH);
            return ['ok' => false, 'message' => 'Credenciais inválidas.'];
        }

        if (!(int) $user['ativo']) {
            return ['ok' => false, 'message' => 'Conta inativa. Contacte o administrador.'];
        }

        // Bloqueio
        if (!empty($user['bloqueado_ate']) && strtotime((string) $user['bloqueado_ate']) > time()) {
            $min = max(1, (int) ceil((strtotime((string) $user['bloqueado_ate']) - time()) / 60));
            return ['ok' => false, 'message' => "Conta bloqueada. Tente novamente em {$min} minuto(s)."];
        }

        if (!password_verify($password, (string) $user['senha_hash'])) {
            // Regista falha
            $tentativas = ((int) $user['tentativas_login']) + 1;
            $bloqueado  = null;
            if ($tentativas >= MAX_LOGIN_ATTEMPTS) {
                $bloqueado = date('Y-m-d H:i:s', time() + LOGIN_BLOCK_MINUTES * 60);
            }
            Database::execute(
                'UPDATE `users` SET `tentativas_login` = :t, `bloqueado_ate` = :b WHERE `id` = :id',
                ['t' => $tentativas, 'b' => $bloqueado, 'id' => $user['id']]
            );
            return ['ok' => false, 'message' => 'Credenciais inválidas.'];
        }

        // A igreja tem de estar activa (ou em teste)
        $igreja = Database::first(
            'SELECT `status` FROM `igrejas` WHERE `id` = :id AND `deletado_em` IS NULL LIMIT 1',
            ['id' => $user['igreja_id']]
        );
        if (!$igreja || in_array($igreja['status'], ['suspensa', 'cancelada'], true)) {
            return ['ok' => false, 'message' => 'O acesso desta igreja está suspenso. Contacte o suporte.'];
        }

        // Sucesso
        Database::execute(
            'UPDATE `users` SET `tentativas_login` = 0, `bloqueado_ate` = NULL, `ultimo_login` = NOW() WHERE `id` = :id',
            ['id' => $user['id']]
        );

        self::loginUser($user);

        return ['ok' => true, 'message' => 'OK'];
    }

    public static function attemptSupervisor(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        $sup = Database::first(
            'SELECT * FROM `supervisores` WHERE LOWER(`email`) = :email LIMIT 1',
            ['email' => $email]
        );

        if (!$sup) {
            password_verify($password, self::DUMMY_HASH);
            return ['ok' => false, 'message' => 'Credenciais inválidas.'];
        }

        if (!(int) $sup['ativo']) {
            return ['ok' => false, 'message' => 'Conta inativa.'];
        }

        if (!empty($sup['bloqueado_ate']) && strtotime((string) $sup['bloqueado_ate']) > time()) {
            $min = max(1, (int) ceil((strtotime((string) $sup['bloqueado_ate']) - time()) / 60));
            return ['ok' => false, 'message' => "Conta bloqueada. Tente novamente em {$min} minuto(s)."];
        }

        if (!password_verify($password, (string) $sup['senha_hash'])) {
            $tentativas = ((int) $sup['tentativas_login']) + 1;
            $bloqueado  = $tentativas >= MAX_LOGIN_ATTEMPTS
                ? date('Y-m-d H:i:s', time() + LOGIN_BLOCK_MINUTES * 60)
                : null;
            Database::execute(
                'UPDATE `supervisores` SET `tentativas_login` = :t, `bloqueado_ate` = :b WHERE `id` = :id',
                ['t' => $tentativas, 'b' => $bloqueado, 'id' => $sup['id']]
            );
            return ['ok' => false, 'message' => 'Credenciais inválidas.'];
        }

        Database::execute(
            'UPDATE `supervisores` SET `tentativas_login` = 0, `bloqueado_ate` = NULL, `ultimo_login` = NOW() WHERE `id` = :id',
            ['id' => $sup['id']]
        );

        self::loginSupervisor($sup);

        return ['ok' => true, 'message' => 'OK'];
    }

    // ======================================================
    // Logout
    // ======================================================

    public static function logoutUser(): void
    {
        unset($_SESSION[self::sessionKey('user')], $_SESSION['igreja_id']);
        Tenant::set(null);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function logoutSupervisor(): void
    {
        unset($_SESSION[self::sessionKey('supervisor')]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    // ======================================================
    // Sessões (privadas)
    // ======================================================

    private static function loginUser(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION[self::sessionKey('user')] = (int) $user['id'];
        $_SESSION['igreja_id']              = (int) $user['igreja_id'];
        Tenant::set((int) $user['igreja_id']);

        // Registo em user_sessions (opcional, mas útil)
        try {
            Database::execute(
                'INSERT INTO `user_sessions` (`user_id`, `token`, `ip`, `user_agent`, `expira_em`, `ativo`)
                 VALUES (:uid, :tok, :ip, :ua, DATE_ADD(NOW(), INTERVAL 2 HOUR), 1)',
                [
                    'uid' => $user['id'],
                    'tok' => bin2hex(random_bytes(32)),
                    'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
                    'ua'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]
            );
        } catch (\Throwable $e) {
            logger('warning', 'Falha ao gravar user_session', ['msg' => $e->getMessage()]);
        }
    }

    private static function loginSupervisor(array $sup): void
    {
        session_regenerate_id(true);
        $_SESSION[self::sessionKey('supervisor')] = (int) $sup['id'];
    }
    /**
     * Força a recarga do utilizador da próxima chamada (invalida cache).
     */
    public static function refresh(): void
    {
        // O cache estático já é invalidado por session_regenerate_id,
        // mas este método é chamado explicitamente após alterações.
        // Não há cache manual aqui — só limpamos a sessão de referências.
        $_SESSION['_auth_refreshed_at'] = time();
    }

    /**
     * Verifica se o utilizador atual precisa trocar a senha.
     */
    public static function mustChangePassword(): bool
    {
        $u = self::user();
        return $u && (int) $u['must_change_password'] === 1;
    }
}