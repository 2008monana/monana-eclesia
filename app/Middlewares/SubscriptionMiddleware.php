<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Subscription;

/**
 * Mantido por compatibilidade. O AuthMiddleware já faz a verificação.
 */
final class SubscriptionMiddleware
{
    public function handle(): void
    {
        $GLOBALS['__subscription_status'] = Subscription::realStatus();
        $GLOBALS['__subscription_banner'] = Subscription::bannerInfo();
    }
}