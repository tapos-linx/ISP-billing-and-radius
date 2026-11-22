<?php

declare(strict_types=1);

namespace App\Contracts\Payments;

interface WebhookIdempotencyInterface
{
    /**
     * Verify payment signature (HMAC-SHA256) and acquire atomic Redis idempotency lock.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyAndLock(
        string $gateway,
        string $idempotencyKey,
        array $payload,
        ?string $signature = null
    ): bool;

    /**
     * Record completed transaction in payment_webhook_logs within Citus.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordSuccess(
        string $gateway,
        string $idempotencyKey,
        array $payload,
        ?string $subscriberId = null,
        int $tenantId = 1
    ): void;

    /**
     * Verify HMAC-SHA256 signature using timing-safe comparison.
     */
    public function verifyHmacSignature(string $payloadRaw, string $signature, string $secret): bool;
}
