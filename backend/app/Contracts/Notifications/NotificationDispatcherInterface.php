<?php

declare(strict_types=1);

namespace App\Contracts\Notifications;

interface NotificationDispatcherInterface
{
    /**
     * Dispatch a transactional notification across SMS, WhatsApp, or Email.
     *
     * @param  array<string, mixed>  $variables
     */
    public function dispatch(
        string $channel,
        string $recipient,
        string $templateCode,
        array $variables = [],
        ?string $subscriberId = null,
        int $tenantId = 1
    ): bool;
}
