<?php
namespace App\Middleware;

use App\Repositories\LoginAttemptRepository;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Limite simples por email e IP: 5 falhas/email e 20 falhas/IP em 15 min. */
final class RateLimitMiddleware
{
    public const WINDOW_MINUTES = 15;
    public const MAX_EMAIL_FAILURES = 5;
    public const MAX_IP_FAILURES = 20;

    /** @var LoginAttemptRepository */
    private $attempts;

    public function __construct(?LoginAttemptRepository $attempts = null)
    {
        $this->attempts = $attempts ?: new LoginAttemptRepository();
    }

    public function isLimited(string $email, string $ip): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $normalizedIp = $ip !== '' ? $ip : 'desconhecido';
        $emailFailures = $normalizedEmail === ''
            ? 0
            : $this->attempts->failuresForEmail($normalizedEmail, self::WINDOW_MINUTES);
        $ipFailures = $this->attempts->failuresForIp($normalizedIp, self::WINDOW_MINUTES);
        return $emailFailures >= self::MAX_EMAIL_FAILURES || $ipFailures >= self::MAX_IP_FAILURES;
    }
}
