<?php

declare(strict_types=1);

namespace App\Services\Notifications\Contracts;

interface NotificationDriverInterface
{
    /**
     * Send a notification message to the recipient.
     * Returns the external message ID or reference.
     */
    public function send(string $recipient, string $content, ?string $subject = null): string;
}
